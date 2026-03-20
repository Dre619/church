<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_users', function (Blueprint $table) {
            $table->enum('branch_role', ['owner', 'manager', 'member'])->default('member')->after('user_type');
        });

        // Migrate existing data: admin/manager user_type → owner branch_role
        DB::table('organization_users')
            ->whereIn('user_type', ['admin', 'manager'])
            ->update(['branch_role' => 'owner']);
    }

    public function down(): void
    {
        Schema::table('organization_users', function (Blueprint $table) {
            $table->dropColumn('branch_role');
        });
    }
};
