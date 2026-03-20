<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('reconciled')->default(false)->after('donation_date');
            $table->timestamp('reconciled_at')->nullable()->after('reconciled');
            $table->foreignId('reconciled_by')->nullable()->after('reconciled_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->boolean('reconciled')->default(false)->after('expense_date');
            $table->timestamp('reconciled_at')->nullable()->after('reconciled');
            $table->foreignId('reconciled_by')->nullable()->after('reconciled_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['reconciled_by']);
            $table->dropColumn(['reconciled', 'reconciled_at', 'reconciled_by']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['reconciled_by']);
            $table->dropColumn(['reconciled', 'reconciled_at', 'reconciled_by']);
        });
    }
};
