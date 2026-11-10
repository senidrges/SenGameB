<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Group conversations, like WhatsApp groups: a name, a photo, a
     * description, admins, the permissions of the members and an invite link.
     *
     * A member who leaves or is removed keeps their pivot row (`left_at`): the
     * history up to that moment stays readable, nothing newer. `history_from`
     * hides what was said before a member joined. `last_read_at` and
     * `last_delivered_at` give the ticks (read / delivered by every member).
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('type', 10)->default('direct')->after('id');
            $table->string('name', 100)->nullable()->after('direct_key');
            $table->text('description')->nullable()->after('name');
            $table->string('avatar_path')->nullable()->after('description');
            $table->foreignUuid('created_by')->nullable()->after('avatar_path')->constrained('users')->nullOnDelete();
            $table->boolean('members_can_edit_info')->default(true)->after('created_by');
            $table->boolean('members_can_send')->default(true)->after('members_can_edit_info');
            $table->boolean('members_can_add')->default(true)->after('members_can_send');
            $table->boolean('approval_required')->default(false)->after('members_can_add');
            $table->string('invite_code', 32)->nullable()->unique()->after('approval_required');
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->string('role', 10)->default('member');
            $table->timestamp('left_at')->nullable();
            $table->timestamp('history_from')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('last_delivered_at')->nullable();
        });

        Schema::table('messages', function (Blueprint $table) {
            // A group admin deleted the message of someone else
            $table->foreignUuid('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('group_join_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_join_requests');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by_id');
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropColumn(['role', 'left_at', 'history_from', 'last_read_at', 'last_delivered_at']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropUnique(['invite_code']);
            $table->dropColumn([
                'type', 'name', 'description', 'avatar_path', 'members_can_edit_info',
                'members_can_send', 'members_can_add', 'approval_required', 'invite_code',
            ]);
        });
    }
};
