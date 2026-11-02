<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every profile photo a user has set, so that people can browse the
     * previous ones. Removing the current photo keeps the history; the
     * current avatar of existing users starts it.
     */
    public function up(): void
    {
        Schema::create('profile_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('path');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });

        DB::table('users')
            ->whereNotNull('avatar_path')
            ->where('avatar_path', '!=', '')
            ->select(['id', 'avatar_path', 'updated_at'])
            ->orderBy('id')
            ->each(fn (object $user) => DB::table('profile_photos')->insert([
                'id' => (string) Str::uuid7(),
                'user_id' => $user->id,
                'path' => $user->avatar_path,
                'created_at' => $user->updated_at ?? now(),
            ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_photos');
    }
};
