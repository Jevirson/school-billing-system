<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->foreignId('scholarship_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
        });

        Schema::create('promissory_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->string('student_name');
            $table->string('student_number', 50);
            $table->string('course_name')->nullable();
            $table->string('year_level', 30)->nullable();
            $table->string('school_year', 9);
            $table->string('semester', 20);
            $table->string('invoice_number', 40);
            $table->decimal('outstanding_amount', 10, 2);
            $table->text('reason');
            $table->date('commitment_date');
            $table->string('contact_information')->nullable();
            $table->timestamp('submitted_at');
            $table->string('status', 20)->default('Pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('next_school_term_id')->nullable()->constrained('school_terms')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'submitted_at']);
            $table->index(['school_year', 'semester']);
        });

        Schema::create('bill_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promissory_letter_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('source_bill_id')->constrained('bills')->restrictOnDelete();
            $table->foreignId('next_school_term_id')->constrained('school_terms')->restrictOnDelete();
            $table->decimal('amount_at_approval', 10, 2);
            $table->date('approved_until');
            $table->timestamps();
            $table->index(['source_bill_id', 'next_school_term_id']);
        });

        Schema::create('promissory_letter_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promissory_letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['promissory_letter_id', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('promissory_letter_events');
        Schema::dropIfExists('bill_extensions');
        Schema::dropIfExists('promissory_letters');

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scholarship_id');
        });
    }
};
