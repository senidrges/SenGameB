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
        Schema::create('love_games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('status', 10)->default('waiting');
            $table->timestamps();

            $table->index('updated_at');
        });

        Schema::create('game_participants', function (Blueprint $table) {
            $table->foreignUuid('game_id')->constrained('love_games')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('player');
            $table->timestamp('joined_at')->useCurrent();

            $table->primary(['game_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('game_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('love_games')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->text('content');
            $table->timestamps();

            $table->unique(['game_id', 'number']);
        });

        Schema::create('game_choices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('love_games')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('choice_number');
            $table->timestamp('chosen_at')->useCurrent();

            $table->unique(['game_id', 'user_id']);
        });

        Schema::create('game_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('love_games')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('reply_to_id')->nullable()->constrained('game_messages')->nullOnDelete();
            $table->text('content');
            $table->json('reactions')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'created_at']);
        });

        Schema::create('game_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 50)->default('Surprise');
            $table->json('items');
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'category', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_templates');
        Schema::dropIfExists('game_messages');
        Schema::dropIfExists('game_choices');
        Schema::dropIfExists('game_items');
        Schema::dropIfExists('game_participants');
        Schema::dropIfExists('love_games');
    }
};
