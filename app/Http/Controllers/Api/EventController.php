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
            $events = auth()->user()->events;
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
                'start' => 'required|date',
                'end' => 'nullable|date|after_or_equal:start',
                'allDay' => 'sometimes|boolean',
                'groupId' => 'nullable|string',
                'url' => 'nullable|string',
            ]);

            $validated['user_id'] = auth()->id();

            if (empty($validated['groupId'])) {
                $validated['groupId'] = Str::uuid()->toString();
            }
            
            $localEvent = Event::create($validated);
            
            $user = auth()->user();
            if ($user && $user->google_token) {
                try {
                    $eventData = [
                        'summary' => $validated['title']
                    ];
                    
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
                    // Log the error or handle it as needed, but don't block the response
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
    public function show($id)
    {
        try {
            $event = auth()->user()->events()->findOrFail($id);
            return $this->successResponse($event, 'Event retrieved successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Event not found.', 404);
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
                'start' => 'date',
                'end' => 'nullable|date|after_or_equal:start',
                'allDay' => 'sometimes|boolean',
                'groupId' => 'nullable|string',
                'url' => 'nullable|string',
            ]);

            $event = auth()->user()->events()->findOrFail($id);
            $event->update($validated);

            if ($event->google_event_id) {
                $user = auth()->user();
                if ($user && $user->google_token) {
                    $eventData = [
                        'summary' => $validated['title'] ?? $event->title
                    ];
                    
                    // Handle start date
                    if (isset($validated['allDay'])) {
                        if($validated['allDay']){
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
                            if($validated['allDay']){
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
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $event = auth()->user()->events()->findOrFail($id);
            
            if ($event->google_event_id) {
                $user = auth()->user();
                if ($user && $user->google_token) {
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
