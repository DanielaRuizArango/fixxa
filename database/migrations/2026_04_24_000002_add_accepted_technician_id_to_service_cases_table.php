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
        Schema::table('service_cases', function (Blueprint $table) {
            $table->unsignedBigInteger('accepted_technician_id')->nullable()->after('status');
            $table->foreign('accepted_technician_id')->references('id')->on('technicians')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_cases', function (Blueprint $table) {
            $table->dropForeign(['accepted_technician_id']);
            $table->dropColumn('accepted_technician_id');
        });
    }
};
