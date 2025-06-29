<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Link to user
            $table->string('google_event_id')->nullable(); // Google event ID
            $table->string('title');
            $table->text('description')->nullable(); // Event description
            $table->string('location')->nullable(); // Event location
            $table->string('groupId')->nullable();
            $table->string('url')->nullable();
            $table->dateTime('start');
            $table->dateTime('end')->nullable();
            $table->boolean('allDay')->default(false);

            // Recurrence
            $table->json('recurrence')->nullable();

            // Attendees
            $table->json('attendees')->nullable();

            // Reminders
            $table->json('reminders')->nullable();

            // Visibility (default, public, private, confidential)
            $table->string('visibility')->nullable();

            // Status (confirmed, tentative, cancelled)
            $table->string('status')->nullable();

            // Color
            $table->string('colorId')->nullable();

            // Organizer/creator
            $table->json('organizer')->nullable();
            $table->json('creator')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
