<?php
require_once 'config.php';
require_once 'token_manager.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>"; // For readable output

// --- Get Access Token ---
try {
    $tokenManager = new SonosTokenManager(SONOS_CLIENT_ID, SONOS_CLIENT_SECRET, TOKEN_FILE_PATH);
    $accessToken = $tokenManager->getAccessToken();
    echo "Successfully retrieved access token.\n\n";
} catch (Exception $e) {
    echo "Error retrieving access token: " . htmlspecialchars($e->getMessage());
    echo "\nPlease ensure you have authorized the application by visiting authorize.php first.";
    exit;
}

// --- API Call Function ---
function callSonosAPI($url, $accessToken) {
    $options = [
        'http' => [
            'header'  => "Authorization: Bearer " . $accessToken,
            'method'  => 'GET'
        ]
    ];
    $context = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

// --- 1. Get Households ---
echo "--- Discovering Households ---
";
$householdsUrl = 'https://api.ws.sonos.com/control/api/v1/households';
$householdsResponse = callSonosAPI($householdsUrl, $accessToken);

if (!$householdsResponse) {
    echo "Failed to get households.";
    exit;
}

$householdsData = json_decode($householdsResponse, true);
$householdId = null;

if (!empty($householdsData['households'])) {
    $householdId = $householdsData['households'][0]['id'];
    echo "Found Household ID: <b>" . htmlspecialchars($householdId) . "</b>\n";
    echo "(Copy this value to SONOS_HOUSEHOLD_ID in your config.php)\n\n";
} else {
    echo "No households found for this account.\n";
    exit;
}

// --- 2. Get Groups ---
echo "--- Discovering Groups (Speakers) in Household ---
";
$groupsUrl = "https://api.ws.sonos.com/control/api/v1/households/{$householdId}/groups";
$groupsResponse = callSonosAPI($groupsUrl, $accessToken);

if (!$groupsResponse) {
    echo "Failed to get groups.";
    exit;
}

$groupsData = json_decode($groupsResponse, true);

if (!empty($groupsData['groups'])) {
    echo "Found the following groups:
";
    foreach ($groupsData['groups'] as $group) {
        echo "\nName: <b>" . htmlspecialchars($group['name']) . "</b>\n";
        echo "  Group ID: <b>" . htmlspecialchars($group['id']) . "</b>\n";
        echo "  (If this is your target speaker, copy this Group ID to SONOS_GROUP_ID in config.php)
";
    }
} else {
    echo "No groups found in this household.\n";
}

echo "</pre>";

?>
