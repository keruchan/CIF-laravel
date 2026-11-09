<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->dateTime('created_at', 6);
            $table->string('actor_role', 20);
            $table->bigInteger('actor_id')->unsigned()->nullable();
            $table->string('actor_name', 255);
            $table->string('category', 32);
            $table->string('action', 80);
            $table->string('outcome', 20)->default('Success');
            $table->string('entity_type', 64);
            $table->string('entity_id', 100);
            $table->string('entity_label', 255);
            $table->longText('before_json')->nullable();
            $table->longText('after_json')->nullable();
            $table->string('request_id', 32)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('source', 20)->default('Live');
            $table->string('legacy_key', 120)->nullable();
            $table->tinyInteger('references_recorded')->default(0);
            $table->bigInteger('previous_mrf_id')->unsigned()->nullable();
            $table->bigInteger('mrf_id')->unsigned()->nullable();
            $table->string('mrf_number', 40)->nullable();
            $table->string('mrf_batch_number', 32)->nullable();
            $table->bigInteger('candidate_id')->unsigned()->nullable();
            $table->string('candidate_number', 32)->nullable();
            $table->bigInteger('invitation_id')->unsigned()->nullable();

            $table->index(['actor_role', 'actor_id', 'id'], 'audit_actor');
            $table->index(['candidate_id', 'id'], 'audit_candidate');
            $table->index(['category', 'id'], 'audit_category');
            $table->index(['created_at', 'id'], 'audit_dates');
            $table->index(['entity_type', 'entity_id', 'id'], 'audit_entity');
            $table->index(['invitation_id', 'id'], 'audit_invitation');
            $table->index(['mrf_id', 'id'], 'audit_mrf');
            $table->unique(['legacy_key'], 'legacy_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
