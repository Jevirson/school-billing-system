<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('reference', 100)->nullable();
            $table->text('student_note')->nullable();
            $table->string('status', 20)->default('Pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['student_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
