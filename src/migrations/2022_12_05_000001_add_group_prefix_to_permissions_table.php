<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGroupPrefixToPermissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionsTable = config('acl.tables.permissions', 'permissions');
        Schema::table($permissionsTable, function (Blueprint $table) {
            $table->string('group_prefix')->nullable()->index();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $permissionsTable = config('acl.tables.permissions', 'permissions');
        Schema::table($permissionsTable, function (Blueprint $table) use ($permissionsTable) {
            if (Schema::hasColumn($permissionsTable, 'group_prefix')) {
                $table->dropColumn('group_prefix');
            }
        });
    }
};
