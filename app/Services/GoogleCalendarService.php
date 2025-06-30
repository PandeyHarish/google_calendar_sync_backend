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
        $event = new \Google_Service_Calendar_Event($eventData);
        // ✅ 'sendUpdates' => 'all' makes Google send email invites
        return $service->events->insert('primary', $event, ['sendUpdates' => 'all']);
    }

    /**
     * Update an event in the user's primary calendar.
     */
    public function updateEvent($user, $googleEventId, array $updatedData)
    {
        $service = $this->getServiceForUser($user);
        $event = $service->events->get('primary', $googleEventId);

        if (!empty($updatedData['summary'])) {
            $event->setSummary($updatedData['summary']);
        }

        if (!empty($updatedData['description'])) {
            $event->setDescription($updatedData['description']);
        }

        if (!empty($updatedData['location'])) {
            $event->setLocation($updatedData['location']);
        }

        if (!empty($updatedData['start'])) {
            $event->setStart(new \Google_Service_Calendar_EventDateTime($updatedData['start']));
        }

        if (!empty($updatedData['end'])) {
            $event->setEnd(new \Google_Service_Calendar_EventDateTime($updatedData['end']));
        }

        if (!empty($updatedData['attendees'])) {
            $event->setAttendees($updatedData['attendees']);
        }

        if (!empty($updatedData['colorId'])) {
            $event->setColorId($updatedData['colorId']);
        }

        if (!empty($updatedData['visibility'])) {
            $event->setVisibility($updatedData['visibility']);
        }

        if (!empty($updatedData['status'])) {
            $event->setStatus($updatedData['status']);
        }

        if (!empty($updatedData['reminders'])) {
            $event->setReminders(new \Google_Service_Calendar_EventReminders($updatedData['reminders']));
        }

        if (!empty($updatedData['recurrence'])) {
            $event->setRecurrence($updatedData['recurrence']);
        }

        return $service->events->update('primary', $googleEventId, $event, ['sendUpdates' => 'all']);
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