<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bir_settings', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });

        Schema::table('invoice_sequences', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['document_type', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_sequences', function (Blueprint $table) {
            $table->dropUnique(['document_type', 'branch_id']);
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('bir_settings', function (Blueprint $table) {
            $table->dropUnique(['branch_id']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
