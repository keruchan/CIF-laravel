<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_users', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('username', 150);
            $table->string('name', 150);
            $table->string('department_name', 150)->default('');
            $table->string('position', 150)->default('');
            $table->string('password_hash', 255);
            $table->dateTime('created_at')->useCurrent();
            $table->string('group_name', 150)->default('');
            $table->string('account_status', 20)->default('Active');
            $table->dateTime('archived_at')->nullable();
            $table->bigInteger('access_version')->unsigned()->default(0);
            $table->bigInteger('revision')->unsigned()->default(0);

            $table->unique(['username'], 'username');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_users');
    }
};
