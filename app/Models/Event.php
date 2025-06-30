<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'google_event_id',
        'title',
        'description',
        'location',
        'groupId',
        'url',
        'start',
        'end',
        'allDay',
        'recurrence',
        'attendees',
        'reminders',
        'visibility',
        'status',
        'colorId',
        'organizer',
        'creator',
        'user_id',
        'guest_name',
        'guest_email',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'recurrence' => 'array',
        'attendees' => 'array',
        'reminders' => 'array',
        'organizer' => 'array',
        'creator' => 'array',
        'allDay' => 'boolean',
    ];

    /**
     * Get the user that owns the event.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
