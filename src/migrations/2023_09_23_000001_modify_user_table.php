<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if ( !Schema::hasColumn('users', 'uuid')) {
                $table->uuid('uuid')->after('id');
            }
            // la columna environment se agrega para poder diferenciar los usuarios de los diferentes
            // ambientes, por ejemplo: destefano, marmolero, tienda
            if ( !Schema::hasColumn('users', 'environment')) {
                $table->string('environment')->after('email_verified_at')->default(''); // destefano, marmolero, tienda
            }
            // softDeletes
            if ( !Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        $groupsTable = config('acl.tables.groups', 'groups');

        Schema::table($groupsTable, function (Blueprint $table) use ($groupsTable) {
            if ( !Schema::hasColumn($groupsTable, 'uuid')) {
                $table->uuid('uuid')->after('id');
            }
            if ( !Schema::hasColumn($groupsTable, 'environment')) {
                $table->string('environment')->after('name')->default(''); // destefano, marmolero, tienda
            }
            if ( !Schema::hasColumn($groupsTable, 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->after('description')->nullable();
            }
            if ( !Schema::hasColumn($groupsTable, 'deleted_at')) {
                $table->softDeletes();
            }
        });

        $permissionsTable = config('acl.tables.permissions', 'permissions');

        Schema::table($permissionsTable, function (Blueprint $table) use ($permissionsTable) {
            if ( !Schema::hasColumn($permissionsTable, 'uuid')) {
                $table->uuid('uuid')->after('id');
            }
            if ( !Schema::hasColumn($permissionsTable, 'environment')) {
                $table->string('environment')->after('name')->default(''); // destefano, marmolero, tienda
            }
            if ( !Schema::hasColumn($permissionsTable, 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::create('work_environment', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key', 200)->unique();
            $table->string('name', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = ['uuid', 'environment', 'deleted_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        $groupsTable = config('acl.tables.groups', 'groups');
        Schema::table($groupsTable, function (Blueprint $table) use ($groupsTable) {
            $columns = ['uuid', 'environment', 'parent_id', 'deleted_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn($groupsTable, $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        $permissionsTable = config('acl.tables.permissions', 'permissions');
        Schema::table($permissionsTable, function (Blueprint $table) use ($permissionsTable) {
            $columns = ['uuid', 'environment', 'deleted_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn($permissionsTable, $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('work_environment');
    }
};