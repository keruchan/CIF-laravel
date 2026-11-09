<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_email_archives', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigInteger('message_id')->unsigned();
            $table->bigInteger('archived_by')->unsigned();
            $table->dateTime('archived_at');

            $table->primary(['message_id']);
            $table->index(['archived_at'], 'archived_at');
            $table->index(['archived_by'], 'archived_by');

            $table->foreign(['message_id'], 'hr_email_archives_ibfk_1')->references(['id'])->on('hr_email_outbox')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['archived_by'], 'hr_email_archives_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_email_archives');
    }
};
