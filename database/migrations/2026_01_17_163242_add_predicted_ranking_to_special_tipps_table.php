<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('special_tipps', function (Blueprint $table) {
            $table->string('predicted_ranking')->nullable()->after('predicted_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('special_tipps', function (Blueprint $table) {
            $table->dropColumn('predicted_ranking');
        });
    }
};
