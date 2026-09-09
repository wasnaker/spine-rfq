<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Start number RFQ global (tanpa reset tahunan) — pola customer:
        // formatted_number = prefix + EntityCode::encode(id, len).
        DB::statement('ALTER TABLE rfqs AUTO_INCREMENT = 1011027');
    }

    public function down(): void
    {
        // tidak perlu kembalikan — AUTO_INCREMENT hanya titik awal.
    }
};
