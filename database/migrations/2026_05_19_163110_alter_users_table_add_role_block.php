<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'block_worker'])->default('admin')->after('password');
            $table->unsignedInteger('block_id')->nullable()->after('role');
            $table->foreign('block_id')->references('id')->on('blocks')->nullOnDelete();
        });

        DB::statement("UPDATE users SET role = 'admin' WHERE role IS NULL");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['block_id']);
            $table->dropColumn('block_id');
            $table->dropColumn('role');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('event_creator');
        });
    }
};
