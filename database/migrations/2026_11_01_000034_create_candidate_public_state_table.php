<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_public_state', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigInteger('candidate_id')->unsigned();
            $table->longText('state_json');
            $table->bigInteger('revision')->unsigned()->default(0);
            $table->bigInteger('published_by')->unsigned()->nullable();
            $table->dateTime('published_at')->nullable();

            $table->primary(['candidate_id']);
            $table->index(['published_by'], 'published_by');

            $table->foreign(['candidate_id'], 'candidate_public_state_ibfk_1')->references(['id'])->on('candidates')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['published_by'], 'candidate_public_state_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_public_state');
    }
};
