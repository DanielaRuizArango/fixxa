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
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('address', 255)->nullable()->after('phone');
            $table->string('cedula', 20)->nullable()->unique()->after('address');
            $table->text('experience')->nullable()->after('cedula');
            $table->string('title', 255)->nullable()->after('experience');
            $table->string('image')->nullable()->after('title');
            $table->enum('role', ['client', 'technician', 'admin'])->default('client')->after('image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address', 'cedula', 'experience', 'title', 'image', 'role']);
        });
    }
};

