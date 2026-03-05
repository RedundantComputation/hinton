<?php
// Speaker Request Form Handler
// Prevents spam with rate limiting and validation

// Start session for CSRF protection
session_start();

// Configuration
$TO_EMAIL = 'patrickyungkang.lee@mail.utoronto.ca';
$RATE_LIMIT_REQUESTS = 5; // Max requests per IP
$RATE_LIMIT_WINDOW = 3600; // Time window in seconds (1 hour)
$SESSION_DIR = sys_get_temp_dir() . '/speaker_requests';
define('MAX_INPUT_LENGTH', 1000);
define('CSRF_TOKEN_LENGTH', 32);

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Create session directory if it doesn't exist with restricted permissions
if (!is_dir($SESSION_DIR)) {
    mkdir($SESSION_DIR, 0700, true);
} else {
    chmod($SESSION_DIR, 0700);
}

// Get client IP
function getClientIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']; // Cloudflare
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ?: 'unknown';
}

// Generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check rate limit with proper file locking to prevent race conditions
function checkRateLimit($ip, $limit, $window) {
    global $SESSION_DIR;
    
    $ip_hash = hash('sha256', $ip);
    $rate_file = $SESSION_DIR . '/' . $ip_hash . '.txt';
    
    $current_time = time();
    
    // Open file with exclusive lock
    $fp = fopen($rate_file, 'c');
    if (!$fp || !flock($fp, LOCK_EX)) {
        return false; // Cannot acquire lock
    }
    
    // Read and process requests
    $requests = array();
    if (file_exists($rate_file)) {
        $data = file_get_contents($rate_file);
        $requests = array_filter(explode("\n", $data));
        
        // Remove old requests outside the window
        $requests = array_filter(
            $requests,
            function($timestamp) use ($current_time, $window) {
                return ($current_time - intval($timestamp)) < $window;
            }
        );
    }
    
    // Check if limit exceeded
    if (count($requests) >= $limit) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false; // Rate limit exceeded
    }
    
    // Add current request and write
    $requests[] = $current_time;
    file_put_contents($rate_file, implode("\n", $requests), LOCK_EX);
    chmod($rate_file, 0600);
    
    // Release lock
    flock($fp, LOCK_UN);
    fclose($fp);
    
    // Clean up old files (older than 24 hours)
    $files = @glob($SESSION_DIR . '/*.txt');
    if ($files) {
        foreach ($files as $file) {
            if ((time() - filemtime($file)) > 86400) {
                @unlink($file);
            }
        }
    }
    
    return true; // Request allowed
}

