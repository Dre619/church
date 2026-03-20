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
            $table->string('description')->nullable()->after('slug');
            $table->renameColumn('max_users', 'max_members');
            $table->dropColumn('max_storage');
            $table->boolean('is_trial')->default(false)->after('is_active');
            $table->unsignedSmallInteger('trial_days')->nullable()->after('is_trial');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_trial', 'trial_days']);
            $table->renameColumn('max_members', 'max_users');
            $table->integer('max_storage')->nullable();
        });
    }
};
