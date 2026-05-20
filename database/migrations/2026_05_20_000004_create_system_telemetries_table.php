<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_telemetries', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->float('cpu_load')->default(0);
            $table->float('memory_usage')->default(0); // in MB
            $table->float('disk_usage')->default(0); // in %
            $table->unsignedInteger('pending_jobs')->default(0);
            $table->float('response_time')->default(0); // in seconds
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_telemetries');
    }
};
