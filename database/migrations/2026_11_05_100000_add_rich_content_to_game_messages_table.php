<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The game chat works like the conversations: voice messages, photos,
     * files, shared locations, contacts, polls and events, with delivery and
     * read ticks. A message is delivered or read once every other participant
     * of the game received or read it, tracked per participant.
     */
    public function up(): void
    {
        Schema::table('game_messages', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
            $table->string('message_type', 10)->default('text')->after('content');
            $table->string('file_path')->nullable()->after('message_type');
            $table->string('file_name')->nullable()->after('file_path');
            $table->unsignedInteger('file_size')->nullable()->after('file_name');
            $table->json('payload')->nullable()->after('file_size');
            $table->json('votes')->nullable()->after('payload');
            $table->boolean('is_delivered')->default(false)->after('votes');
            $table->boolean('is_read')->default(false)->after('is_delivered');
        });

        Schema::table('game_participants', function (Blueprint $table) {
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
        });

        // Older messages count as read
        DB::table('game_messages')->update(['is_delivered' => true, 'is_read' => true]);
        DB::table('game_participants')->update(['last_delivered_at' => now(), 'last_read_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_participants', function (Blueprint $table) {
            $table->dropColumn(['last_delivered_at', 'last_read_at']);
        });

        Schema::table('game_messages', function (Blueprint $table) {
            $table->dropColumn(['message_type', 'file_path', 'file_name', 'file_size', 'payload', 'votes', 'is_delivered', 'is_read']);
        });
    }
};
