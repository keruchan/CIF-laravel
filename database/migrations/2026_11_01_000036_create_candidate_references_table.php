<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_references', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigInteger('candidate_id')->unsigned();
            $table->tinyInteger('record_number')->unsigned();
            $table->text('name')->nullable();
            $table->text('company_position')->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->text('relationship')->nullable();

            $table->primary(['candidate_id', 'record_number']);

            $table->foreign(['candidate_id'], 'candidate_references_ibfk_1')->references(['id'])->on('candidates')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_references');
    }
};
