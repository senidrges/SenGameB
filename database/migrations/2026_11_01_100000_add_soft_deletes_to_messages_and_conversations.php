<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nothing a user deletes is ever removed from the database:
     * - a message deleted for everyone keeps its content, `deleted_at` dates it;
     * - a message deleted "for me" gets a `hidden_messages` row for that user;
     * - a conversation deleted by a participant gets `deleted_at` on their
     *   pivot row: older messages are hidden from them, newer ones show again.
     *
     * `forwarded_from_id` keeps track of the message a forwarded copy came from.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
            $table->foreignUuid('forwarded_from_id')->nullable()->constrained('messages')->nullOnDelete();
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('hidden_messages', function (Blueprint $table) {
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('hidden_at')->useCurrent();

            $table->primary(['message_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hidden_messages');

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('forwarded_from_id');
            $table->dropColumn('deleted_at');
        });
    }
};
