<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_truth_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('experiment_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name')->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedBigInteger('value_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['event_name', 'order_id']);
            $table->index(['experiment_run_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_truth_events');
    }
};
