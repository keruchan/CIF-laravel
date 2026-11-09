<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_email_attempts', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigIncrements('id');
            $table->bigInteger('message_id')->unsigned();
            $table->enum('status', ['Sent', 'Failed']);
            $table->string('provider_message_id', 255)->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['message_id'], 'message_id');

            $table->foreign(['message_id'], 'hr_email_attempts_ibfk_1')->references(['id'])->on('hr_email_outbox')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_email_attempts');
    }
};
