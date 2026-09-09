<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RFQ hanya list item — tanpa rate/qty/unit/tax.
        Schema::table('rfq_items', function (Blueprint $table) {
            foreach (['qty', 'rate', 'unit', 'tax'] as $col) {
                if (Schema::hasColumn('rfq_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('rfq_items', function (Blueprint $table) {
            if (! Schema::hasColumn('rfq_items', 'qty')) {
                $table->decimal('qty', 12, 2)->default(1);
            }
            if (! Schema::hasColumn('rfq_items', 'rate')) {
                $table->decimal('rate', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('rfq_items', 'unit')) {
                $table->string('unit', 30)->nullable();
            }
            if (! Schema::hasColumn('rfq_items', 'tax')) {
                $table->string('tax', 100)->nullable();
            }
        });
    }
};
