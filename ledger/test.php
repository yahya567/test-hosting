<?php
/**
 * ledger_demo.php
 *
 * Full demo of:
 * - MySQL schema creation
 * - Register 20 users
 * - Insert transactions with per-transaction hash chain
 * - Compute Merkle root
 * - Scenario A: cached balance tamper -> detect & fix
 * - Scenario B: transaction tamper -> detect, locate, restore from backup, recompute chain and balances
 *
 * Configure DB credentials below, then run: php ledger_demo.php
 */

// ---------------------------
// Configuration: set DB creds
// ---------------------------
$dbHost = '172.17.0.1';
$dbPort = '3306';
$dbName = 'ledger_demo';
$dbUser = 'root';
$dbPass = 'falcon2020'; // set this

// ---------------------------
// Utilities
// ---------------------------
date_default_timezone_set('UTC');

function out($s = "") {
    echo $s . PHP_EOL;
}

function now_iso() {
    return date('c');
}

// Canonical JSON for transaction payloads (stable ordering)
function canonical_json(array $arr) : string {
    ksort($arr);
    // ensure scalar values are encoded consistently
    return json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

// Simple SHA256 transaction hash: hash(canonical_payload + prev_hash)
function tx_hash_from_payload(array $payload, string $prev_hash) : string {
    return hash('sha256', canonical_json($payload) . $prev_hash);
}

// Merkle root builder: accepts array of leaf hashes (strings)
function merkle_root(array $leaves) : ?string {
    if (count($leaves) === 0) return null;
    $level = $leaves;
    while (count($level) > 1) {
        $next = [];
        for ($i = 0; $i < count($level); $i += 2) {
            $left = $level[$i];
            $right = ($i + 1 < count($level)) ? $level[$i + 1] : $left;
            $next[] = hash('sha256', $left . $right);
        }
        $level = $next;
    }
    return $level[0];
}

// ---------------------------
// DB Setup
// ---------------------------
try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort}", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    out("Error connecting to MySQL server: " . $e->getMessage());
    exit(1);
}

