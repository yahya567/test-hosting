<?php
// ============================================================================
// AJAX HANDLER - MUST BE AT THE VERY TOP
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] == 'get_email_body') {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // Set JSON headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    // Configuration
    $email = "brhane163451@gmail.com";
    $appPassword = "dxva artq ipdh qdjc";
    
    // Get parameters
    $uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    $folder = isset($_GET['folder']) ? $_GET['folder'] : 'inbox';
    
    // Mailbox connections
    $mailboxes = [
        'inbox' => "{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX",
        'sent' => "{imap.gmail.com:993/imap/ssl/novalidate-cert}[Gmail]/Sent Mail"
    ];
    
    // Alternative sent folders if needed
    $sentAlternatives = [
        '[Gmail]/Sent',
        'Sent',
        'Sent Items',
        '[Gmail]/Sent Mail'
    ];
    
    function fetchEmailBodyAjax($uid, $folder, $email, $appPassword) {
        global $mailboxes, $sentAlternatives;
        
        // Determine mailbox
        if ($folder == 'sent' && !isset($mailboxes[$folder])) {
            foreach ($sentAlternatives as $altFolder) {
                $testMailbox = "{imap.gmail.com:993/imap/ssl/novalidate-cert}" . $altFolder;
                $testImap = @imap_open($testMailbox, $email, $appPassword);
                if ($testImap) {
                    $mailboxes['sent'] = $testMailbox;
                    imap_close($testImap);
                    break;
                }
            }
        }
        
        if (!isset($mailboxes[$folder])) {
            return ['error' => 'Folder not found'];
        }
        
        $imap = @imap_open($mailboxes[$folder], $email, $appPassword);
        
        if (!$imap) {
            return ['error' => 'Connection failed: ' . imap_last_error()];
        }
        
        $msgNumber = imap_msgno($imap, $uid);
        
        if ($msgNumber == 0) {
            imap_close($imap);
            return ['error' => 'Email not found (UID: ' . $uid . ')'];
        }
        
        // Try to get body
        $body = imap_body($imap, $msgNumber);
        
        // Decode if quoted-printable
        $structure = imap_fetchstructure($imap, $msgNumber);
        if (isset($structure->encoding) && $structure->encoding == 4) {
            $body = imap_qprint($body);
        }
        
        // Convert to UTF-8
        $body = imap_utf8($body);
        
        // Convert plain text to HTML
        if (strpos($body, '<') === false) {
            $body = nl2br(htmlspecialchars($body));
        }
        
        imap_close($imap);
        
        return ['body' => $body];
    }
    
    // Process request
    $result = fetchEmailBodyAjax($uid, $folder, $email, $appPassword);
    echo json_encode($result);
    exit();
}

