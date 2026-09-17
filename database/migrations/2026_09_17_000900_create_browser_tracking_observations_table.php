<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('browser_tracking_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ground_truth_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32)->index();
            $table->string('layer', 32)->index();
            $table->string('resource_kind', 32)->index();
            $table->string('canonical_event_name')->nullable()->index();
            $table->string('provider_event_name')->nullable();
            $table->string('outcome', 32)->index();
            $table->string('correlation_method', 64)->nullable();
            $table->string('request_method', 16)->nullable();
            $table->string('request_host')->nullable();
            $table->string('request_path')->nullable();
            $table->string('request_fingerprint', 64)->nullable()->index();
            $table->timestamp('observed_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('failure_text', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['experiment_run_id', 'ground_truth_event_id'], 'browser_obs_run_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('browser_tracking_observations');
    }
};
