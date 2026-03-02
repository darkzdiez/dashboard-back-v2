<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_login_as_links', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('actor_user_id');
            $table->unsignedBigInteger('target_user_id');
            $table->unsignedSmallInteger('expiration_minutes');
            $table->timestamp('expires_at')->index();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['target_user_id', 'expires_at']);

            $table->foreign('actor_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('target_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_as_links');
    }
};
