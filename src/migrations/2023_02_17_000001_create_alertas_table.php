<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // tabla de alerts
        if (!Schema::hasTable('alerts')) {
            Schema::create('alerts', function (Blueprint $table) {
                $table->id();
                $table->string('message')->nullable();
                $table->string('priority')->nullable();
                $table->string('type')->nullable();
                $table->string('status')->nullable();
                $table->string('url')->nullable();
                $table->string('icon')->nullable();
                $table->string('color')->nullable();
                $table->bigInteger('user_id')->unsigned()->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        // tabla de alert usuario ( estado de la alert para cada usuario)
        if (!Schema::hasTable('alert_user')) {
            Schema::create('alert_user', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('alert_id')->unsigned()->nullable();
                $table->bigInteger('user_id')->unsigned()->nullable();
                $table->string('status')->nullable(); // read, unread, deleted, etc
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('alert_user');
    }
};