// Create database if not exists
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$dbName}`");

// Create tables: users, balances (cached), transactions, tx_backup
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS balances (
    user_id INT PRIMARY KEY,
    balance_bigint BIGINT NOT NULL DEFAULT 0,
    last_updated DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
");

// transactions: tx_id is unique business id, prev_hash and hash are chain fields
$pdo->exec("
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tx_id VARCHAR(64) NOT NULL UNIQUE,
    tx_type ENUM('deposit','transfer','withdrawal') NOT NULL,
    from_user INT NULL,
    to_user INT NULL,
    amount_bigint BIGINT NOT NULL,
    ts DATETIME NOT NULL,
    prev_hash VARCHAR(128) NOT NULL,
    tx_hash VARCHAR(128) NOT NULL,
    raw_payload JSON NOT NULL,
    INDEX (ts),
    FOREIGN KEY (from_user) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (to_user) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS tx_backup (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_tx_id VARCHAR(64) NOT NULL,
    tx_type ENUM('deposit','transfer','withdrawal') NOT NULL,
    from_user INT NULL,
    to_user INT NULL,
    amount_bigint BIGINT NOT NULL,
    ts DATETIME NOT NULL,
    prev_hash VARCHAR(128) NOT NULL,
    tx_hash VARCHAR(128) NOT NULL,
    raw_payload JSON NOT NULL
) ENGINE=InnoDB;
");

// Helper to insert user
function create_user(PDO $pdo, $username) {
    $stmt = $pdo->prepare("INSERT INTO users (username, created_at) VALUES (:u, :c)");
    $stmt->execute([':u' => $username, ':c' => date('Y-m-d H:i:s')]);
    $id = (int)$pdo->lastInsertId();
    $stmt2 = $pdo->prepare("INSERT INTO balances (user_id, balance_bigint, last_updated) VALUES (:uid, 0, :c)");
    $stmt2->execute([':uid' => $id, ':c' => date('Y-m-d H:i:s')]);
    return $id;
}

// Clean up any previous demo data (optional)
$pdo->exec("TRUNCATE TABLE tx_backup");
$pdo->exec("TRUNCATE TABLE transactions");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("TRUNCATE TABLE balances");
$pdo->exec("TRUNCATE TABLE users");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// ---------------------------
// Create 20 users with 0 balances
// ---------------------------
out("Creating 20 users...");
$userIds = [];
for ($i = 1; $i <= 20; $i++) {
    $username = "U{$i}";
    $id = create_user($pdo, $username);
    $userIds[$username] = $id;
}
out("Users created: " . implode(', ', array_keys($userIds)));

// ---------------------------
// Insert example transactions (10 txs like earlier) and build hash chain
// ---------------------------

/**
 * Insert transaction helper: payload fields (tx_id, type, from, to, amount, ts)
 * This function computes prev_hash = last tx_hash in DB or GENESIS
 * Computes tx_hash = hash(payload + prev_hash)
 * Stores raw_payload and hashes in transactions table
 */
function insert_transaction(PDO $pdo, array $tx_payload) {
    // find last prev hash
    $stmt = $pdo->query("SELECT tx_hash FROM transactions ORDER BY id DESC LIMIT 1");
    $prev_hash = 'GENESIS';
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['tx_hash'])) $prev_hash = $row['tx_hash'];

    // Build canonical payload for hashing (exclude prev_hash & tx_hash)
    $payload = [
        'tx_id' => $tx_payload['tx_id'],
        'type' => $tx_payload['type'],
        'from' => $tx_payload['from'] ?? null,
        'to'   => $tx_payload['to'] ?? null,
        'amount' => $tx_payload['amount'],
        'ts' => $tx_payload['ts']
    ];
    $tx_hash = tx_hash_from_payload($payload, $prev_hash);

    // Insert
    $stmt = $pdo->prepare("
        INSERT INTO transactions (tx_id, tx_type, from_user, to_user, amount_bigint, ts, prev_hash, tx_hash, raw_payload)
        VALUES (:txid, :type, :from_user, :to_user, :amount, :ts, :prev_hash, :tx_hash, :raw)
    ");
    $stmt->execute([
        ':txid' => $payload['tx_id'],
        ':type' => $payload['type'],
        ':from_user' => $payload['from'],
        ':to_user' => $payload['to'],
        ':amount' => $payload['amount'],
        ':ts' => $payload['ts'],
        ':prev_hash' => $prev_hash,
        ':tx_hash' => $tx_hash,
        ':raw' => json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
    ]);

    return $tx_hash;
}

// Build transactions set (10 txs)
out("Inserting example transactions and building chain...");

$transactions_to_insert = [
    // tx_id, type, from, to, amount, ts
    ['tx1','deposit', null, 'U1', 100],
    ['tx2','deposit', null, 'U2', 50],
    ['tx3','transfer', 'U1', 'U3', 30],
    ['tx4','transfer', 'U2', 'U6', 20],
    ['tx5','deposit', null, 'U5', 200],
    ['tx6','transfer', 'U3', 'U4', 10],
    ['tx7','withdrawal', 'U4', null, 5],
    ['tx8','transfer', 'U6', 'U7', 15],
    ['tx9','transfer', 'U5', 'U8', 50],
    ['tx10','deposit', null, 'U9', 75],
];

foreach ($transactions_to_insert as $t) {
    list($txid, $type, $fromName, $toName, $amount) = $t;
    $payload = [
        'tx_id' => $txid,
        'type'  => $type,
        'from'  => ($fromName ? $userIds[$fromName] : null),
        'to'    => ($toName ? $userIds[$toName] : null),
        'amount'=> (int)$amount,
        'ts'    => date('Y-m-d H:i:s')
    ];
    $hash = insert_transaction($pdo, $payload);
    out("Inserted {$txid} (type={$type}) hash={$hash}");
    // small sleep to vary timestamps if needed
    usleep(200000);
}

// ---------------------------
// Save backup copy of transactions (tx_backup) for recovery simulation
// ---------------------------
out("Backing up transactions into tx_backup for later recovery...");
$pdo->exec("INSERT INTO tx_backup (original_tx_id, tx_type, from_user, to_user, amount_bigint, ts, prev_hash, tx_hash, raw_payload)
            SELECT tx_id, tx_type, from_user, to_user, amount_bigint, ts, prev_hash, tx_hash, raw_payload FROM transactions");

// ---------------------------
// Recompute cached balances from ledger
// ---------------------------
function recompute_all_balances(PDO $pdo) {
    // init balances to zero
    $stmtReset = $pdo->query("SELECT user_id FROM users");
    $all = $stmtReset->fetchAll(PDO::FETCH_COLUMN);
    foreach ($all as $uid) {
        $s = $pdo->prepare("UPDATE balances SET balance_bigint = 0, last_updated = :t WHERE user_id = :uid");
        $s->execute([':t' => date('Y-m-d H:i:s'), ':uid' => $uid]);
    }

    // replay transactions in order
    $stmt = $pdo->query("SELECT * FROM transactions ORDER BY id ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type = $row['tx_type'];
        $from = $row['from_user'] ? (int)$row['from_user'] : null;
        $to = $row['to_user'] ? (int)$row['to_user'] : null;
        $amt = (int)$row['amount_bigint'];

        if ($type === 'deposit') {
            $s = $pdo->prepare("UPDATE balances SET balance_bigint = balance_bigint + :a, last_updated = :t WHERE user_id = :u");
            $s->execute([':a' => $amt, ':t' => date('Y-m-d H:i:s'), ':u' => $to]);
        } elseif ($type === 'withdrawal') {
            $s = $pdo->prepare("UPDATE balances SET balance_bigint = balance_bigint - :a, last_updated = :t WHERE user_id = :u");
            $s->execute([':a' => $amt, ':t' => date('Y-m-d H:i:s'), ':u' => $from]);
        } elseif ($type === 'transfer') {
            $s1 = $pdo->prepare("UPDATE balances SET balance_bigint = balance_bigint - :a, last_updated = :t WHERE user_id = :u");
            $s2 = $pdo->prepare("UPDATE balances SET balance_bigint = balance_bigint + :a, last_updated = :t WHERE user_id = :u2");
            $s1->execute([':a' => $amt, ':t' => date('Y-m-d H:i:s'), ':u' => $from]);
            $s2->execute([':a' => $amt, ':t' => date('Y-m-d H:i:s'), ':u2' => $to]);
        }
    }
}

// first compute balances
out("Recomputing all balances from ledger...");
recompute_all_balances($pdo);

// Show balances
function print_balances(PDO $pdo, array $userIds) {
    out("Current cached balances:");
    $stmt = $pdo->query("SELECT u.username, b.balance_bigint FROM users u JOIN balances b ON u.id = b.user_id ORDER BY u.id ASC");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        out("  {$r['username']}: {$r['balance_bigint']}");
    }
}
print_balances($pdo, $userIds);

// ---------------------------
// Compute Merkle root for all transaction hashes (single-block)
$out = null;
$stmt = $pdo->query("SELECT tx_hash FROM transactions ORDER BY id ASC");
$leaves = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $leaves[] = $r['tx_hash'];
$root = merkle_root($leaves);
out("Computed Merkle root for current transactions: {$root}");

// ---------------------------
// Verification function: verify chain hashes and block merkle root
// ---------------------------
function verify_chain_and_merkle(PDO $pdo) {
    // verify chain
    $prev = 'GENESIS';
    $stmt = $pdo->query("SELECT id, tx_id, raw_payload, prev_hash, tx_hash FROM transactions ORDER BY id ASC");
    $index = 0;
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $index++;
        $payload = json_decode($r['raw_payload'], true);
        $computed = tx_hash_from_payload($payload, $prev);
        if ($computed !== $r['tx_hash']) {
            return ['ok' => false, 'first_bad_index' => $index, 'first_bad_tx_id' => $r['tx_id']];
        }
        $prev = $computed;
    }

    // verify merkle
    $stmt2 = $pdo->query("SELECT tx_hash FROM transactions ORDER BY id ASC");
    $leaves = [];
    while ($r2 = $stmt2->fetch(PDO::FETCH_ASSOC)) $leaves[] = $r2['tx_hash'];
    $root_calculated = merkle_root($leaves);
    return ['ok' => true, 'merkle' => $root_calculated];
}

// ---------------------------
// Scenario A: Cached balance tamper (only DB balance changed)
out(PHP_EOL . "=== Scenario A: Cached balance tamper (only DB cached balance changed) ===");

// Print U3 real cached balance (should be 30)
$stmt = $pdo->prepare("SELECT b.balance_bigint FROM users u JOIN balances b ON u.id=b.user_id WHERE u.username = :name");
$stmt->execute([':name' => 'U3']); $origU3 = $stmt->fetchColumn();
out("U3 cached balance BEFORE tamper: {$origU3}");

// Tamper: directly set balance to 100 (attacker changes DB)
$pdo->prepare("UPDATE balances b JOIN users u ON b.user_id=u.id SET b.balance_bigint = :v WHERE u.username = :name")
    ->execute([':v' => 100, ':name' => 'U3']);
out("Tampered U3 cached balance set to 100 (DB)");

// Now, when U3 tries to send 50, system must compare ledger-derived balance vs cached claim
function get_ledger_balance_for_user(PDO $pdo, $username) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :n");
    $stmt->execute([':n' => $username]);
    $uid = $stmt->fetchColumn();
    if (!$uid) return null;
    $bal = 0;
    $stmt2 = $pdo->query("SELECT tx_type, from_user, to_user, amount_bigint FROM transactions ORDER BY id ASC");
    while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        if ($r['tx_type'] === 'deposit' && $r['to_user'] == $uid) $bal += (int)$r['amount_bigint'];
        if ($r['tx_type'] === 'withdrawal' && $r['from_user'] == $uid) $bal -= (int)$r['amount_bigint'];
        if ($r['tx_type'] === 'transfer') {
            if ($r['from_user'] == $uid) $bal -= (int)$r['amount_bigint'];
            if ($r['to_user'] == $uid) $bal += (int)$r['amount_bigint'];
        }
    }
    return $bal;
}

$claimed_by_user = 100; // attacker claims they have 100 (cached)
$ledger_balance_u3 = get_ledger_balance_for_user($pdo, 'U3');
out("Ledger-derived balance for U3: {$ledger_balance_u3}");
out("Claimed cached balance for U3: {$claimed_by_user}");

// Decision
if ($claimed_by_user > $ledger_balance_u3) {
    out("SYSTEM ACTION: Reject transaction (claimed > ledger-derived). Now fix cached balance by recomputing from ledger...");
    // Fix
    recompute_all_balances($pdo);
    out("Cached balances recomputed from ledger.");
    print_balances($pdo, $userIds);
} else {
    out("Claim seems fine (should not happen in this demo).");
}

// Re-verify chain & merkle (should be OK since only cached balance was tampered)
$verification = verify_chain_and_merkle($pdo);
out("Verification after Scenario A: " . ($verification['ok'] ? "OK" : "FAIL"));

// ---------------------------
// Scenario B: Transaction tamper (attacker modifies a transaction amount without updating hashes)
out(PHP_EOL . "=== Scenario B: Transaction tamper (modify a transaction amount directly in transactions table) ===");

// Choose a transaction to tamper with: tx3 (U1->U3 30)
$stmt = $pdo->prepare("SELECT id, tx_id, tx_type, from_user, to_user, amount_bigint, tx_hash FROM transactions WHERE tx_id = :txid");
$stmt->execute([':txid' => 'tx3']);
$tx3 = $stmt->fetch(PDO::FETCH_ASSOC);
out("Original tx3: amount={$tx3['amount_bigint']}, tx_hash={$tx3['tx_hash']}");

// Tamper: change amount from 30 -> 100 directly in transactions table (attacker changes data but not hash)
$pdo->prepare("UPDATE transactions SET amount_bigint = :v, raw_payload = :raw WHERE tx_id = :txid")
    ->execute([':v' => 100, ':raw' => json_encode([
        'tx_id' => $tx3['tx_id'],
        'type'  => $tx3['tx_type'],
        'from'  => $tx3['from_user'],
        'to'    => $tx3['to_user'],
        'amount'=> 100,
        'ts'    => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), ':txid' => 'tx3']);

out("Tampered tx3 amount set to 100 (tx row changed) WITHOUT updating tx_hash or downstream hashes.");

// Now verify chain; this should detect the tamper
$verification2 = verify_chain_and_merkle($pdo);
out("Verification after tampering: " . ($verification2['ok'] ? "OK (unexpected)" : "FAIL as expected"));
if (!$verification2['ok']) {
    out("First mismatch at transaction index: " . $verification2['first_bad_index'] . " (tx_id=" . $verification2['first_bad_tx_id'] . ")");
}

// Locate first corrupted transaction (same logical algorithm)
function find_first_mismatch(PDO $pdo) {
    $prev = 'GENESIS';
    $stmt = $pdo->query("SELECT id, tx_id, raw_payload, prev_hash, tx_hash FROM transactions ORDER BY id ASC");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload = json_decode($r['raw_payload'], true);
        $computed = tx_hash_from_payload($payload, $prev);
        if ($computed !== $r['tx_hash']) {
            return ['index' => (int)$r['id'], 'tx_id' => $r['tx_id']];
        }
        $prev = $computed;
    }
    return null;
}

$bad = find_first_mismatch($pdo);
if ($bad) {
    out("Located first mismatched transaction: id={$bad['index']}, tx_id={$bad['tx_id']}");
} else {
    out("No mismatch found (unexpected).");
}

// Recovery plan: restore original transaction data from tx_backup, then recompute chain hashes from that point
out("Recovering from backup...");
// fetch backup row for that tx_id
$stmt = $pdo->prepare("SELECT * FROM tx_backup WHERE original_tx_id = :tid ORDER BY id DESC LIMIT 1");
$stmt->execute([':tid' => $bad['tx_id']]);
$backup = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$backup) {
    out("No backup found for {$bad['tx_id']}. In production you'd restore from immutable storage/replicas.");
} else {
    // restore transaction row to original values
    $restoreStmt = $pdo->prepare("
        UPDATE transactions
           SET tx_type = :type,
               from_user = :fu,
               to_user = :tu,
               amount_bigint = :amt,
               ts = :ts,
               prev_hash = :prev,
               tx_hash = :txhash,
               raw_payload = :raw
         WHERE tx_id = :txid
    ");
    $restoreStmt->execute([
        ':type' => $backup['tx_type'],
        ':fu' => $backup['from_user'],
        ':tu' => $backup['to_user'],
        ':amt' => $backup['amount_bigint'],
        ':ts' => $backup['ts'],
        ':prev' => $backup['prev_hash'],
        ':txhash' => $backup['tx_hash'],
        ':raw' => $backup['raw_payload'],
        ':txid' => $backup['original_tx_id']
    ]);
    out("Restored tx {$backup['original_tx_id']} from backup.");
}

// Now recompute subsequent tx hashes to re-seal the chain
function recompute_hashes_from(PDO $pdo, $start_tx_id) {
    // find prev_hash before start_tx_id
    $stmt = $pdo->prepare("SELECT id FROM transactions WHERE tx_id = :txid");
    $stmt->execute([':txid' => $start_tx_id]);
    $start_id = (int)$stmt->fetchColumn();

    // find previous row (id < start_id) last hash
    $stmt2 = $pdo->prepare("SELECT tx_hash FROM transactions WHERE id = :id");
    $prev_hash = 'GENESIS';
    if ($start_id > 1) {
        $stmtp = $pdo->prepare("SELECT tx_hash FROM transactions WHERE id = :idprev");
        $stmtp->execute([':idprev' => $start_id - 1]);
        $prev_hash = $stmtp->fetchColumn();
        if ($prev_hash === false) $prev_hash = 'GENESIS';
    }

    // iterate from start_id to end, recomputing hashes based on current raw_payload
    $stmtAll = $pdo->prepare("SELECT id, tx_id, raw_payload FROM transactions WHERE id >= :start ORDER BY id ASC");
    $stmtAll->execute([':start' => $start_id]);
    while ($r = $stmtAll->fetch(PDO::FETCH_ASSOC)) {
        $payload = json_decode($r['raw_payload'], true);
        $new_hash = tx_hash_from_payload($payload, $prev_hash);
        $upd = $pdo->prepare("UPDATE transactions SET prev_hash = :prev, tx_hash = :th WHERE id = :id");
        $upd->execute([':prev' => $prev_hash, ':th' => $new_hash, ':id' => $r['id']]);
        $prev_hash = $new_hash;
    }
    return true;
}

// recompute from the BAD transaction's tx_id
recompute_hashes_from($pdo, $bad['tx_id']);
out("Recomputed hashes from bad transaction onward.");

// verify again
$verification3 = verify_chain_and_merkle($pdo);
out("Verification after recovery: " . ($verification3['ok'] ? "OK" : "FAIL"));

// Recompute balances after recovery
recompute_all_balances($pdo);
out("Recomputed balances after recovery:");
print_balances($pdo, $userIds);

// Show final merkle root
$stmt = $pdo->query("SELECT tx_hash FROM transactions ORDER BY id ASC");
$leaves = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $leaves[] = $r['tx_hash'];
$root_final = merkle_root($leaves);
out("Final Merkle root: {$root_final}");

out(PHP_EOL . "Demo complete. You can inspect the DB tables to see all rows (users, balances, transactions, tx_backup).");
