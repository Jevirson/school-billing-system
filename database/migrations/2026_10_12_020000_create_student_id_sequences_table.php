<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_id_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('next_number');
        });

        DB::table('student_id_sequences')->insert([
            'id' => 1,
            'next_number' => 1,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('student_id_sequences');
    }
};
