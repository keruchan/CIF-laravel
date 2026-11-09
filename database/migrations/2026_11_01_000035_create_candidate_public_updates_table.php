<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_public_updates', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigIncrements('id');
            $table->bigInteger('candidate_id')->unsigned();
            $table->string('stage', 40);
            $table->bigInteger('public_revision')->unsigned();
            $table->longText('progress_json');
            $table->bigInteger('email_message_id')->unsigned();
            $table->char('request_key', 64);
            $table->char('request_hash', 64);
            $table->bigInteger('published_by')->unsigned();
            $table->dateTime('published_at');

            $table->index(['candidate_id'], 'candidate_id');
            $table->unique(['email_message_id'], 'email_message_id');
            $table->index(['published_by'], 'published_by');
            $table->unique(['request_key'], 'request_key');

            $table->foreign(['candidate_id'], 'candidate_public_updates_ibfk_1')->references(['id'])->on('candidates')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['published_by'], 'candidate_public_updates_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_public_updates');
    }
};
