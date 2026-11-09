<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_hr_activity', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->bigInteger('candidate_id')->unsigned();
            $table->string('stage', 30);
            $table->string('action', 12);
            $table->bigInteger('admin_id')->unsigned();
            $table->string('decision', 30)->nullable();
            $table->dateTime('created_at');

            $table->index(['candidate_id', 'stage', 'id'], 'hr_activity_candidate');
            $table->index(['admin_id', 'created_at'], 'hr_activity_staff');

            $table->foreign(['candidate_id'], 'candidate_hr_activity_ibfk_1')->references(['id'])->on('candidates')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['admin_id'], 'candidate_hr_activity_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_hr_activity');
    }
};
