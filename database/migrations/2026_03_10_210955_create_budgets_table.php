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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');              // e.g. "Tithe Budget", "Rent Budget"
            $table->decimal('amount', 15, 2);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month')->nullable(); // null = full-year budget
            $table->timestamps();

            $table->index(['organization_id', 'type', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
