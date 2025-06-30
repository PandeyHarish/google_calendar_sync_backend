<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use App\Http\Controllers\Traits\ResponseTrait;
use Exception;
use App\Models\User;

class GoogleCalendarController extends Controller
{
    use ResponseTrait;
    protected $calendarService;

    public function __construct(GoogleCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    // GET /api/google-calendarc
    public function index(Request $request)
    {
        try {
            // Automatically push local events to Google Calendar before fetching
            $this->pushLocalEvents($request);

            $user = auth()->user();
            if (!$user || !$user->google_refresh_token) {
                return $this->errorResponse('User is not connected to Google Calendar.', 403);
            }
            $params = $request->only(['timeMin', 'timeMax', 'maxResults']);
            $events = $this->calendarService->listEvents($user, $params);
            $googleEvents = $events->getItems();

            // Fetch only local events that are not synced (no google_event_id)
            $localEvents = \App\Models\Event::whereNull('google_event_id')->get();

            return $this->successResponse([
                'google_events' => $googleEvents,
                'local_events' => $localEvents,
            ], 'Google Calendar and unsynced local events fetched successfully.');
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch Google Calendar events.', 500, $e->getMessage());
        }
    }

    // POST /api/google-calendar
    public function store(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->google_refresh_token) {
                return $this->errorResponse('User is not connected to Google Calendar.', 403);
            }
            $eventData = $request->input('event');
            $event = $this->calendarService->createEvent($user, $eventData);
            return $this->successResponse($event, 'Event created on Google Calendar successfully.');
        } catch (Exception $e) {
            return $this->errorResponse('Failed to create Google Calendar event.', 500, $e->getMessage());
        }
    }

    // GET /api/google-calendar/{event}
    public function show(Request $request, $eventId)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->google_refresh_token) {
                return $this->errorResponse('User is not connected to Google Calendar.', 403);
            }
            $event = $this->calendarService->getEvent($user, $eventId);
            return $this->successResponse($event, 'Google Calendar event retrieved successfully.');
        } catch (Exception $e) {
            return $this->errorResponse('Failed to retrieve Google Calendar event.', 500, $e->getMessage());
        }
    }

    // PUT/PATCH /api/google-calendar/{event}
    public function update(Request $request, $eventId)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->google_refresh_token) {
                return $this->errorResponse('User is not connected to Google Calendar.', 403);
            }

            $incoming = $request->input('event') ?? $request->all();

            if (empty($incoming) || !is_array($incoming)) {
                return $this->errorResponse('No event data provided.', 422);
            }

            $extractDateString = function ($value) {
                if (is_array($value)) {
                    return $value['dateTime'] ?? $value['date'] ?? null;
                }
                return $value;
            };

            $startString = $extractDateString($incoming['start'] ?? null);
            $endString = $extractDateString($incoming['end'] ?? null);

            $eventData = [
                'summary' => $incoming['title'] ?? $incoming['summary'] ?? null,
                'description' => $incoming['description'] ?? null,
                'location' => $incoming['location'] ?? null,
                'attendees' => $incoming['attendees'] ?? [],
                'colorId' => $incoming['colorId'] ?? null,
                'visibility' => $incoming['visibility'] ?? null,
                'status' => $incoming['status'] ?? null,
                'reminders' => $incoming['reminders'] ?? ['useDefault' => true],
                'recurrence' => $incoming['recurrence'] ?? null,
            ];

            if (!empty($incoming['allDay'])) {
                $eventData['start'] = [
                    'date' => (new \DateTime($startString))->format('Y-m-d')
                ];
                $eventData['end'] = [
                    'date' => (new \DateTime($endString))->format('Y-m-d')
                ];
            } else {
                $eventData['start'] = [
                    'dateTime' => (new \DateTime($startString))->format(\DateTime::RFC3339),
                    'timeZone' => 'Asia/Kathmandu'
                ];
                $eventData['end'] = [
                    'dateTime' => (new \DateTime($endString))->format(\DateTime::RFC3339),
                    'timeZone' => 'Asia/Kathmandu'
                ];
            }

            $event = $this->calendarService->updateEvent($user, $eventId, $eventData);
            return $this->successResponse($event, 'Event updated on Google Calendar successfully.');
        } catch (Exception $e) {
            return $this->errorResponse('Failed to update Google Calendar event.', 500, $e->getMessage());
        }
    }

    // DELETE /api/google-calendar/{event}
    public function destroy(Request $request, $eventId)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->google_refresh_token) {
                return $this->errorResponse('User is not connected to Google Calendar.', 403);
            }
            $this->calendarService->deleteEvent($user, $eventId);
            return $this->successResponse(null, 'Event deleted from Google Calendar successfully.', 204);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to delete Google Calendar event.', 500, $e->getMessage());
        }
    }

    /**
     * Push all local events to Google Calendar that do not already exist there.
     */
    public function pushLocalEvents(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->google_refresh_token) {
                return $this->errorResponse('User is not connected to Google Calendar.', 403);
            }

            $localEvents = \App\Models\Event::whereNull('google_event_id')->get();
            $pushed = 0;
            $errors = [];

            foreach ($localEvents as $event) {
                try {
                    $eventData = [
                        'summary' => $event->title,
                        'description' => $event->description,
                        'location' => $event->location,
                        'attendees' => $event->attendees,
                        'reminders' => $event->reminders,
                        'recurrence' => $event->recurrence,
                        'status' => $event->status,
                        'colorId' => $event->colorId,
                        'visibility' => $event->visibility,
                    ];
                    // Handle start
                    if ($event->allDay) {
                        $eventData['start'] = ['date' => (new \DateTime($event->start))->format('Y-m-d')];
                    } else {
                        $eventData['start'] = ['dateTime' => (new \DateTime($event->start))->format(\DateTime::RFC3339)];
                    }
                    // Handle end
                    if (empty($event->end)) {
                        $endDate = new \DateTime($event->start);
                        $endDate->modify('+1 hour');
                        if ($event->allDay) {
                            $eventData['end'] = ['date' => $endDate->format('Y-m-d')];
                        } else {
                            $eventData['end'] = ['dateTime' => $endDate->format(\DateTime::RFC3339)];
                        }
                    } else {
                        if ($event->allDay) {
                            $eventData['end'] = ['date' => (new \DateTime($event->end))->format('Y-m-d')];
                        } else {
                            $eventData['end'] = ['dateTime' => (new \DateTime($event->end))->format(\DateTime::RFC3339)];
                        }
                    }
                    // Handle meeting URL as conferenceData if present
                    if (!empty($event->url)) {
                        $eventData['conferenceData'] = [
                            'createRequest' => [
                                'requestId' => uniqid(),
                                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                            ],
                        ];
                    }
                    $googleEvent = $this->calendarService->createEvent($user, $eventData);
                    $event->google_event_id = $googleEvent->id;
                    $event->save();
                    $pushed++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'event_id' => $event->id,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            return $this->successResponse([
                'pushed' => $pushed,
                'errors' => $errors,
            ], 'Local events pushed to Google Calendar.');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to push local events to Google Calendar.', 500, $e->getMessage());
        }
    }
}