<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_id_counters', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->string('prefix', 6);
            $table->bigInteger('last_number')->unsigned()->default(0);

            $table->primary(['prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_id_counters');
    }
};
