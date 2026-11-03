<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Audio and video calls between the two participants of a conversation.
     * The provider carrying the media (LiveKit, peer to peer...) is chosen
     * when the call starts and kept for both sides.
     */
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('caller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('callee_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('provider');
            $table->string('status');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'answered_at']);
            $table->index(['caller_id', 'status']);
            $table->index(['callee_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
