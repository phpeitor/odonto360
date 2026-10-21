<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quién registró cada cambio en la bitácora (null en los registros anteriores).
     */
    public function up(): void
    {
        Schema::table('odontogram_treatment_log', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('observation')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('odontogram_treatment_log', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
