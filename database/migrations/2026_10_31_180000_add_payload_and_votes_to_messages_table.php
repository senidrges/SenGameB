<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Shared locations, contacts, polls and events keep their data in
     * `payload`. Answers to a poll or an event are a JSON map of
     * option => list of user ids in `votes`, like reactions.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->json('payload')->nullable();
            $table->json('votes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['payload', 'votes']);
        });
    }
};
