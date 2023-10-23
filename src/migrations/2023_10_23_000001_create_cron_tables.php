<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cron', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('description', 254)->nullable();
            $table->string('method', 15)->nullable(); // exec, command
            // example:
            // command: emails:send Taylor --force
            // exec: node /home/forge/script.js
            $table->string('command', 254)->nullable();
            $table->string('minute', 15)->default('*');
            $table->string('hour', 15)->default('*');
            $table->string('day', 15)->default('*');
            $table->string('month', 15)->default('*');
            $table->string('day_of_week', 15)->default('*');
            $table->string('timezone', 40)->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status', 15)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cron_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cron_id');
            $table->foreign('cron_id')->references('id')->on('cron');
            $table->timestamp('run_at')->nullable();
            $table->string('run_status', 15)->nullable();
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
        
    }

    public function down()
    {
        Schema::dropIfExists('cron_log');
        Schema::dropIfExists('cron');
    }
};