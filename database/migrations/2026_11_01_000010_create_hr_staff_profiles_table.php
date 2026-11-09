<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_staff_profiles', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigInteger('admin_id')->unsigned();
            $table->mediumText('photo');
            $table->dateTime('updated_at');

            $table->primary(['admin_id']);

            $table->foreign(['admin_id'], 'hr_staff_profiles_ibfk_1')->references(['id'])->on('admins')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_staff_profiles');
    }
};
