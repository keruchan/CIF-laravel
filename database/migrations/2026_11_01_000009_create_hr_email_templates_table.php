<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_email_templates', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigIncrements('id');
            $table->string('slug', 80);
            $table->string('name', 150);
            $table->string('category', 32);
            $table->string('subject', 200);
            $table->text('body');
            $table->boolean('archived')->default(0);
            $table->bigInteger('revision')->unsigned()->default(0);
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();

            $table->unique(['slug'], 'slug');
            $table->index(['updated_by'], 'updated_by');

            $table->foreign(['updated_by'], 'hr_email_templates_ibfk_1')->references(['id'])->on('admins')->onDelete('set null')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_email_templates');
    }
};
