<?php

class SonosTokenManager {

    private $clientId;
    private $clientSecret;
    private $tokenFilePath;

    public function __construct($clientId, $clientSecret, $tokenFilePath) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->tokenFilePath = $tokenFilePath;
    }

    public function getAccessToken() {
        $tokens = $this->getTokens();

        // Check if the token is expired or close to expiring (e.g., within the next 60 seconds)
        if (!$tokens || !isset($tokens['access_token']) || (time() > ($tokens['expires_at'] - 60))) {
            // Token is expired or invalid, refresh it
            $tokens = $this->refreshAccessToken($tokens['refresh_token']);
        }

        return $tokens['access_token'];
    }

    public function storeTokens($tokenData) {
        $tokens = [
            'access_token'  => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'expires_at'    => time() + $tokenData['expires_in']
        ];

        // Ensure the directory exists
        $dir = dirname($this->tokenFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->tokenFilePath, json_encode($tokens));
    }

    private function getTokens() {
        if (!file_exists($this->tokenFilePath)) {
            return null;
        }
        $json = file_get_contents($this->tokenFilePath);
        return json_decode($json, true);
    }

    private function refreshAccessToken($refreshToken) {
        $url = 'https://api.sonos.com/login/v3/oauth/access';
        $credentials = base64_encode($this->clientId . ':' . $this->clientSecret);

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n" .
                             "Authorization: Basic " . $credentials,
                'method'  => 'POST',
                'content' => http_build_query(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken])
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        if ($result === FALSE) {
            throw new Exception("Failed to refresh access token.");
        }

        $tokenData = json_decode($result, true);
        $this->storeTokens($tokenData);
        return $this->getTokens();
    }
}

?>