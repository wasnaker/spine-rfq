<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dokumen RFQ (customer -> surveyor).
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->integer('number');
            $table->string('prefix', 50)->default('RFQ-');
            $table->string('formatted_number', 100)->nullable();
            $table->string('hash', 40)->unique();
            $table->date('date');
            $table->date('expirydate')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('surveyor_id')->nullable()->constrained('surveyors')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requestor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft|sent|accepted|declined|expired
            $table->text('terms')->nullable();
            $table->text('clientnote')->nullable();
            $table->text('adminnote')->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->integer('currency')->nullable();
            $table->integer('pipeline_order')->default(0);
            $table->boolean('is_expiry_notified')->default(false);
            $table->string('acceptance_firstname', 50)->nullable();
            $table->string('acceptance_lastname', 50)->nullable();
            $table->string('acceptance_email', 200)->nullable();
            $table->dateTime('acceptance_date')->nullable();
            $table->string('acceptance_ip', 40)->nullable();
            $table->string('signature', 40)->nullable();
            $table->string('short_link', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index(['surveyor_id', 'status']);
            $table->index('formatted_number');
        });

        // Line item RFQ (hanya daftar item — tanpa rate/qty).
        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('equipments')->nullOnDelete();
            $table->text('description');
            $table->text('long_description')->nullable();
            $table->integer('item_order')->default(0);
            $table->timestamps();
        });

        // Equipment milik customer yang di-RFQ-kan (unit dari My Equipment).
        Schema::create('rfq_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('customer_equipment_id')->constrained('customer_equipments')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('equipments')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rfq_id', 'customer_equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_equipment');
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('rfqs');
    }
};
