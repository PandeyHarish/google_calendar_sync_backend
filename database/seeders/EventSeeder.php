<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Event;
use Carbon\Carbon;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Event::create([
            'user_id' => 1,
            'title' => 'All-day event',
            'start' => '2025-06-23 00:00:00',
            'allDay' => true,

        ]);

        Event::create([
            'user_id' => 1,
            'title' => 'Long event',
            'start' => Carbon::now()->addDays(3),
            'end' => Carbon::now()->addDays(5)
        ]);

        Event::create([
            'user_id' => 1,
            'title' => 'Timed event',
            'start' => Carbon::now()->addHours(12),
        ]);
    }
}
