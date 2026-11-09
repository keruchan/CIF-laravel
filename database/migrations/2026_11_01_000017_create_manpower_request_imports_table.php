<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manpower_request_imports', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigInteger('request_id')->unsigned();
            $table->bigInteger('admin_id')->unsigned();
            $table->string('original_filename', 255);
            $table->string('mime_type', 150);
            $table->integer('file_size')->unsigned();
            $table->char('file_sha256', 64);
            $table->rawColumn('original_file', 'mediumblob');
            $table->mediumText('extracted_text');
            $table->dateTime('imported_at');

            $table->primary(['request_id']);
            $table->unique(['file_sha256'], 'file_sha256');
            $table->index(['admin_id'], 'fk_mrf_import_admin');

            $table->foreign(['admin_id'], 'fk_mrf_import_admin')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['request_id'], 'fk_mrf_import_request')->references(['id'])->on('manpower_requests')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manpower_request_imports');
    }
};
