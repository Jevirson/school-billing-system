<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_extensions', function (Blueprint $table) {
            $table->foreignId('destination_bill_id')
                ->nullable()
                ->after('source_bill_id')
                ->constrained('bills')
                ->nullOnDelete();
            $table->string('next_billing_period', 30)->nullable()->after('next_school_term_id');
            $table->index(
                ['next_school_term_id', 'next_billing_period', 'destination_bill_id'],
                'bill_extensions_destination_period_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('bill_extensions', function (Blueprint $table) {
            $table->dropIndex('bill_extensions_destination_period_index');
            $table->dropConstrainedForeignId('destination_bill_id');
            $table->dropColumn('next_billing_period');
        });
    }
};
