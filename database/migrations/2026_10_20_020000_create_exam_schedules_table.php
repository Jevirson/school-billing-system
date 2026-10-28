<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_term_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('year_level', 30)->nullable();
            $table->string('exam_period', 30);
            $table->date('exam_date');
            $table->string('scope_key', 100);
            $table->timestamps();
            $table->unique(['school_term_id', 'exam_period', 'scope_key'], 'exam_schedules_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_schedules');
    }
};
