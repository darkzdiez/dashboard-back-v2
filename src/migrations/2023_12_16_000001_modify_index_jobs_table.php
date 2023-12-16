<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('index_jobs', function (Blueprint $table) {
            if ( !Schema::hasColumn('index_jobs', 'queries')) {
                $table->longText('queries')->after('callback')->nullable();
            }
            if ( !Schema::hasColumn('index_jobs', 'queries_time')) {
                $table->float('queries_time', 14, 2)->after('callback')->default(0);
            }
            if ( !Schema::hasColumn('index_jobs', 'queries_count')) {
                $table->integer('queries_count')->after('callback')->default(0);
            }
            // alter time_execution, memory_usage pass 10,2 to 14,2
            \DB::statement("ALTER TABLE `index_jobs` CHANGE `time_execution` `time_execution` DOUBLE(14,2) NOT NULL DEFAULT '0.00'");
            \DB::statement("ALTER TABLE `index_jobs` CHANGE `memory_usage` `memory_usage` DOUBLE(14,2) NOT NULL DEFAULT '0.00'");
        });
    }

    public function down()
    {
        Schema::table('index_jobs', function (Blueprint $table) {
            $columns = ['queries', 'queries_time', 'queries_count'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('index_jobs', $column)) {
                    $table->dropColumn($column);
                }
            }
            // alter time_execution, memory_usage pass 14,2 to 10,2
            \DB::statement("ALTER TABLE `index_jobs` CHANGE `time_execution` `time_execution` DOUBLE(10,2) NOT NULL DEFAULT '0.00'");
            \DB::statement("ALTER TABLE `index_jobs` CHANGE `memory_usage` `memory_usage` DOUBLE(10,2) NOT NULL DEFAULT '0.00'");
        });
    }
};