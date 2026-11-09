<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('email', 254);
            $table->string('name', 150);
            $table->string('password_hash', 255);
            $table->dateTime('created_at')->useCurrent();
            $table->string('role', 20)->default('hr');
            $table->string('account_status', 20)->default('Active');
            $table->bigInteger('access_version')->unsigned()->default(0);
            $table->bigInteger('revision')->unsigned()->default(0);

            $table->unique(['email'], 'email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
