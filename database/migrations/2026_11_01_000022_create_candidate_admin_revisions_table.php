<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_admin_revisions', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigIncrements('id');
            $table->bigInteger('candidate_id')->unsigned();
            $table->bigInteger('admin_id')->unsigned();
            $table->longText('form_data');
            $table->longText('hr_data');
            $table->dateTime('created_at')->useCurrent();

            $table->index(['admin_id'], 'admin_id');
            $table->index(['candidate_id', 'id'], 'idx_candidate_revision');

            $table->foreign(['candidate_id'], 'candidate_admin_revisions_ibfk_1')->references(['id'])->on('candidates')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign(['admin_id'], 'candidate_admin_revisions_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_admin_revisions');
    }
};
