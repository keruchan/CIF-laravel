<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_attempts', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->char('bucket_key', 64);
            $table->integer('attempts')->unsigned()->default(0);
            $table->dateTime('window_started');

            $table->primary(['bucket_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_attempts');
    }
};
