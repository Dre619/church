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
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('discount_ends_at');
            $table->unsignedSmallInteger('discount_max_organizations')->nullable()->after('discount_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('discount_max_organizations');
            $table->timestamp('discount_ends_at')->nullable()->after('discount_percentage');
        });
    }
};
