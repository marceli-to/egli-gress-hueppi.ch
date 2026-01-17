<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_tipp_specs', function (Blueprint $table) {
            $table->unsignedSmallInteger('result_value')->nullable()->after('team_id');
            $table->string('result_ranking')->nullable()->after('result_value');
        });
    }

    public function down(): void
    {
        Schema::table('special_tipp_specs', function (Blueprint $table) {
            $table->dropColumn(['result_value', 'result_ranking']);
        });
    }
};
