<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Exception;
use App\Http\Controllers\Traits\ResponseTrait;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
    use ResponseTrait;
    protected $calendarService;

    public function __construct(GoogleCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Fetch all events for public viewing
            $events = Event::all();
            return $this->successResponse($events, 'Events fetched successfully.');
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch events.', 500, $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'location' => 'nullable|string',
                'start' => 'required|date',
                'end' => 'nullable|date|after_or_equal:start',
                'allDay' => 'sometimes|boolean',
                'groupId' => 'nullable|string',
                // 'url' => 'nullable|string',
                'recurrence' => 'nullable|array',
                'attendees' => 'nullable|array',
                'reminders' => 'nullable|array',
                'visibility' => 'nullable|string|in:default,public,private,confidential',
                'status' => 'nullable|string|in:confirmed,tentative,cancelled',
                'colorId' => 'nullable|string',
                'organizer' => 'nullable|array',
                'creator' => 'nullable|array',
                // Guest fields: required if user is not authenticated
                'guestName' => 'required_if:user_id,null|string|max:255',
                'guestEmail' => 'required_if:user_id,null|email|max:255',
            ]);

            $validated['user_id'] = auth()->id() ?? null;

            // For guests, populate guest fields and map to snake_case for the model
            if (!auth()->check()) {

                $validated['guest_name'] = $validated['guestName'];
                $validated['guest_email'] = $validated['guestEmail'];
                unset($validated['guestName'], $validated['guestEmail']);
            }

            // Prevent double-booking: check for overlapping events
            $overlap = Event::where(function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('start', '<', $validated['end'] ?? $validated['start'])
                        ->where('end', '>', $validated['start']);
                })
                    ->orWhere(function ($q) use ($validated) {
                        $q->whereNull('end')
                            ->where('start', '=', $validated['start']);
                    });
            })->exists();

            if ($overlap) {
                return $this->errorResponse('This slot is already booked. Please choose another slot.', 409);
            }

            if (empty($validated['groupId'])) {
                $validated['groupId'] = Str::uuid()->toString();
            }

            $localEvent = Event::create($validated);

            $user = auth()->user();
            if ($user && $user->google_refresh_token) {
                try {
                    $eventData = [
                        'summary' => $validated['title']
                    ];

                    // Add description if provided
                    if (!empty($validated['description'])) {
                        $eventData['description'] = $validated['description'];
                    }

                    // Add location if provided
                    if (!empty($validated['location'])) {
                        $eventData['location'] = $validated['location'];
                    }

                    // Add attendees if provided
                    if (!empty($validated['attendees'])) {
                        $eventData['attendees'] = $validated['attendees'];
                    }

                    // Add reminders if provided
                    if (!empty($validated['reminders'])) {
                        $eventData['reminders'] = $validated['reminders'];
                    }

                    // Add recurrence if provided
                    if (!empty($validated['recurrence'])) {
                        $eventData['recurrence'] = $validated['recurrence'];
                    }

                    // Add visibility if provided
                    if (!empty($validated['visibility'])) {
                        $eventData['visibility'] = $validated['visibility'];
                    }

                    // Add status if provided
                    if (!empty($validated['status'])) {
                        $eventData['status'] = $validated['status'];
                    }

                    // Add colorId if provided
                    if (!empty($validated['colorId'])) {
                        $eventData['colorId'] = $validated['colorId'];
                    }

                    // Handle start date
                    if (isset($validated['allDay']) && $validated['allDay']) {
                        $eventData['start'] = ['date' => (new \DateTime($validated['start']))->format('Y-m-d')];
                    } else {
                        $eventData['start'] = ['dateTime' => (new \DateTime($validated['start']))->format(\DateTime::RFC3339)];
                    }

                    // Handle end date - if not provided, use start date + 1 hour
                    if (empty($validated['end'])) {
                        $endDate = new \DateTime($validated['start']);
                        $endDate->modify('+1 hour');
                        if (isset($validated['allDay']) && $validated['allDay']) {
                            $eventData['end'] = ['date' => $endDate->format('Y-m-d')];
                        } else {
                            $eventData['end'] = ['dateTime' => $endDate->format(\DateTime::RFC3339)];
                        }
                    } else {
                        if (isset($validated['allDay']) && $validated['allDay']) {
                            $eventData['end'] = ['date' => (new \DateTime($validated['end']))->format('Y-m-d')];
                        } else {
                            $eventData['end'] = ['dateTime' => (new \DateTime($validated['end']))->format(\DateTime::RFC3339)];
                        }
                    }

                    $googleEvent = $this->calendarService->createEvent($user, $eventData);
                    $localEvent->update(['google_event_id' => $googleEvent->id]);
                } catch (Exception $e) {
                    // Google Calendar sync failed, but do not log
                }
            }

            return $this->successResponse($localEvent, 'Event created successfully.', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to create event.', 500, $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        try {
            // The event is automatically fetched by Laravel's route model binding.
            return $this->successResponse($event, 'Event retrieved successfully.');
        } catch (Exception $e) {
            return $this->errorResponse('Failed to retrieve event.', 500, $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'title' => 'string|max:255',
                'description' => 'nullable|string',
                'location' => 'nullable|string',
                'start' => 'date',
                'end' => 'nullable|date|after_or_equal:start',
                'allDay' => 'sometimes|boolean',
                'groupId' => 'nullable|string',
                // 'url' => 'nullable|string',
                'recurrence' => 'nullable|array',
                'attendees' => 'nullable|array',
                'reminders' => 'nullable|array',
                'visibility' => 'nullable|string|in:default,public,private,confidential',
                'status' => 'nullable|string|in:confirmed,tentative,cancelled',
                'colorId' => 'nullable|string',
                'organizer' => 'nullable|array',
                'creator' => 'nullable|array',
            ]);

            $event = auth()->user()->events()->findOrFail($id);
            $event->update($validated);

            if ($event->google_event_id) {
                $user = auth()->user();
                if ($user && $user->google_refresh_token) {
                    $eventData = [
                        'summary' => $validated['title'] ?? $event->title
                    ];

                    // Add description if provided
                    if (!empty($validated['description'])) {
                        $eventData['description'] = $validated['description'];
                    } elseif (isset($validated['description']) && $validated['description'] === '') {
                        $eventData['description'] = '';
                    }

                    // Add location if provided
                    if (!empty($validated['location'])) {
                        $eventData['location'] = $validated['location'];
                    } elseif (isset($validated['location']) && $validated['location'] === '') {
                        $eventData['location'] = '';
                    }

                    // Add attendees if provided
                    if (!empty($validated['attendees'])) {
                        $eventData['attendees'] = $validated['attendees'];
                    } elseif (isset($validated['attendees']) && $validated['attendees'] === []) {
                        $eventData['attendees'] = [];
                    }

                    // Add reminders if provided
                    if (!empty($validated['reminders'])) {
                        $eventData['reminders'] = $validated['reminders'];
                    } elseif (isset($validated['reminders']) && $validated['reminders'] === []) {
                        $eventData['reminders'] = [];
                    }

                    // Add recurrence if provided
                    if (!empty($validated['recurrence'])) {
                        $eventData['recurrence'] = $validated['recurrence'];
                    } elseif (isset($validated['recurrence']) && $validated['recurrence'] === []) {
                        $eventData['recurrence'] = [];
                    }

                    // Add visibility if provided
                    if (!empty($validated['visibility'])) {
                        $eventData['visibility'] = $validated['visibility'];
                    } elseif (isset($validated['visibility']) && $validated['visibility'] === '') {
                        $eventData['visibility'] = '';
                    }

                    // Add status if provided
                    if (!empty($validated['status'])) {
                        $eventData['status'] = $validated['status'];
                    } elseif (isset($validated['status']) && $validated['status'] === '') {
                        $eventData['status'] = '';
                    }

                    // Add colorId if provided
                    if (!empty($validated['colorId'])) {
                        $eventData['colorId'] = $validated['colorId'];
                    } elseif (isset($validated['colorId']) && $validated['colorId'] === '') {
                        $eventData['colorId'] = '';
                    }

                    // Handle start date
                    if (isset($validated['allDay'])) {
                        if ($validated['allDay']) {
                            $eventData['start'] = ['date' => (new \DateTime($validated['start']))->format('Y-m-d')];
                        } else {
                            $eventData['start'] = ['dateTime' => (new \DateTime($validated['start']))->format(\DateTime::RFC3339)];
                        }
                    } else {
                        if ($event->allDay) {
                            $eventData['start'] = ['date' => (new \DateTime($validated['start'] ?? $event->start))->format('Y-m-d')];
                        } else {
                            $eventData['start'] = ['dateTime' => (new \DateTime($validated['start'] ?? $event->start))->format(\DateTime::RFC3339)];
                        }
                    }

                    // Handle end date - if not provided, use start date + 1 hour
                    if (empty($validated['end'])) {
                        $endDate = new \DateTime($validated['start'] ?? $event->start);
                        $endDate->modify('+1 hour');
                        if (isset($validated['allDay'])) {
                            if ($validated['allDay']) {
                                $eventData['end'] = ['date' => $endDate->format('Y-m-d')];
                            } else {
                                $eventData['end'] = ['dateTime' => $endDate->format(\DateTime::RFC3339)];
                            }
                        } else {
                            if ($event->allDay) {
                                $eventData['end'] = ['date' => $endDate->format('Y-m-d')];
                            } else {
                                $eventData['end'] = ['dateTime' => $endDate->format(\DateTime::RFC3339)];
                            }
                        }
                    } else {
                        if (isset($validated['allDay'])) {
                            if ($validated['allDay']) {
                                $eventData['end'] = ['date' => (new \DateTime($validated['end']))->format('Y-m-d')];
                            } else {
                                $eventData['end'] = ['dateTime' => (new \DateTime($validated['end']))->format(\DateTime::RFC3339)];
                            }
                        } else {
                            if ($event->allDay) {
                                $eventData['end'] = ['date' => (new \DateTime($validated['end'] ?? $event->end))->format('Y-m-d')];
                            } else {
                                $eventData['end'] = ['dateTime' => (new \DateTime($validated['end'] ?? $event->end))->format(\DateTime::RFC3339)];
                            }
                        }
                    }

                    $this->calendarService->updateEvent($user, $event->google_event_id, $eventData);
                }
            }

            return $this->successResponse($event, 'Event updated successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Event not found.', 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to update event.', 500, $e->getMessage());
        }
    }

    /**
     * Debug method to check Google Calendar connection status
     */
    public function debugGoogleConnection()
    {
        try {
            $user = auth()->user();
            $connectionStatus = [
                'user_id' => $user->id,
                'has_google_token' => !empty($user->google_token),
                'has_google_refresh_token' => !empty($user->google_refresh_token),
                'google_calendar_connected' => $user->google_calendar_connected,
                'google_id' => $user->google_id,
            ];

            // Try to test Google Calendar connection if refresh token exists
            if ($user->google_refresh_token) {
                try {
                    $this->calendarService->listEvents($user, ['maxResults' => 1]);
                    $connectionStatus['google_api_test'] = 'success';
                } catch (Exception $e) {
                    $connectionStatus['google_api_test'] = 'failed';
                    $connectionStatus['google_api_error'] = $e->getMessage();
                }
            }

            return $this->successResponse($connectionStatus, 'Google Calendar connection status.');
        } catch (Exception $e) {
            return $this->errorResponse('Failed to check Google Calendar connection.', 500, $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $event = Event::findOrFail($id);

            // An authenticated user can delete their own events or guest events.
            if (auth()->id() !== $event->user_id && $event->user_id !== null) {
                return $this->errorResponse('You are not authorized to delete this event.', 403);
            }

            if ($event->google_event_id) {
                // To delete from Google, we need a user context.
                // If it's a guest event, we can't delete it from Google Calendar.
                // If it's an owned event, use the owner's credentials.
                $user = $event->user; // The user who owns the event
                if ($user && $user->google_refresh_token) {
                    $this->calendarService->deleteEvent($user, $event->google_event_id);
                }
            }

            $event->delete();

            return $this->successResponse(null, 'Event deleted successfully.', 204);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Event not found.', 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to delete event.', 500, $e->getMessage());
        }
    }
}
