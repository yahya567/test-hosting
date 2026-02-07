<?php
    error_log('Webhook received and processed');
    $secret = 'webHookSecret';
    $input = file_get_contents('php://input');
    $hmac = 'sha256=' . hash_hmac('sha256', $input, $secret);
    
    error_log('HMAC: ' . $hmac);
    error_log("Raw input received: " . $input); // Check your error logs

    // check if X-Signature is set
    if (!isset($_SERVER['HTTP_X_SIGNATURE'])) {
        error_log('X-Signature not set');
        http_response_code(400);
        echo 'X-Signature not set';
        exit;
    }

    // check if X-Signature is valid
    if ($_SERVER['HTTP_X_SIGNATURE'] !== $hmac) {
        error_log('X-Signature is invalid');
        http_response_code(400);
        echo 'X-Signature is invalid';
        exit;
    }

    if (!hash_equals($_SERVER['HTTP_X_SIGNATURE'] ?? '', $hmac)) {
        http_response_code(400);
        echo 'invalid signature';
        exit;
    }

    $data = json_decode($input, true);

    // Access individual values
    if ($data) {
        $id = $data['id'];
        $type = $data['type'];
        $created_at = $data['created_at'];
        
        // Access nested 'data' values
        $msisdn = $data['data']['msisdn'];
        $timestamp = $data['data']['timestamp'];
        $medium = $data['data']['medium'];
        
        // Now you can use these variables as needed
        // For example:
        error_log("ID: $id");
        error_log("Type: $type");
        error_log("Created At: $created_at");
        error_log("MSISDN: $msisdn");
        error_log("Timestamp: $timestamp");
        error_log("Medium: $medium");
    } else {
        // Handle JSON decode error
        error_log("Error: Invalid JSON input");
    }

    // $event = json_decode($input, true);
    // // $dataOne = json_decode($event['data'], true);
    // $dataOne = json_encode($event['data'], JSON_PRETTY_PRINT);
    // switch ($event['type']) {
    //     case 'subscription.created':
    //         error_log('subscription.created');
    //         error_log($dataOne);
    //         break;
    //     case 'subscription.cancelled':
    //         error_log('subscription.cancelled');
    //         error_log($dataOne);
    //         break;
    //     case 'billing.attempt':
    //         error_log('billing.attempt');
    //         error_log($dataOne);
    //         break;
    //     default:
    //         error_log('unknown event type');
    //         error_log($event['type']);
    //         error_log($dataOne);
    //         break;
    // }

    http_response_code(200);
    echo 'ok';