<?php

namespace App\Services;

use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Exception;

class GoogleCalendarService
{
    protected $clientService;

    public function __construct(GoogleClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * Get the Google Calendar service instance for a user, refreshing token if needed.
     */
    protected function getServiceForUser($user): Google_Service_Calendar
    {
        $client = $this->clientService->createClient();
        $client->setAccessToken($user->google_token);

        if ($client->isAccessTokenExpired()) {
            if (!$user->google_refresh_token) {
                throw new Exception('Google token is expired and no refresh token is available. Please re-authenticate.');
            }

            $newToken = $this->clientService->refreshToken($user->google_refresh_token);

            if (isset($newToken['error'])) {
                throw new Exception('Failed to refresh Google token, please re-authenticate. Error: ' . ($newToken['error_description'] ?? 'Unknown error'));
            }

            if (isset($newToken['access_token'])) {
                $user->google_token = $newToken['access_token'];
                $user->save();
                $client->setAccessToken($newToken);
            }
        }

        return new Google_Service_Calendar($client);
    }

    /**
     * List events from the user's primary calendar.
     */
    public function listEvents($user, $params = [])
    {
        $service = $this->getServiceForUser($user);
        return $service->events->listEvents('primary', $params);
    }
    
    /**
     * Get a specific event from the user's primary calendar.
     */
    public function getEvent($user, $eventId)
    {
        $service = $this->getServiceForUser($user);
        return $service->events->get('primary', $eventId);
    }

    /**
     * Create a new event in the user's primary calendar.
     */
    public function createEvent($user, $eventData)
    {
        $service = $this->getServiceForUser($user);
        $event = new Google_Service_Calendar_Event($eventData);
        return $service->events->insert('primary', $event);
    }

    /**
     * Update an event in the user's primary calendar.
     */
    public function updateEvent($user, $eventId, $eventData)
    {
        $service = $this->getServiceForUser($user);
        $event = $service->events->get('primary', $eventId);
        foreach ($eventData as $key => $value) {
            $event->$key = $value;
        }
        return $service->events->update('primary', $eventId, $event);
    }

    /**
     * Delete an event from the user's primary calendar.
     */
    public function deleteEvent($user, $eventId)
    {
        $service = $this->getServiceForUser($user);
        return $service->events->delete('primary', $eventId);
    }
} 