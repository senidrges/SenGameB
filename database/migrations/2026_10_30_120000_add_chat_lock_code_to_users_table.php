<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Hashed secret code that reveals the locked conversations when typed in
     * the app's conversation search bar.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('chat_lock_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('chat_lock_code');
        });
    }
};
