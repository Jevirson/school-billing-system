<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promissory_letters', function (Blueprint $table) {
            $table->decimal('requested_amount', 10, 2)->default(0)->after('outstanding_amount');
        });

        DB::table('promissory_letters')->update([
            'requested_amount' => DB::raw('outstanding_amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('promissory_letters', function (Blueprint $table) {
            $table->dropColumn('requested_amount');
        });
    }
};
