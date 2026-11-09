<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_tracking_settings', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->tinyInteger('id')->unsigned();
            $table->dateTime('started_at');

            $table->primary(['id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_tracking_settings');
    }
};
