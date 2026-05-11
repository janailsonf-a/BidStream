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
    Schema::create('auctions', function (Blueprint $table) {
        $table->id();

        $table->foreignId('created_by')
            ->constrained('users')
            ->cascadeOnDelete();

        $table->foreignId('winner_id')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->string('title');
        $table->text('description')->nullable();

        $table->decimal('starting_price', 10, 2);
        $table->decimal('current_price', 10, 2)->default(0);

        $table->timestamp('starts_at');
        $table->timestamp('ends_at');

        $table->enum('status', ['draft', 'active', 'finished', 'cancelled'])
            ->default('draft');

        $table->timestamps();

        $table->index(['status', 'ends_at']);
        $table->index('created_by');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
