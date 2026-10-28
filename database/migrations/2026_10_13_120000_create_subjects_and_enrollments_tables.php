<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->decimal('units', 4, 1);
            $table->decimal('subject_fee', 10, 2);
            $table->string('year_level', 30)->nullable();
            $table->string('semester', 20)->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'code']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_term_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('units', 4, 1);
            $table->decimal('subject_fee', 10, 2);
            $table->timestamps();
            $table->unique(['student_profile_id', 'subject_id', 'school_term_id'], 'enrollments_student_subject_term_unique');
            $table->index(['student_profile_id', 'school_term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('subjects');
    }
};
