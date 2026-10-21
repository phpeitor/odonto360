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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('document_number')->nullable()->unique()->after('name');
            $table->enum('sex', ['Masculino', 'Femenino', 'Otro'])->nullable()->after('document_number');
            $table->string('phone')->nullable()->after('sex');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['document_number']);
            $table->dropColumn(['document_number', 'sex', 'phone']);
        });
    }
};
