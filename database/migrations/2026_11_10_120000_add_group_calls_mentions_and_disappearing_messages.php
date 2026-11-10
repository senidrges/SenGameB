<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * - Group calls, like WhatsApp: every member rings, anyone joins or
     *   leaves while the call goes on (`call_participants`).
     * - @mentions: the members a message names (`message_mentions`).
     * - Disappearing messages: a chat setting, and when each message goes.
     * - `audience`: a notice only the admins see (someone left the group).
     */
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->boolean('is_group')->default(false)->after('callee_id');
            $table->uuid('callee_id')->nullable()->change();
        });

        Schema::create('call_participants', function (Blueprint $table) {
            $table->foreignUuid('call_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10);
            $table->timestamp('invited_at')->useCurrent();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();

            $table->primary(['call_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('message_mentions', function (Blueprint $table) {
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['message_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedInteger('disappearing_seconds')->nullable()->after('approval_required');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('audience', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['expires_at', 'audience']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('disappearing_seconds');
        });

        Schema::dropIfExists('message_mentions');
        Schema::dropIfExists('call_participants');

        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn('is_group');
        });
    }
};
