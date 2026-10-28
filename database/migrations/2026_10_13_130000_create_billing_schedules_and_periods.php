<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_schedules')) {
            Schema::create('billing_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_term_id')->constrained()->restrictOnDelete();
                $table->foreignId('course_id')->nullable()->constrained()->restrictOnDelete();
                $table->string('year_level', 30)->nullable();
                $table->string('billing_period', 30);
                $table->date('due_date');
                $table->string('scope_key', 100);
                $table->timestamps();
                $table->unique(['school_term_id', 'billing_period', 'scope_key'], 'billing_schedules_scope_unique');
            });
        }

        Schema::table('bills', function (Blueprint $table) {
            $table->string('billing_period', 30)->default('Full Term')->after('school_term_id');
            $table->date('due_date')->nullable()->after('billing_date');
            $table->foreignId('billing_schedule_id')->nullable()->after('due_date')->constrained()->nullOnDelete();
            $table->unique(
                ['student_profile_id', 'school_term_id', 'billing_period'],
                'bills_student_term_period_unique'
            );
            $table->dropUnique('bills_student_profile_id_school_term_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropUnique('bills_student_term_period_unique');
            $table->dropConstrainedForeignId('billing_schedule_id');
            $table->dropColumn(['billing_period', 'due_date']);
            $table->unique(['student_profile_id', 'school_term_id']);
        });

        Schema::dropIfExists('billing_schedules');
    }
};
