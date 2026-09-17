<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('server_tracking_dispatches', function (Blueprint $table): void {
            $table->json('request_context')->nullable()->after('request_payload');
        });
    }

    public function down(): void
    {
        Schema::table('server_tracking_dispatches', function (Blueprint $table): void {
            $table->dropColumn('request_context');
        });
    }
};
