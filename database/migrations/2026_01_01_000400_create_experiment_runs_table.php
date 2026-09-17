<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiment_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id')->unique();
            $table->string('tracking_mode')->nullable()->index();
            $table->string('blocking_mode')->nullable()->index();
            $table->string('privacy_mode')->nullable()->index();
            $table->string('consent_mode')->nullable()->index();
            $table->string('browser')->nullable();
            $table->string('browser_version')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiment_runs');
    }
};
