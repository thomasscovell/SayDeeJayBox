<?php
require_once 'config.php';
require_once 'token_manager.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$map_file = __DIR__ . '/favorites_map.json';

// Handle form submission for saving aliases
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_map = [];
    if (isset($_POST['aliases']) && is_array($_POST['aliases'])) {
        foreach ($_POST['aliases'] as $favId => $alias) {
            $trimmed_alias = trim($alias);
            if (!empty($trimmed_alias)) {
                $new_map[$trimmed_alias] = $favId;
            }
        }
    }
    file_put_contents($map_file, json_encode($new_map, JSON_PRETTY_PRINT));
    header("Location: " . $_SERVER['PHP_SELF'] . "?saved=true");
    exit;
}

// --- Get Access Token ---
try {
    $tokenManager = new SonosTokenManager(SONOS_CLIENT_ID, SONOS_CLIENT_SECRET, TOKEN_FILE_PATH);
    $accessToken = $tokenManager->getAccessToken();
} catch (Exception $e) {
    echo "Error retrieving access token: " . htmlspecialchars($e->getMessage());
    exit;
}

// --- Get Favorites from Sonos API ---
$householdId = SONOS_HOUSEHOLD_ID;
$favoritesUrl = "https://api.ws.sonos.com/control/api/v1/households/{$householdId}/favorites";
$options = ['http' => ['header' => "Authorization: Bearer " . $accessToken, 'method' => 'GET']];
$context = stream_context_create($options);
$favoritesResponse = file_get_contents($favoritesUrl, false, $context);
$favoritesData = json_decode($favoritesResponse, true);

    // --- Clean up stale entries from the favorites map and load aliases ---
    $validFavoriteIds = [];
    if (!empty($favoritesData['items'])) {
        foreach ($favoritesData['items'] as $item) {
            $validFavoriteIds[] = $item['id'];
        }
    }

    $existing_map_json = file_exists($map_file) ? file_get_contents($map_file) : '[]';
    $existing_map = json_decode($existing_map_json, true);
    $cleaned_map = [];

    if (is_array($existing_map)) {
        foreach ($existing_map as $alias => $favId) {
            if (in_array($favId, $validFavoriteIds)) {
                $cleaned_map[$alias] = $favId;
            }
        }
    }

    // Save the cleaned map back to the file if it has changed
    if (json_encode($cleaned_map, JSON_PRETTY_PRINT) !== json_encode($existing_map, JSON_PRETTY_PRINT)) {
        file_put_contents($map_file, json_encode($cleaned_map, JSON_PRETTY_PRINT));
    }

    // Use the cleaned map for the rest of the page
    $existing_aliases = $cleaned_map;
    $id_to_alias_map = array_flip($existing_aliases);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sonos Favorites Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 20px auto; padding: 0 20px; }
        h1 { text-align: center; }
        .favorite-item { border: 1px solid #ccc; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .item-content { display: flex; align-items: center; }
        .favorite-item img { width: 80px; height: 80px; margin-right: 15px; border-radius: 4px; }
        .favorite-details { flex-grow: 1; }
        .favorite-details strong { font-size: 1.2em; }
        .favorite-details p { margin: 5px 0; color: #555; }
        .alias-input { width: 100%; padding: 8px; margin-top: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .nfc-link-area { margin-top: 15px; }
        .nfc-link-area strong { font-size: 0.9em; color: #333; }
        .nfc-link-input { width: calc(100% - 60px); padding: 8px; border: 1px solid #ddd; background-color: #f9f9f9; border-radius: 4px; }
        .copy-button { width: 50px; padding: 8px; margin-left: 5px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; }
        .save-button { display: block; width: 100%; padding: 15px; background-color: #28a745; color: white; font-size: 1.2em; border: none; border-radius: 8px; cursor: pointer; margin-top: 20px; }
        .save-button:hover { background-color: #218838; }
        .saved-notice { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center; }
    </style>
    <script>
        function copyToClipboard(elementId) {
            const input = document.getElementById(elementId);
            navigator.clipboard.writeText(input.value).then(() => {
                alert("Copied the link!");
            }).catch(err => {
                alert("Failed to copy link.");
            });
        }
    </script>
</head>
<body>

    <h1>Sonos Favorites Manager</h1>

    <?php if (isset($_GET['saved'])): ?>
        <div class="saved-notice">Aliases have been saved successfully!</div>
    <?php endif; ?>

    <form method="POST">
        <?php if (!empty($favoritesData['items'])): ?>
            <?php foreach ($favoritesData['items'] as $item): ?>
                <?php
                    $favId = htmlspecialchars($item['id']);
                    $alias = isset($id_to_alias_map[$favId]) ? htmlspecialchars($id_to_alias_map[$favId]) : '';
                ?>
                <div class="favorite-item">
                    <div class="item-content">
                        <?php if (!empty($item['imageUrl'])): ?>
                            <img src="<?= htmlspecialchars($item['imageUrl']) ?>" alt="Album Art">
                        <?php endif; ?>
                        <div class="favorite-details">
                            <strong><?= htmlspecialchars($item['name']) ?></strong>
                            <p><?= htmlspecialchars($item['description']) ?></p>
                            <p><code>ID: <?= $favId ?></code></p>
                            <input type="text" name="aliases[<?= $favId ?>]" class="alias-input" placeholder="Enter a short alias (e.g. 'doolittle')" value="<?= $alias ?>">
                        </div>
                    </div>
                    <?php if (!empty($alias)): ?>
                        <div class="nfc-link-area">
                            <strong>NFC Link:</strong><br>
                            <input type="text" class="nfc-link-input" value="https://code.shinytoys.xyz/playbox/play.php?album=<?= urlencode($alias) ?>" id="nfc-link-<?= $favId ?>" readonly>
                            <button type="button" class="copy-button" onclick="copyToClipboard('nfc-link-<?= $favId ?>')">Copy</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="save-button">Save All Aliases</button>
        <?php else: ?>
            <p>No favorites found. Please add some albums in your Sonos app first.</p>
        <?php endif; ?>
    </form>

</body>
</html>