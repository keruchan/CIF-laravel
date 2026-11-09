<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manpower_request_slots', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->bigInteger('request_id')->unsigned();
            $table->smallInteger('slot_number')->unsigned();
            $table->string('reference_number', 50);

            $table->unique(['reference_number'], 'reference_number');
            $table->unique(['request_id', 'slot_number'], 'uq_mrf_slot');

            $table->foreign(['request_id'], 'fk_mrf_slot_request')->references(['id'])->on('manpower_requests')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manpower_request_slots');
    }
};
