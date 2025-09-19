<?php
session_start();
require_once 'config.php';
require_once 'token_manager.php';

// --- Step 1: Verify the state for CSRF protection ---
if (empty($_GET['state']) || !isset($_SESSION['oauth2_state']) || ($_GET['state'] !== $_SESSION['oauth2_state'])) {
    unset($_SESSION['oauth2_state']);
    exit('Invalid state. Please try the authorization process again.');
}

// --- Step 2: Check for an authorization code ---
if (!isset($_GET['code'])) {
    exit('Authorization code not found.');
}

$code = $_GET['code'];

// --- Step 3: Exchange the authorization code for tokens ---
try {
    $url = 'https://api.sonos.com/login/v3/oauth/access';
    $credentials = base64_encode(SONOS_CLIENT_ID . ':' . SONOS_CLIENT_SECRET);

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n" .
                         "Authorization: Basic " . $credentials,
            'method'  => 'POST',
            'content' => http_build_query([
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => SONOS_REDIRECT_URI
            ])
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        throw new Exception("Failed to exchange authorization code for tokens.");
    }

    $tokenData = json_decode($result, true);

    // --- Step 4: Store the tokens securely ---
    $tokenManager = new SonosTokenManager(SONOS_CLIENT_ID, SONOS_CLIENT_SECRET, TOKEN_FILE_PATH);
    $tokenManager->storeTokens($tokenData);

    echo '<!DOCTYPE html>';
    echo '<html><head><title>Authorization Successful</title></head><body>';
    echo '<h1>Success!</h1>';
    echo '<p>Your application is now authorized. The authentication tokens have been securely stored.</p>';
    echo '<p>You can now proceed to the next setup steps.</p>';
    echo '</body></html>';

} catch (Exception $e) {
    echo '<h1>Error</h1>';
    echo '<p>An error occurred: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

?>