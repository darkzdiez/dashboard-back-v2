<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // php artisan migrate:rollback --path=database/migrations/2023_05_31_000001_create_notes_table.php
        if (!Schema::hasTable('notes')) {
            Schema::create('notes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('area')  ->nullable();
                $table->string('refid') ->nullable();
                $table->string('title') ->nullable();
                $table->text('content') ->nullable();
                $table->string('level')->nullable(); // info, warning, danger, success, primary, secondary, light, dark
                $table->string('type')->nullable(); // note, task, reminder, alert
                $table->unsignedBigInteger('user_id')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('notes_meta')) {
            Schema::create('notes_meta', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('key')   ->nullable();
                $table->string('value') ->nullable();
                $table->unsignedBigInteger('note_id')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('notes_meta');
    }
};