// ============================================================================
// HTML DISPLAY
// ============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gmail Email Viewer</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { 
            box-sizing: border-box; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            margin: 0; 
            padding: 0; 
        }
        
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: #333; 
            min-height: 100vh; 
            padding: 20px; 
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        
        .header { 
            text-align: center; 
            color: white; 
            padding: 30px 0; 
            margin-bottom: 30px; 
        }
        
        .header h1 { 
            font-size: 2.8rem; 
            margin-bottom: 10px; 
            text-shadow: 1px 1px 3px rgba(0,0,0,0.2); 
        }
        
        .header p { 
            font-size: 1.2rem; 
            opacity: 0.9; 
        }
        
        .connection-status { 
            background: white; 
            border-radius: 10px; 
            padding: 20px; 
            margin-bottom: 30px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
        }
        
        .connection-status h3 { 
            color: #667eea; 
            margin-bottom: 15px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        
        .folder-tabs { 
            display: flex; 
            background: white; 
            border-radius: 10px 10px 0 0; 
            overflow: hidden; 
            margin-bottom: 0; 
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05); 
        }
        
        .tab { 
            padding: 18px 30px; 
            background: #f8f9fa; 
            border: none; 
            font-size: 1.1rem; 
            cursor: pointer; 
            flex: 1; 
            text-align: center; 
            transition: all 0.3s ease; 
            color: #666; 
            text-decoration: none;
        }
        
        .tab:hover { 
            background: #e9ecef; 
        }
        
        .tab.active { 
            background: white; 
            color: #667eea; 
            font-weight: 600; 
            box-shadow: inset 0 -3px 0 #667eea; 
        }
        
        .email-list-container { 
            background: white; 
            border-radius: 0 0 10px 10px; 
            padding: 0; 
            overflow: hidden; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
            margin-bottom: 40px; 
        }
        
        .email-list { 
            max-height: 500px; 
            overflow-y: auto; 
        }
        
        .email-item { 
            padding: 22px 30px; 
            border-bottom: 1px solid #eee; 
            display: flex; 
            align-items: flex-start; 
            gap: 20px; 
            transition: background 0.2s; 
            cursor: pointer; 
        }
        
        .email-item:hover { 
            background: #f8f9ff; 
        }
        
        .email-item:last-child { 
            border-bottom: none; 
        }
        
        .email-icon { 
            background: #e0e7ff; 
            color: #667eea; 
            width: 50px; 
            height: 50px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.3rem; 
            flex-shrink: 0; 
        }
        
        .email-content { 
            flex: 1; 
        }
        
        .email-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            margin-bottom: 8px; 
        }
        
        .email-sender { 
            font-weight: 600; 
            color: #2d3748; 
            font-size: 1.1rem; 
        }
        
        .email-date { 
            color: #718096; 
            font-size: 0.9rem; 
        }
        
        .email-subject { 
            font-weight: 600; 
            color: #4a5568; 
            margin-bottom: 6px; 
            font-size: 1.05rem; 
        }
        
        .email-preview { 
            color: #718096; 
            line-height: 1.5; 
            font-size: 0.95rem; 
        }
        
        .message-viewer { 
            background: white; 
            border-radius: 10px; 
            padding: 30px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
        }
        
        .message-header { 
            border-bottom: 2px solid #f1f1f1; 
            padding-bottom: 20px; 
            margin-bottom: 25px; 
        }
        
        .message-subject { 
            font-size: 1.8rem; 
            color: #2d3748; 
            margin-bottom: 15px; 
        }
        
        .message-meta { 
            display: flex; 
            justify-content: space-between; 
            color: #718096; 
            font-size: 0.95rem; 
        }
        
        .message-body { 
            line-height: 1.7; 
            font-size: 1.05rem; 
            color: #4a5568; 
        }
        
        .message-body img { 
            max-width: 100%; 
            height: auto; 
            border-radius: 8px; 
            margin: 15px 0; 
        }
        
        .no-messages { 
            text-align: center; 
            padding: 60px 20px; 
            color: #a0aec0; 
        }
        
        .no-messages i { 
            font-size: 3rem; 
            margin-bottom: 20px; 
            opacity: 0.5; 
        }
        
        .error-box { 
            background: #fed7d7; 
            color: #742a2a; 
            padding: 20px; 
            border-radius: 10px; 
            border-left: 5px solid #fc8181; 
        }
        
        .pagination { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            padding: 20px; 
            gap: 10px; 
            background: white;
            border-top: 1px solid #eee;
        }
        
        .pagination button { 
            padding: 10px 20px; 
            background: #667eea; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            transition: background 0.3s; 
            font-size: 1rem;
        }
        
        .pagination button:hover { 
            background: #5a67d8; 
        }
        
        .pagination button:disabled { 
            background: #a0aec0; 
            cursor: not-allowed; 
        }
        
        .page-info { 
            color: #718096; 
            font-size: 0.95rem; 
            margin: 0 15px;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .folder-tabs { flex-direction: column; }
            .email-header { flex-direction: column; gap: 5px; }
            .message-meta { flex-direction: column; gap: 10px; }
            .pagination { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-envelope-open-text"></i> Gmail Email Viewer</h1>
            <p>Browse through your sent and received emails</p>
        </div>

<?php
// ============================================================================
// PHP FUNCTIONS FOR DISPLAY
// ============================================================================

// Configuration
$email = "brhane163451@gmail.com";
$appPassword = "sxci bulv cwxh pgdb";

// Get current page and folder
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$folder = isset($_GET['folder']) ? $_GET['folder'] : 'inbox';
$perPage = 10;

// Mailbox connections
$mailboxes = [
    'inbox' => "{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX",
    'sent' => "{imap.gmail.com:993/imap/ssl/novalidate-cert}[Gmail]/Sent Mail"
];

// Alternative sent folders
$sentAlternatives = [
    '[Gmail]/Sent',
    'Sent',
    'Sent Items',
    '[Gmail]/Sent Mail'
];

function fetchPaginatedEmails($folder, $email, $appPassword, $page = 1, $perPage = 10) {
    global $mailboxes, $sentAlternatives;
    
    // Determine correct mailbox for sent folder
    if ($folder == 'sent') {
        $mailboxFound = false;
        
        if (isset($mailboxes['sent'])) {
            $imap = @imap_open($mailboxes['sent'], $email, $appPassword);
            if ($imap) $mailboxFound = true;
        }
        
        if (!$mailboxFound) {
            foreach ($sentAlternatives as $altFolder) {
                $testMailbox = "{imap.gmail.com:993/imap/ssl/novalidate-cert}" . $altFolder;
                $imap = @imap_open($testMailbox, $email, $appPassword);
                if ($imap) {
                    $mailboxes['sent'] = $testMailbox;
                    $mailboxFound = true;
                    break;
                }
            }
        }
    } else {
        $imap = @imap_open($mailboxes[$folder], $email, $appPassword);
        $mailboxFound = ($imap !== false);
    }
    
    if (!$mailboxFound || !$imap) {
        return ['error' => "Failed to connect to $folder. Error: " . imap_last_error()];
    }
    
    // Get mailbox info
    $mailboxInfo = imap_check($imap);
    $totalEmails = $mailboxInfo->Nmsgs;
    
    if ($totalEmails == 0) {
        imap_close($imap);
        return ['emails' => [], 'total' => 0, 'totalPages' => 0];
    }
    
    // Calculate pagination
    $totalPages = ceil($totalEmails / $perPage);
    $startIndex = max(0, $totalEmails - ($page * $perPage));
    $endIndex = max(0, $totalEmails - (($page - 1) * $perPage) - 1);
    
    $emails = [];
    
    // Fetch emails (newest first)
    for ($msgNumber = $endIndex; $msgNumber >= $startIndex && $msgNumber > 0; $msgNumber--) {
        $header = imap_headerinfo($imap, $msgNumber);
        
        // Extract sender
        $from = "Unknown";
        if (isset($header->from[0])) {
            $personal = isset($header->from[0]->personal) ? imap_utf8($header->from[0]->personal) : "";
            $mailboxPart = $header->from[0]->mailbox;
            $hostPart = $header->from[0]->host;
            $from = (!empty($personal) ? $personal . " " : "") . "&lt;" . $mailboxPart . "@" . $hostPart . "&gt;";
        }
        
        // Extract recipient
        $to = "";
        if (isset($header->to)) {
            foreach ($header->to as $toAddress) {
                $toPersonal = isset($toAddress->personal) ? imap_utf8($toAddress->personal) : "";
                $to .= (!empty($toPersonal) ? $toPersonal . " " : "") . "&lt;" . $toAddress->mailbox . "@" . $toAddress->host . "&gt;, ";
            }
            $to = rtrim($to, ", ");
        }
        
        // Get subject and date
        $subject = isset($header->subject) ? imap_utf8($header->subject) : "No Subject";
        $date = isset($header->date) ? date("F j, Y, g:i a", strtotime($header->date)) : "Unknown Date";
        
        // Get UID for AJAX
        $uid = imap_uid($imap, $msgNumber);
        
        // Get preview
        $bodyPreview = imap_fetchbody($imap, $msgNumber, 1, FT_PEEK);
        if (strlen($bodyPreview) < 10) {
            $bodyPreview = imap_body($imap, $msgNumber, FT_PEEK);
        }
        
        // Decode if needed
        $structure = imap_fetchstructure($imap, $msgNumber);
        if (isset($structure->encoding) && $structure->encoding == 4) {
            $bodyPreview = imap_qprint($bodyPreview);
        }
        
        // Clean preview
        $bodyPreview = imap_utf8($bodyPreview);
        $bodyPreview = strip_tags($bodyPreview);
        $preview = strlen($bodyPreview) > 150 ? substr($bodyPreview, 0, 150) . "..." : $bodyPreview;
        
        $emails[] = [
            'id' => $msgNumber,
            'uid' => $uid,
            'from' => $from,
            'to' => $to,
            'subject' => $subject,
            'date' => $date,
            'preview' => $preview
        ];
    }
    
    imap_close($imap);
    
    return [
        'emails' => $emails,
        'total' => $totalEmails,
        'totalPages' => $totalPages,
        'currentPage' => $page
    ];
}

// Fetch emails for current folder
$emailData = fetchPaginatedEmails($folder, $email, $appPassword, $currentPage, $perPage);

// Display connection status
echo '<div class="connection-status">';
echo '<h3><i class="fas fa-plug"></i> Connection Status</h3>';

if (!isset($emailData['error'])) {
    $folderName = $folder == 'inbox' ? 'Inbox' : 'Sent';
    $totalEmails = $emailData['total'];
    $totalPages = $emailData['totalPages'];
    echo "<p>✓ Successfully connected to Gmail.</p>";
    echo "<p>Found $totalEmails emails in $folderName. Showing page $currentPage of $totalPages.</p>";
} else {
    echo '<div class="error-box">';
    echo "<p>⚠ Connection Error</p>";
    echo "<p>{$emailData['error']}</p>";
    echo "<p>Please check: 1) IMAP is enabled in Gmail 2) App password is correct</p>";
    echo '</div>';
}

echo '</div>';

// Get counts for tabs
$inboxCount = 0;
$sentCount = 0;

try {
    $inboxImap = @imap_open($mailboxes['inbox'], $email, $appPassword);
    if ($inboxImap) {
        $inboxInfo = imap_check($inboxImap);
        $inboxCount = $inboxInfo->Nmsgs;
        imap_close($inboxImap);
    }
} catch (Exception $e) {
    $inboxCount = 0;
}

// Try to get sent count
foreach ($sentAlternatives as $altFolder) {
    $testMailbox = "{imap.gmail.com:993/imap/ssl/novalidate-cert}" . $altFolder;
    $sentImap = @imap_open($testMailbox, $email, $appPassword);
    if ($sentImap) {
        $sentInfo = imap_check($sentImap);
        $sentCount = $sentInfo->Nmsgs;
        imap_close($sentImap);
        break;
    }
}

// Folder tabs
echo '<div class="folder-tabs">';
echo '<a href="?folder=inbox&page=1" class="tab ' . ($folder == 'inbox' ? 'active' : '') . '">';
echo '<i class="fas fa-inbox"></i> Inbox (' . $inboxCount . ')';
echo '</a>';
echo '<a href="?folder=sent&page=1" class="tab ' . ($folder == 'sent' ? 'active' : '') . '">';
echo '<i class="fas fa-paper-plane"></i> Sent (' . $sentCount . ')';
echo '</a>';
echo '</div>';

// Email list container
echo '<div class="email-list-container">';
echo '<div class="email-list" id="emailList">';

if (isset($emailData['emails']) && count($emailData['emails']) > 0) {
    foreach ($emailData['emails'] as $index => $emailItem) {
        $senderDisplay = ($folder == 'sent' && !empty($emailItem['to'])) 
            ? 'To: ' . $emailItem['to'] 
            : $emailItem['from'];
        
        echo '<div class="email-item" onclick="loadEmailBody(' . $emailItem['uid'] . ', \'' . $folder . '\', this)">';
        echo '<div class="email-icon">';
        echo ($folder == 'sent') ? '<i class="fas fa-paper-plane"></i>' : '<i class="fas fa-envelope"></i>';
        echo '</div>';
        echo '<div class="email-content">';
        echo '<div class="email-header">';
        echo '<div class="email-sender">' . $senderDisplay . '</div>';
        echo '<div class="email-date">' . $emailItem['date'] . '</div>';
        echo '</div>';
        echo '<div class="email-subject">' . $emailItem['subject'] . '</div>';
        echo '<div class="email-preview">' . $emailItem['preview'] . '</div>';
        echo '</div>';
        echo '</div>';
    }
} else {
    echo '<div class="no-messages">';
    echo '<i class="fas fa-envelope-open"></i>';
    echo '<h3>No messages found</h3>';
    echo '<p>' . (isset($emailData['error']) ? 'Error loading emails' : 'This folder appears to be empty') . '</p>';
    echo '</div>';
}

echo '</div>'; // Close email-list

// Pagination
if (isset($emailData['totalPages']) && $emailData['totalPages'] > 1) {
    echo '<div class="pagination">';
    
    // First page
    if ($currentPage > 1) {
        echo '<a href="?folder=' . $folder . '&page=1"><button><i class="fas fa-angle-double-left"></i> First</button></a>';
    } else {
        echo '<button disabled><i class="fas fa-angle-double-left"></i> First</button>';
    }
    
    // Previous page
    if ($currentPage > 1) {
        echo '<a href="?folder=' . $folder . '&page=' . ($currentPage - 1) . '"><button><i class="fas fa-chevron-left"></i> Prev</button></a>';
    } else {
        echo '<button disabled><i class="fas fa-chevron-left"></i> Prev</button>';
    }
    
    // Page info
    echo '<span class="page-info">Page ' . $currentPage . ' of ' . $emailData['totalPages'] . '</span>';
    
    // Next page
    if ($currentPage < $emailData['totalPages']) {
        echo '<a href="?folder=' . $folder . '&page=' . ($currentPage + 1) . '"><button>Next <i class="fas fa-chevron-right"></i></button></a>';
    } else {
        echo '<button disabled>Next <i class="fas fa-chevron-right"></i></button>';
    }
    
    // Last page
    if ($currentPage < $emailData['totalPages']) {
        echo '<a href="?folder=' . $folder . '&page=' . $emailData['totalPages'] . '"><button>Last <i class="fas fa-angle-double-right"></i></button></a>';
    } else {
        echo '<button disabled>Last <i class="fas fa-angle-double-right"></i></button>';
    }
    
    echo '</div>';
}

echo '</div>'; // Close email-list-container

// Message viewer
echo '<div class="message-viewer" id="messageViewer">';
echo '<div class="no-messages" id="defaultMessage">';
echo '<i class="fas fa-mouse-pointer"></i>';
echo '<h3>Select a message to view</h3>';
echo '<p>Click on any email in the list above to read its contents here</p>';
echo '</div>';
echo '<div id="messageContent" style="display:none;">';
echo '<div class="message-header">';
echo '<h2 class="message-subject" id="msgSubject"></h2>';
echo '<div class="message-meta">';
echo '<div><strong>From:</strong> <span id="msgFrom"></span></div>';
echo '<div><strong>Date:</strong> <span id="msgDate"></span></div>';
echo '</div>';
echo '<div id="msgToContainer" style="margin-top: 10px; display:none;">';
echo '<strong>To:</strong> <span id="msgTo"></span>';
echo '</div>';
echo '</div>';
echo '<div class="message-body" id="msgBody"></div>';
echo '</div>';
echo '</div>';
?>

<script>
let currentOpenEmail = null;

function loadEmailBody(emailUid, folder, element) {
    // Highlight selected email
    if (currentOpenEmail) {
        currentOpenEmail.style.background = "";
    }
    element.style.background = "#f0f4ff";
    currentOpenEmail = element;
    
    // Show message content area
    document.getElementById("defaultMessage").style.display = "none";
    document.getElementById("messageContent").style.display = "block";
    
    // Extract email info from clicked row
    const emailContent = element.querySelector(".email-content");
    document.getElementById("msgSubject").textContent = emailContent.querySelector(".email-subject").textContent;
    
    const senderText = emailContent.querySelector(".email-sender").textContent;
    document.getElementById("msgFrom").textContent = senderText;
    document.getElementById("msgDate").textContent = emailContent.querySelector(".email-date").textContent;
    
    // Handle "To" field for sent emails
    const toContainer = document.getElementById("msgToContainer");
    if (folder === "sent" && senderText.startsWith("To: ")) {
        toContainer.style.display = "block";
        document.getElementById("msgTo").textContent = senderText.substring(4);
        document.getElementById("msgFrom").textContent = "You";
    } else {
        toContainer.style.display = "none";
    }
    
    // Show loading state
    document.getElementById("msgBody").innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div class="loading-spinner" style="margin: 0 auto 15px;"></div>
            <p style="color: #667eea;">Loading email content...</p>
        </div>
    `;
    
    // Make AJAX request
    fetch(`?action=get_email_body&uid=${emailUid}&folder=${folder}&t=${Date.now()}`)
        .then(response => {
            // Check content type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Non-JSON response:', text.substring(0, 500));
                    throw new Error('Server returned non-JSON response. Check for PHP errors.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                document.getElementById("msgBody").innerHTML = `
                    <div class="error-box">
                        <p><strong>Error:</strong> ${data.error}</p>
                        <p>UID: ${emailUid}, Folder: ${folder}</p>
                    </div>
                `;
            } else if (data.body) {
                document.getElementById("msgBody").innerHTML = data.body;
            } else {
                document.getElementById("msgBody").innerHTML = `
                    <div class="error-box">
                        <p>No content received from server</p>
                    </div>
                `;
            }
            
            // Scroll to message viewer
            document.getElementById("messageViewer").scrollIntoView({ 
                behavior: "smooth", 
                block: "start" 
            });
        })
        .catch(error => {
            console.error('Fetch error:', error);
            document.getElementById("msgBody").innerHTML = `
                <div class="error-box">
                    <p><strong>Network Error:</strong> ${error.message}</p>
                    <p>Please check browser console for details.</p>
                </div>
            `;
        });
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
        e.preventDefault();
        const emails = document.querySelectorAll('.email-item');
        if (emails.length === 0) return;
        
        let currentIndex = -1;
        if (currentOpenEmail) {
            for (let i = 0; i < emails.length; i++) {
                if (emails[i] === currentOpenEmail) {
                    currentIndex = i;
                    break;
                }
            }
        }
        
        let newIndex = currentIndex;
        if (e.key === 'ArrowUp' && currentIndex > 0) {
            newIndex = currentIndex - 1;
        } else if (e.key === 'ArrowDown' && currentIndex < emails.length - 1) {
            newIndex = currentIndex + 1;
        }
        
        if (newIndex >= 0 && newIndex < emails.length && newIndex !== currentIndex) {
            const onclickAttr = emails[newIndex].getAttribute('onclick');
            const match = onclickAttr.match(/loadEmailBody\((\d+),\s*'(\w+)'/);
            if (match) {
                const uid = match[1];
                const folder = match[2];
                loadEmailBody(uid, folder, emails[newIndex]);
            }
        }
    }
});

// Add click handler for all email items (in case of dynamic loading)
document.addEventListener('DOMContentLoaded', function() {
    console.log('Gmail Email Viewer loaded successfully');
    console.log('Click any email to view its contents');
});
</script>

    </div> <!-- Close container -->
</body>
</html>