// Validate form data
function validateFormData($data) {
    $errors = array();
    
    // Required fields
    $required = array('yourName', 'emailAddress', 'pointOfContact', 'eventPurpose', 'eventType', 'eventDate', 'eventTime', 'timeZone', 'willBeRecorded', 'expectPress', 'hasFee', 'typeOfParticipation');
    
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = "Missing required field: $field";
        }
    }
    
    // Email validation
    if (!empty($data['emailAddress']) && !filter_var($data['emailAddress'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address";
    }
    
    // Whitelist validation for select/radio fields
    $allowed_event_types = array('in-person', 'virtual', 'hybrid');
    if (!empty($data['eventType']) && !in_array($data['eventType'], $allowed_event_types)) {
        $errors[] = "Invalid event type";
    }
    
    $allowed_participation = array('keynote', 'panel', 'lecture', 'workshop', 'other');
    if (!empty($data['typeOfParticipation']) && !in_array($data['typeOfParticipation'], $allowed_participation)) {
        $errors[] = "Invalid type of participation";
    }
    
    $allowed_bool_values = array('yes', 'no');
    if (!empty($data['willBeRecorded']) && !in_array($data['willBeRecorded'], $allowed_bool_values)) {
        $errors[] = "Invalid recording preference";
    }
    if (!empty($data['expectPress']) && !in_array($data['expectPress'], $allowed_bool_values)) {
        $errors[] = "Invalid press attendance preference";
    }
    if (!empty($data['hasFee']) && !in_array($data['hasFee'], $allowed_bool_values)) {
        $errors[] = "Invalid fee preference";
    }
    
    $allowed_point_of_contact = array('yes', 'no');
    if (!empty($data['pointOfContact']) && !in_array($data['pointOfContact'], $allowed_point_of_contact)) {
        $errors[] = "Invalid point of contact value";
    }
    
    // Date validation
    if (!empty($data['eventDate'])) {
        $date = DateTime::createFromFormat('Y-m-d', $data['eventDate']);
        if (!$date || $date->format('Y-m-d') !== $data['eventDate']) {
            $errors[] = "Invalid event date";
        }
    }
    
    // Time validation
    if (!empty($data['eventTime'])) {
        $time = DateTime::createFromFormat('H:i', $data['eventTime']);
        if (!$time || $time->format('H:i') !== $data['eventTime']) {
            $errors[] = "Invalid event time";
        }
    }
    
    return $errors;
}

// Sanitize input - check length and remove dangerous characters
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    // Trim and escape HTML
    $sanitized = htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    
    // Check length limits for text fields
    if (strlen($sanitized) > MAX_INPUT_LENGTH) {
        return substr($sanitized, 0, MAX_INPUT_LENGTH);
    }
    
    return $sanitized;
}

// Sanitize email headers to prevent header injection
function sanitizeEmailHeader($header) {
    // Remove any newline or carriage return characters
    return preg_replace('/[\r\n].*/', '', $header);
}

