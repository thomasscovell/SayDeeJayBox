<?php
session_start();
require_once 'config.php';

// Generate a random state value for CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['oauth2_state'] = $state;

// Construct the authorization URL
$params = [
    'client_id'     => SONOS_CLIENT_ID,
    'response_type' => 'code',
    'state'         => $state,
    'scope'         => 'playback-control-all',
    'redirect_uri'  => SONOS_REDIRECT_URI
];

$authUrl = 'https://api.sonos.com/login/v3/oauth?' . http_build_query($params);

// Display the login button to the user
echo '<!DOCTYPE html>';
echo '<html><head><title>Authorize Sonos Jukebox</title></head><body>';
echo '<h1>Step 1: Connect to Sonos</h1>';
echo '<p>Click the button below to authorize this application to control your Sonos system. You will be redirected to the Sonos website to log in and grant permission.</p>';
echo '<a href="' . htmlspecialchars($authUrl) . '" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;">Authorize with Sonos</a>';
echo '</body></html>';

?>