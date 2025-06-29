<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Models\Event;
use Illuminate\Support\Facades\Log;

class SyncGoogleCalendar extends Command
{
    protected $signature = 'app:sync-google-calendar';
    protected $description = 'Sync all users Google Calendars with the local database';

    public function handle(GoogleCalendarService $calendarService)
    {
        $this->info('Starting Google Calendar sync for all connected users...');

        $users = User::whereNotNull('google_refresh_token')->get();

        if ($users->isEmpty()) {
            $this->info('No users connected to Google Calendar. Exiting.');
            return;
        }

        foreach ($users as $user) {
            $this->info("Syncing for user: {$user->email}");

            try {
                // 1. Push unsynced local events to Google Calendar
                $localEventsToPush = $user->events()->whereNull('google_event_id')->get();

                foreach ($localEventsToPush as $localEvent) {
                    $this->info("  - Pushing local event '{$localEvent->title}' to Google...");

                    $eventData = [
                        'summary' => $localEvent->title,
                        'start' => ['dateTime' => (new \DateTime($localEvent->start))->format(\DateTime::RFC3339)],
                        'end' => ['dateTime' => (new \DateTime($localEvent->end))->format(\DateTime::RFC3339)],
                    ];

                    if ($localEvent->allDay) {
                        $eventData['start'] = ['date' => (new \DateTime($localEvent->start))->format('Y-m-d')];
                        $eventData['end'] = ['date' => (new \DateTime($localEvent->end))->format('Y-m-d')];
                    }

                    $googleEvent = $calendarService->createEvent($user, $eventData);
                    $localEvent->update(['google_event_id' => $googleEvent->id]);
                }

                // 2. Fetch all Google events
                $googleEvents = $calendarService->listEvents($user)->getItems();
                $googleEventIds = [];

                foreach ($googleEvents as $googleEvent) {
                    if (empty($googleEvent->id))
                        continue;

                    // Handle deleted events
                    if ($googleEvent->getStatus() === 'cancelled') {
                        $this->info("  - Deleting cancelled event ID {$googleEvent->getId()} locally...");
                        $user->events()->where('google_event_id', $googleEvent->getId())->delete();
                        continue;
                    }

                    $googleEventIds[] = $googleEvent->id;
                    $this->info("  - Syncing Google event '{$googleEvent->getSummary()}'...");

                    Event::updateOrCreate(
                        [
                            'google_event_id' => $googleEvent->id,
                            'user_id' => $user->id,
                        ],
                        [
                            'title' => $googleEvent->getSummary(),
                            'start' => $googleEvent->getStart()->getDateTime() ?? $googleEvent->getStart()->getDate(),
                            'end' => $googleEvent->getEnd()->getDateTime() ?? $googleEvent->getEnd()->getDate(),
                            'allDay' => !is_null($googleEvent->getStart()->getDate()),
                        ]
                    );
                }

                // 3. Optionally delete local events not found in Google anymore (extra safety)
                $user->events()
                    ->whereNotNull('google_event_id')
                    ->whereNotIn('google_event_id', $googleEventIds)
                    ->delete();

                $this->info("  - Sync complete for user: {$user->email}");
            } catch (\Exception $e) {
                $this->error("  - Failed to sync for user {$user->email}: " . $e->getMessage());
                Log::error("Google Sync Failed for User ID {$user->id}: " . $e->getMessage());
            }
        }

        $this->info('Google Calendar sync finished.');
    }
}