// Handle the request
function handleRequest() {
    global $TO_EMAIL, $RATE_LIMIT_REQUESTS, $RATE_LIMIT_WINDOW;
    
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        return array('success' => false, 'message' => 'Method not allowed');
    }
    
    // Validate CSRF token
    if (empty($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        return array('success' => false, 'message' => 'Invalid request. Please try again.');
    }
    
    // Check honeypot field (should be empty)
    if (!empty($_POST['website'])) {
        http_response_code(400);
        return array('success' => false, 'message' => 'Invalid request.');
    }
    
    // Get client IP
    $client_ip = getClientIP();
    
    // Check rate limit
    if (!checkRateLimit($client_ip, $RATE_LIMIT_REQUESTS, $RATE_LIMIT_WINDOW)) {
        http_response_code(429);
        return array(
            'success' => false,
            'message' => 'Too many requests. Please try again in a few minutes.'
        );
    }
    
    // Sanitize input
    $data = sanitizeInput($_POST);
    
    // Validate data
    $errors = validateFormData($data);
    if (!empty($errors)) {
        http_response_code(400);
        return array(
            'success' => false,
            'message' => 'Validation failed: ' . implode('; ', $errors)
        );
    }
    
    // Build email content with sanitized subject
    // Sanitize subject to remove newlines that could lead to header injection
    $subject = "Speaker Request from " . preg_replace('/[\r\n]/', '', $data['yourName']);
    
    $email_body = "A new speaker request has been submitted.\n\n";
    $email_body .= "=== CONTACT INFORMATION ===\n";
    $email_body .= "Name: " . $data['yourName'] . "\n";
    $email_body .= "Email: " . $data['emailAddress'] . "\n";
    $email_body .= "Point of Contact: " . $data['pointOfContact'] . "\n";
    if ($data['pointOfContact'] === 'no' && !empty($data['alternateContact'])) {
        $email_body .= "Alternate Contact: " . $data['alternateContact'] . "\n";
    }
    $email_body .= "Organization: " . (!empty($data['hostOrganization']) ? $data['hostOrganization'] : 'N/A') . "\n";
    $email_body .= "Organization Location: " . (!empty($data['organizationLocation']) ? $data['organizationLocation'] : 'N/A') . "\n";
    
    $email_body .= "\n=== EVENT DETAILS ===\n";
    $email_body .= "Event Name: " . (!empty($data['eventName']) ? $data['eventName'] : 'N/A') . "\n";
    $email_body .= "Event Purpose: " . $data['eventPurpose'] . "\n";
    $email_body .= "Event Website: " . (!empty($data['eventWebsite']) ? $data['eventWebsite'] : 'N/A') . "\n";
    $email_body .= "Event Type: " . $data['eventType'] . "\n";
    $email_body .= "Event Date: " . $data['eventDate'] . "\n";
    $email_body .= "Event Time: " . $data['eventTime'] . " " . $data['timeZone'] . "\n";
    $email_body .= "Will be Recorded: " . $data['willBeRecorded'] . "\n";
    $email_body .= "Expects Press: " . $data['expectPress'] . "\n";
    $email_body .= "Has Fee: " . $data['hasFee'] . "\n";
    
    $email_body .= "\n=== AUDIENCE DETAILS ===\n";
    $email_body .= "Estimated Attendees: " . (!empty($data['estimatedAttendees']) ? $data['estimatedAttendees'] : 'N/A') . "\n";
    $email_body .= "Audience Type: " . (!empty($data['audienceType']) ? $data['audienceType'] : 'N/A') . "\n";
    $email_body .= "Audience Access: " . (!empty($data['audienceAccess']) ? $data['audienceAccess'] : 'N/A') . "\n";
    
    $email_body .= "\n=== PRESENTATION/SPEECH DETAILS ===\n";
    $email_body .= "Type of Participation: " . $data['typeOfParticipation'] . "\n";
    $email_body .= "Requested Topic: " . (!empty($data['requestedTopic']) ? $data['requestedTopic'] : 'N/A') . "\n";
    $email_body .= "Requested Presenter: " . (!empty($data['requestedPresenter']) ? $data['requestedPresenter'] : 'N/A') . "\n";
    $email_body .= "Additional Notes: " . (!empty($data['otherNotes']) ? $data['otherNotes'] : 'N/A') . "\n";
    
    $email_body .= "\n=== SUBMISSION INFO ===\n";
    $email_body .= "Submitted: " . date('Y-m-d H:i:s') . "\n";
    $email_body .= "IP Address: " . $client_ip . "\n";
    
    // Set email headers with sanitization to prevent header injection
    // Use hardcoded domain instead of HTTP_HOST to prevent spoofing
    $headers = "From: noreply@utoronto.ca\r\n";
    $headers .= "Reply-To: " . sanitizeEmailHeader($data['emailAddress']) . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Send email
    $email_sent = mail($TO_EMAIL, $subject, $email_body, $headers);
    
    if (!$email_sent) {
        http_response_code(500);
        error_log("Failed to send speaker request email from " . $data['emailAddress']);
        return array(
            'success' => false,
            'message' => 'Failed to send request. Please try again later.'
        );
    }
    
    // Send confirmation email to requester
    $confirmation_subject = "Speaker Request Received - Geoffrey Hinton";
    $confirmation_body = "Dear " . $data['yourName'] . ",\n\n";
    $confirmation_body .= "Thank you for submitting your speaker request for Geoffrey Hinton.\n\n";
    $confirmation_body .= "We have received your submission and will review it carefully. ";
    $confirmation_body .= "You can expect to hear from us within 2-3 weeks.\n\n";
    $confirmation_body .= "Best regards,\nGeoffrey Hinton's Office\n";
    
    $confirmation_headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $confirmation_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    mail($data['emailAddress'], $confirmation_subject, $confirmation_body, $confirmation_headers);
    
    return array(
        'success' => true,
        'message' => 'Your speaker request has been submitted successfully. You will receive a confirmation email shortly.'
    );
}

// Handle the request and return JSON response
header('Content-Type: application/json');
$response = handleRequest();
echo json_encode($response);
