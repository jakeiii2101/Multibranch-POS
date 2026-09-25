<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('original_branch_inventory', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->foreignId('branch_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamp('activated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('original_branch_inventory');
    }
};
