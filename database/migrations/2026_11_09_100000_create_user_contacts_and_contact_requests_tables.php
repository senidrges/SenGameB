<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Like WhatsApp, two users can talk and play once one of them has the
     * other's number in their phone (`user_contacts`, filled by the contact
     * sync). Anyone else needs an accepted invitation (`contact_requests`).
     * Only the matched accounts are stored, never the numbers of the phone.
     */
    public function up(): void
    {
        Schema::create('user_contacts', function (Blueprint $table) {
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['owner_id', 'contact_id']);
            $table->index('contact_id');
        });

        Schema::create('contact_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 10)->default('pending');
            // Presentation message: at most one per sender and recipient, ever
            $table->text('message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['sender_id', 'recipient_id', 'status']);
            $table->index(['recipient_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
        Schema::dropIfExists('user_contacts');
    }
};
