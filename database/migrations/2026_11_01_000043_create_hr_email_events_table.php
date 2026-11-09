<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_email_events', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigIncrements('id');
            $table->bigInteger('message_id')->unsigned();
            $table->string('event', 32);
            $table->bigInteger('admin_id')->unsigned();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['admin_id'], 'admin_id');
            $table->index(['message_id'], 'message_id');

            $table->foreign(['message_id'], 'hr_email_events_ibfk_1')->references(['id'])->on('hr_email_outbox')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['admin_id'], 'hr_email_events_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_email_events');
    }
};
