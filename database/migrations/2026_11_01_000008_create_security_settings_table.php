<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_settings', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->tinyInteger('id');
            $table->integer('schema_version');
            $table->dateTime('audit_started', 6)->nullable();
            $table->tinyInteger('requestors_superadmin_only')->default(0);

            $table->primary(['id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_settings');
    }
};
