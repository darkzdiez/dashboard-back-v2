<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('news', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('title', 254)->nullable();
            $table->mediumText('description_short')->nullable();
            $table->longText('description')->nullable();
            $table->string('image', 254)->nullable();
            $table->string('url', 254)->nullable();
            $table->string('icon', 254)->nullable();
            $table->boolean('featured')->default(false);
            $table->bigInteger('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users');
            
            $table->softDeletes();
            $table->timestamps();
        });        
    }

    public function down()
    {
        Schema::dropIfExists('news');
    }
};