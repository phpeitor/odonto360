<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('odontograms', function (Blueprint $table) {
            $table->enum('dentition', ['adult', 'child', 'mixed'])->default('adult')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Los odontogramas mixtos vuelven a infantil: ambos son de pacientes menores de edad.
        DB::table('odontograms')->where('dentition', 'mixed')->update(['dentition' => 'child']);

        Schema::table('odontograms', function (Blueprint $table) {
            $table->enum('dentition', ['adult', 'child'])->default('adult')->change();
        });
    }
};
