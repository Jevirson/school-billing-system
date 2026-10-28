<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarship_student_profile', function (Blueprint $table) {
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scholarship_id')->constrained()->cascadeOnDelete();
            $table->primary(['student_profile_id', 'scholarship_id']);
        });

        DB::table('student_profiles')
            ->whereNotNull('scholarship_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $student): void {
                DB::table('scholarship_student_profile')->insert([
                    'student_profile_id' => $student->id,
                    'scholarship_id' => $student->scholarship_id,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarship_student_profile');
    }
};
