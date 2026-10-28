<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('school_terms', function (Blueprint $table) {
            $table->id();
            $table->string('school_year', 9);
            $table->string('semester', 20);
            $table->timestamps();
            $table->unique(['school_year', 'semester']);
        });

        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('discount_amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('student_number', 50)->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('year_level', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_term_id')->constrained()->restrictOnDelete();
            $table->foreignId('scholarship_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scholarship_name')->nullable();
            $table->decimal('total_fees', 10, 2);
            $table->decimal('scholarship_discount', 10, 2)->default(0);
            $table->decimal('net_amount_due', 10, 2);
            $table->date('billing_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['student_profile_id', 'school_term_id']);
        });

        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_amount', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->dateTime('paid_at');
            $table->string('reference', 100)->nullable()->unique();
            $table->string('method', 50)->default('Cash');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['bill_id', 'paid_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('student_profiles');
        Schema::dropIfExists('scholarships');
        Schema::dropIfExists('fee_types');
        Schema::dropIfExists('school_terms');
        Schema::dropIfExists('courses');
    }
};
