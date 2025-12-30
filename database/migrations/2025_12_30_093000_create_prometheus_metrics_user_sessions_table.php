<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('prometheus_metrics_user_sessions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id', 255)->unique();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
            $table->index('last_activity_at');
            $table->index('created_at');

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prometheus_metrics_user_sessions');
    }
};
