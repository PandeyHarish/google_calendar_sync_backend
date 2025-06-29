<?php

namespace App\Services;

use Google_Client;

class GoogleClientService
{
    /**
     * Initialize and return a configured Google_Client instance.
     *
     * @return Google_Client
     */
    public function createClient(): Google_Client
    {
        $client = new Google_Client();

        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->setAccessType('offline'); // To get refresh tokens
        $client->setPrompt('consent'); // Force consent every time
        $client->setScopes([
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
            'openid',
        ]);

        return $client;
    }

    /**
     * Refresh the Google access token using a refresh token.
     *
     * @param string $refreshToken
     * @return array|null
     */
    public function refreshToken($refreshToken)
    {
        $client = $this->createClient();
        $client->refreshToken($refreshToken);
        return $client->getAccessToken();
    }
}