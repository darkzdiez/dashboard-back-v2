<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('index_jobs', function (Blueprint $table) {
            if ( !Schema::hasColumn('index_jobs', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('status');
            }
            if ( !Schema::hasColumn('index_jobs', 'finished_at')) {
                $table->timestamp('finished_at')->nullable()->after('started_at');
            }
        });
    }

    public function down()
    {
        Schema::table('index_jobs', function (Blueprint $table) {
            $table->dropColumn('started_at');
            $table->dropColumn('finished_at');
        });
    }
};