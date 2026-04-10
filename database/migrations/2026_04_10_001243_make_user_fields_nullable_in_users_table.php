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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
            $table->string('city', 50)->nullable()->change();
            $table->string('address', 255)->nullable()->change();
            $table->string('type_id', 20)->nullable()->change();
            $table->string('id_number', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable(false)->change();
            $table->string('city', 50)->nullable(false)->change();
            $table->string('address', 255)->nullable(false)->change();
            $table->string('type_id', 20)->nullable(false)->change();
            $table->string('id_number', 20)->nullable(false)->change();
        });
    }
};
