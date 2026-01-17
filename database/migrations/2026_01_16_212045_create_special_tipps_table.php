<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_tipps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('special_tipp_spec_id')->constrained('special_tipp_specs')->cascadeOnDelete();
            $table->foreignId('predicted_team_id')->nullable()->constrained('teams');
            $table->unsignedSmallInteger('predicted_value')->nullable();
            $table->unsignedSmallInteger('score')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'special_tipp_spec_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_tipps');
    }
};
