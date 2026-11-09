<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('email', 254);
            $table->char('code_hash', 64)->nullable();
            $table->string('access_code', 8)->nullable();
            $table->string('position', 150)->nullable();
            $table->bigInteger('created_by')->unsigned();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('redeemed_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->bigInteger('archived_by')->unsigned()->nullable();
            $table->string('applicant_id', 32)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->longText('draft_form')->nullable();
            $table->tinyInteger('draft_step')->unsigned()->default(0);
            $table->bigInteger('draft_revision')->unsigned()->default(0);
            $table->dateTime('draft_saved_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->tinyInteger('access_enabled')->default(1);
            $table->bigInteger('access_version')->unsigned()->default(0);

            $table->unique(['code_hash'], 'code_hash');
            $table->index(['created_by'], 'fk_invitation_admin');
            $table->index(['email'], 'idx_invitation_email');
            $table->unique(['applicant_id'], 'uq_invitation_applicant_id');

            $table->foreign(['created_by'], 'fk_invitation_admin')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
