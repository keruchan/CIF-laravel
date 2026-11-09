<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mrf_counters', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->smallInteger('request_year')->unsigned();
            $table->bigInteger('last_number')->unsigned()->default(0);

            $table->primary(['request_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mrf_counters');
    }
};
