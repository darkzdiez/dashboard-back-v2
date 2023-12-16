<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('index_jobs')) {
            Schema::create('index_jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('uuid')->unique();
                $table->string('queue')->index();
                $table->string('title', 191)->nullable();
                $table->longText('description')->nullable();
                $table->string('group', 191)->nullable();
                $table->string('icon', 191)->nullable();
                $table->string('level', 191)->nullable();
                $table->json('callback')->nullable();
                $table->float('time_execution', 14, 2)->default(0);
                $table->float('memory_usage', 14, 2)->default(0);
                $table->string('status', 191)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
        if (!Schema::hasTable('processed_jobs')) {
            Schema::create('processed_jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('connection');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->timestamp('processed_at')->useCurrent();
            });
        }

    }

    public function down()
    {
        Schema::dropIfExists('processed_jobs');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('index_jobs');
    }
};