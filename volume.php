<?php
require_once 'config.php';
require_once 'token_manager.php';

// --- Helper Function to Find Group ID ---
function getGroupIdByPlayerName($householdId, $playerName, $accessToken) {
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
        $playerId = null;
        foreach ($groupsData['players'] as $player) {
            if ($player['name'] === $playerName) {
                $playerId = $player['id'];
                break;
            }
        }

        if ($playerId && isset($groupsData['groups'])) {
            foreach ($groupsData['groups'] as $group) {
                if (in_array($playerId, $group['playerIds'])) {
                    return $group['id'];
                }
            }
        }
    }
    return null; // Player or group not found
}

// --- Step 1: Input Validation ---
if (!isset($_GET['level'])) {
    http_response_code(400);
    echo "Error: No volume level specified. Please add ?level=X to the URL, where X is 0-100.";
    exit;
}

$volumeLevel = (int)$_GET['level'];
if ($volumeLevel < 0 || $volumeLevel > 100) {
    http_response_code(400);
    echo "Error: Volume level must be between 0 and 100.";
    exit;
}

// --- Step 2: Authentication ---
try {
    $tokenManager = new SonosTokenManager(SONOS_CLIENT_ID, SONOS_CLIENT_SECRET, TOKEN_FILE_PATH);
    $accessToken = $tokenManager->getAccessToken();
} catch (Exception $e) {
    http_response_code(500);
    echo "Error retrieving access token: " . htmlspecialchars($e->getMessage());
    exit;
}

// --- Step 3: Find the correct Group ID dynamically ---
$groupId = getGroupIdByPlayerName(SONOS_HOUSEHOLD_ID, SONOS_PLAYER_NAME, $accessToken);

if (!$groupId) {
    http_response_code(404);
    echo "Error: Could not find a group containing the player named '" . htmlspecialchars(SONOS_PLAYER_NAME) . "'. Is the speaker online?";
    exit;
}

// --- Step 4: Volume Command ---
$url = "https://api.ws.sonos.com/control/api/v1/groups/{$groupId}/groupVolume";

$postData = json_encode(['volume' => $volumeLevel]);

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
$result = file_get_contents($url, false, $context);

// --- Step 5: User Feedback ---
$success = false;
if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (preg_match('/^HTTP\/\d\.\d\s+2(\d{2})\s+.*/', $header)) {
            $success = true;
            break;
        }
    }
}

echo '<!DOCTYPE html>';
echo '<html><head><title>Sonos Jukebox</title><meta name="viewport" content="width=device-width, initial-scale=1.0"></head><body>';
echo '<div style="font-family: sans-serif; text-align: center; padding-top: 50px;">';

if ($success) {
    echo '<h1>Volume Set</h1>';
    echo '<p style="font-size: 1.2em;">Volume on ' . htmlspecialchars(SONOS_PLAYER_NAME) . ' has been set to ' . $volumeLevel . '%.</p>';
} else {
    echo '<h1>Error</h1>';
    echo '<p>Could not set the volume. There was an issue with the Sonos API.</p>';
    echo '<pre>' . htmlspecialchars($result) . '</pre>';
}

echo '</div></body></html>';

?>