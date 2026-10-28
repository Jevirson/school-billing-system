<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropColumn('subject_fee');
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn('subject_fee');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->decimal('subject_fee', 10, 2)->default(0);
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->decimal('subject_fee', 10, 2)->default(0);
        });
    }
};
