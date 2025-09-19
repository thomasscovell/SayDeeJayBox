<?php
require_once 'config.php';
require_once 'token_manager.php';

// --- Helper Function to get Player ID by Name ---
function getPlayerIdByName($householdId, $playerName, $accessToken) {
    $groupsUrl = "https://api.ws.sonos.com/control/api/v1/households/{$householdId}/groups";
    $options = [
        'http' => [
            'header'  => "Authorization: Bearer " . $accessToken,
            'method'  => 'GET',
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    $groupsResponse = file_get_contents($groupsUrl, false, $context);
    $groupsData = json_decode($groupsResponse, true);

    if (isset($groupsData['players'])) {
        foreach ($groupsData['players'] as $player) {
            if ($player['name'] === $playerName) {
                return $player['id'];
            }
        }
    }
    return null; // Player not found
}

// --- Helper Function to isolate a player and get its new group ID ---
function isolatePlayerAndGetGroupId($householdId, $playerId, $accessToken) {
    $createGroupUrl = "https://api.ws.sonos.com/control/api/v1/households/{$householdId}/groups/createGroup";
    $postData = json_encode(['playerIds' => [$playerId]]);

    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n" .
                         "Authorization: Bearer " . $accessToken,
            'method'  => 'POST',
            'content' => $postData,
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($createGroupUrl, false, $context);
    $groupData = json_decode($response, true);

    // After creating the group, the response contains the new group information, including its ID.
    if (isset($groupData['group']['id'])) {
        return $groupData['group']['id'];
    }
    
    // Fallback for older API versions or different response structures
    if (isset($groupData['id'])) {
        return $groupData['id'];
    }

    return null; // Failed to create or find group
}


// --- Step 1: Input Validation ---
if (!isset($_GET['album'])) {
    http_response_code(400);
    echo "Error: No album specified.";
    exit;
}

$albumAlias = $_GET['album'];

if (!array_key_exists($albumAlias, $ALBUM_FAVORITE_MAP)) {
    http_response_code(404);
    echo "Error: Album alias '" . htmlspecialchars($albumAlias) . "' not found in configuration.";
    exit;
}

$favoriteId = $ALBUM_FAVORITE_MAP[$albumAlias];

// --- Step 2: Authentication ---
try {
    $tokenManager = new SonosTokenManager(SONOS_CLIENT_ID, SONOS_CLIENT_SECRET, TOKEN_FILE_PATH);
    $accessToken = $tokenManager->getAccessToken();
} catch (Exception $e) {
    http_response_code(500);
    echo "Error retrieving access token: " . htmlspecialchars($e->getMessage());
    exit;
}

// --- Step 3: Find Player ID and Isolate It ---
$playerId = getPlayerIdByName(SONOS_HOUSEHOLD_ID, SONOS_PLAYER_NAME, $accessToken);

if (!$playerId) {
    http_response_code(404);
    echo "Error: Could not find the player named '" . htmlspecialchars(SONOS_PLAYER_NAME) . "'. Is the speaker online?";
    exit;
}

// Isolate the player into its own group to target it directly
$groupId = isolatePlayerAndGetGroupId(SONOS_HOUSEHOLD_ID, $playerId, $accessToken);

if (!$groupId) {
    http_response_code(500);
    echo "Error: Could not isolate the speaker. Failed to create a new group for '" . htmlspecialchars(SONOS_PLAYER_NAME) . "'.";
    exit;
}


// --- Step 4: Load Favorite and Play ---
$loadUrl = "https://api.ws.sonos.com/control/api/v1/groups/{$groupId}/favorites";

$postData = json_encode([
    'favoriteId' => $favoriteId,
    'playOnCompletion' => false, // We will send a separate play command
    'action' => 'REPLACE'
]);

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n" .
                     "Authorization: Bearer " . $accessToken,
        'method'  => 'POST',
        'content' => $postData,
        'ignore_errors' => true
    ]
];

$context = stream_context_create($options);
$loadResult = file_get_contents($loadUrl, false, $context);

// Check if the load was successful (HTTP 2xx response)
$loadSuccess = false;
if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (preg_match('/^HTTP\/\d\.\d\s+2(\d{2})\s+.*/', $header)) {
            $loadSuccess = true;
            break;
        }
    }
}

$playSuccess = false;
if ($loadSuccess) {
    // If loading was successful, send the play command
    $playUrl = "https://api.ws.sonos.com/control/api/v1/groups/{$groupId}/playback/play";
    $playOptions = [
        'http' => [
            'header'  => "Authorization: Bearer " . $accessToken . "\r\n" .
                         "Content-Type: application/json",
            'method'  => 'POST',
            'content' => '' // No body needed for play command
        ]
    ];
    $playContext = stream_context_create($playOptions);
    $playResult = file_get_contents($playUrl, false, $playContext);
    
    // Check if the play command was successful
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/^HTTP\/\d\.\d\s+2(\d{2})\s+.*/', $header)) {
                $playSuccess = true;
                break;
            }
        }
    }
}

// --- Step 5: User Feedback ---
$albumName = array_search($favoriteId, $ALBUM_FAVORITE_MAP);
$albumName = ucwords(str_replace('-', ' ', $albumName));

echo '<!DOCTYPE html>';
echo '<html><head><title>Sonos Jukebox</title><meta name="viewport" content="width=device-width, initial-scale=1.0"></head><body>';
echo '<div style="font-family: sans-serif; text-align: center; padding-top: 50px;">';

if ($playSuccess) {
    echo '<h1>Playing on ' . htmlspecialchars(SONOS_PLAYER_NAME) . '</h1>';
    echo '<p style="font-size: 1.2em;">' . htmlspecialchars($albumName) . '</p>';
} else {
    echo '<h1>Error</h1>';
    echo '<p>Could not play the album. There was an issue with the Sonos API.</p>';
    // Provide detailed error context for debugging
    if (!$loadSuccess) {
        echo '<h2>Load Favorite Response:</h2>';
        echo '<pre>' . htmlspecialchars($loadResult) . '</pre>';
    } else {
        echo '<h2>Play Command Response:</h2>';
        echo '<pre>' . htmlspecialchars($playResult) . '</pre>';
    }
}

echo '</div></body></html>';

?>