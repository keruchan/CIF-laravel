<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mrf_requestor_events', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigIncrements('id');
            $table->bigInteger('requestor_id')->unsigned();
            $table->bigInteger('admin_id')->unsigned()->nullable();
            $table->string('action', 40);
            $table->text('details_json');
            $table->dateTime('created_at');

            $table->index(['admin_id'], 'admin_id');
            $table->index(['requestor_id', 'id'], 'requestor_id');

            $table->foreign(['requestor_id'], 'mrf_requestor_events_ibfk_1')->references(['id'])->on('department_users')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign(['admin_id'], 'mrf_requestor_events_ibfk_2')->references(['id'])->on('admins')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mrf_requestor_events');
    }
};
