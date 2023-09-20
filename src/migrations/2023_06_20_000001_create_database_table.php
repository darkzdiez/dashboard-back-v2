<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // lo que se quiere es poder categorizar las tablas de la base de datos, para poder realizar
        // acciones en conjunto, como por ejemplo, eliminar todas las tablas de una categoría
        if (!Schema::hasTable('table_groups')) {
            Schema::create('table_groups', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 200)->nullable();
                $table->string('description', 255)->nullable();
                $table->json('tables')->nullable();
                $table->timestamps();
            });
        }

    }

    public function down()
    {
        Schema::dropIfExists('table_groups');
    }
};