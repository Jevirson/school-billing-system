<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->timestamp('withdrawn_at')->nullable()->index();
        });

        Schema::create('subject_withdrawal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('Pending');
            $table->text('student_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->decimal('credited_amount', 10, 2)->default(0);
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['student_profile_id', 'created_at']);
        });

        Schema::create('subject_withdrawal_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_withdrawal_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('Pending');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('method', 50)->nullable();
            $table->string('reference', 100)->nullable()->unique();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_withdrawal_refunds');
        Schema::dropIfExists('subject_withdrawal_requests');

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn('withdrawn_at');
        });
    }
};
