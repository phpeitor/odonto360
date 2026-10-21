<?php

use App\Domain\Odontogram\TreatmentLogBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Datos únicamente (sin DDL): clasifica los registros anteriores a las reversas y revierte los que
     * ya estaban anulados.
     */
    public function up(): void
    {
        (new TreatmentLogBackfill)->run();
    }

    public function down(): void
    {
        // No se deshace: solo completa datos que antes estaban vacíos.
    }
};
