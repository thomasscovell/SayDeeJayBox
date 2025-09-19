<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Step 1: Enter your Sonos API Credentials
// You can get these by creating a Control Integration on the Sonos Developer Portal: https://developer.sonos.com/
define('SONOS_CLIENT_ID', '[YOUR_CLIENT_ID]');
define('SONOS_CLIENT_SECRET', '[YOUR_CLIENT_SECRET]');

// Step 2: Set up the Redirect URI for OAuth
// This MUST exactly match one of the Redirect URIs you configured in your Sonos Integration.
// It should point to your callback.php script.
// Example: https://your-domain.com/playbox/callback.php
define('SONOS_REDIRECT_URI', '[YOUR_DOMAIN]/callback.php');

// Step 3: Define the path to store the authentication tokens.
// For security, this should be outside your web server's root directory.
// The correct path on your server is /home/dh_dz9jks/sonos_tokens/tokens.json
define('TOKEN_FILE_PATH', '[YOUR_F]/tokens.json');

// Step 4: Configure Household ID and Target Player Name
define('SONOS_HOUSEHOLD_ID', '[YOUR_HOUSEHOLD_ID]');

// This is the name of the speaker you want to control.
// It must exactly match the name in your Sonos app.
define('SONOS_PLAYER_NAME', '[YOUR_SONOS_NAME]');

// Step 5: Load the Album-to-Favorite mapping from the JSON file.
// Use the new list_favorites.php page to edit this mapping.
$map_file = __DIR__ . '/favorites_map.json';
$album_map_json = file_exists($map_file) ? file_get_contents($map_file) : '[]';
$ALBUM_FAVORITE_MAP = json_decode($album_map_json, true);

// Ensure ALBUM_FAVORITE_MAP is always an array to prevent errors
if (!is_array($ALBUM_FAVORITE_MAP)) {
    $ALBUM_FAVORITE_MAP = [];
}

?>