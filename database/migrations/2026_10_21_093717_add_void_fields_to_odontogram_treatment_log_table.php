<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite anular un registro de la bitácora devolviendo la zona a su estado anterior:
     * `action` y `previous_condition_id` son null en los registros anteriores a este cambio.
     */
    public function up(): void
    {
        Schema::table('odontogram_treatment_log', function (Blueprint $table): void {
            $table->enum('action', ['apply', 'clear', 'observe'])->nullable()->after('face');
            $table->foreignId('previous_condition_id')->nullable()->after('condition_id')
                ->constrained('tooth_conditions')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('registered_at');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('odontogram_treatment_log', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn('voided_at');
            $table->dropConstrainedForeignId('previous_condition_id');
            $table->dropColumn('action');
        });
    }
};
