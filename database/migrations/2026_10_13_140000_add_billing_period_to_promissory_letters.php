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
            $table->string('billing_period', 30)->default('Full Term')->after('semester');
        });

        DB::table('promissory_letters')->update([
            'billing_period' => DB::raw('(SELECT billing_period FROM bills WHERE bills.id = promissory_letters.bill_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('promissory_letters', function (Blueprint $table) {
            $table->dropColumn('billing_period');
        });
    }
};
