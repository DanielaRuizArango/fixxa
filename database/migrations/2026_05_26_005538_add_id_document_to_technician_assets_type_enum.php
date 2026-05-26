<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega 'id_document' al enum de la columna 'type' en technician_assets.
     */
    public function up(): void
    {
        // MySQL: ALTER COLUMN para extender el enum
        DB::statement("ALTER TABLE technician_assets MODIFY COLUMN `type` ENUM('tool', 'certification', 'work', 'id_document') NOT NULL");
    }

    /**
     * Revertir: eliminar 'id_document' del enum.
     */
    public function down(): void
    {
        // Primero eliminar los registros con tipo id_document para no violar la constraint
        DB::table('technician_assets')->where('type', 'id_document')->delete();

        DB::statement("ALTER TABLE technician_assets MODIFY COLUMN `type` ENUM('tool', 'certification', 'work') NOT NULL");
    }
};
