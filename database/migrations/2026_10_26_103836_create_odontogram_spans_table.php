<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hallazgos que abarcan varias piezas (NTS N° 188-MINSA/DGIESP-2022): prótesis, aparatos
 * ortodónticos, edéntulo total, diastema, fusión, supernumeraria y transposición.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tooth_conditions', function (Blueprint $table) {
            $table->string('target', 10)->change(); // face, tooth, both o span (varias piezas)
        });

        Schema::create('odontogram_spans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odontogram_id')->constrained()->cascadeOnDelete();
            $table->foreignId('condition_id')->constrained('tooth_conditions')->cascadeOnDelete();
            $table->unsignedTinyInteger('start_fdi_code');
            $table->unsignedTinyInteger('end_fdi_code');
            $table->enum('state', ['bueno', 'malo']);
            $table->timestamps();

            $table->foreign('start_fdi_code')->references('fdi_code')->on('tooth_definitions');
            $table->foreign('end_fdi_code')->references('fdi_code')->on('tooth_definitions');
            $table->unique(['odontogram_id', 'condition_id', 'start_fdi_code', 'end_fdi_code'], 'odontogram_spans_zone_unique');
        });

        // En un hallazgo de varias piezas, fdi_code es la pieza inicial y end_fdi_code la final.
        Schema::table('odontogram_treatment_log', function (Blueprint $table) {
            $table->unsignedTinyInteger('end_fdi_code')->nullable()->after('fdi_code');
            $table->foreign('end_fdi_code')->references('fdi_code')->on('tooth_definitions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('odontogram_treatment_log', function (Blueprint $table) {
            $table->dropForeign(['end_fdi_code']);
            $table->dropColumn('end_fdi_code');
        });

        Schema::dropIfExists('odontogram_spans');
    }
};
