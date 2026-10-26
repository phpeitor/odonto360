<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomenclatura de la NTS N° 188-MINSA/DGIESP-2022: cada hallazgo se registra en buen estado (azul)
 * o mal estado (rojo), con la sigla de su recuadro, y una pieza puede tener varios hallazgos.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tooth_conditions', function (Blueprint $table) {
            $table->string('category', 30)->default('patologia')->change();
            $table->string('allowed_states', 10)->default('ambos')->after('category'); // bueno, malo o ambos
            $table->json('abbreviations')->nullable()->after('allowed_states');        // siglas del recuadro
            $table->string('glyph', 30)->nullable()->after('abbreviations');           // dibujo sobre la pieza
        });

        Schema::create('odontogram_tooth_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odontogram_tooth_id')->constrained('odontogram_teeth')->cascadeOnDelete();
            $table->foreignId('condition_id')->constrained('tooth_conditions')->cascadeOnDelete();
            $table->string('abbreviation', 10)->nullable();
            $table->enum('state', ['bueno', 'malo']);
            $table->timestamps();

            // Nombre explícito: el generado pasa de 64 caracteres, el límite de MySQL.
            $table->unique(['odontogram_tooth_id', 'condition_id'], 'tooth_findings_tooth_condition_unique');
        });

        Schema::table('odontogram_tooth_faces', function (Blueprint $table) {
            $table->string('abbreviation', 10)->nullable()->after('condition_id');
            $table->enum('state', ['bueno', 'malo'])->nullable()->after('abbreviation');
        });

        Schema::table('odontogram_treatment_log', function (Blueprint $table) {
            $table->string('abbreviation', 10)->nullable()->after('condition_id');
            $table->enum('state', ['bueno', 'malo'])->nullable()->after('abbreviation');
        });

        Schema::table('odontograms', function (Blueprint $table) {
            $table->text('specifications')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('odontograms', function (Blueprint $table) {
            $table->dropColumn('specifications');
        });

        Schema::table('odontogram_treatment_log', function (Blueprint $table) {
            $table->dropColumn(['abbreviation', 'state']);
        });

        Schema::table('odontogram_tooth_faces', function (Blueprint $table) {
            $table->dropColumn(['abbreviation', 'state']);
        });

        Schema::dropIfExists('odontogram_tooth_findings');

        Schema::table('tooth_conditions', function (Blueprint $table) {
            $table->dropColumn(['allowed_states', 'abbreviations', 'glyph']);
        });
    }
};
