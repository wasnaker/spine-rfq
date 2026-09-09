<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Kunci AUTO_INCREMENT rfqs ke start number (1011027) — hanya bila
        // counter turun di bawahnya (rebuild/restart InnoDB bisa me-reset ke
        // max+1); jangan pernah menurunkan counter yang sudah lewat.
        $create = DB::selectOne('SHOW CREATE TABLE rfqs')->{'Create Table'};
        if (preg_match('/AUTO_INCREMENT=(\d+)/', $create, $m) && (int) $m[1] < 1011027) {
            DB::statement('ALTER TABLE rfqs AUTO_INCREMENT = 1011027');
        }
    }

    public function down(): void
    {
        // tidak perlu kembalikan — AUTO_INCREMENT hanya titik awal.
    }
};
