<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ground_truth_events', function (Blueprint $table) {
            $table->dropIndex(['event_name', 'order_id']);
            $table->unique(['event_name', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ground_truth_events', function (Blueprint $table) {
            $table->dropUnique(['event_name', 'order_id']);
            $table->index(['event_name', 'order_id']);
        });
    }
};
