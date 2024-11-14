<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('current_url')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::table('notes', function (Blueprint $table) {
            $cols = [
                'current_url',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('notes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};