<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_plans', function (Blueprint $table) {
            $table->integer('months')->nullable()->after('plan_id');
            $table->decimal('amount_paid', 15, 2)->nullable()->after('months');
            $table->integer('discount')->default(0)->after('amount_paid');
            $table->string('status')->default('active')->after('discount');
            $table->string('payment_reference')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('organization_plans', function (Blueprint $table) {
            $table->dropColumn(['months', 'amount_paid', 'discount', 'status', 'payment_reference']);
        });
    }
};
