<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_details', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->bigInteger('candidate_id')->unsigned();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->text('applied_via')->nullable();
            $table->string('fn', 100)->nullable();
            $table->string('mn', 100)->nullable();
            $table->string('ln', 100)->nullable();
            $table->string('sx', 30)->nullable();
            $table->string('nickname', 100)->nullable();
            $table->text('place_of_birth')->nullable();
            $table->date('dob')->nullable();
            $table->decimal('height_cm', 6, 2)->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->string('religion', 150)->nullable();
            $table->text('cs')->nullable();
            $table->text('sex')->nullable();
            $table->string('nat', 100)->nullable();
            $table->string('mob', 50)->nullable();
            $table->text('adr')->nullable();
            $table->text('permanent_address')->nullable();
            $table->text('city')->nullable();
            $table->text('prov')->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('ecn', 150)->nullable();
            $table->text('emergency_address')->nullable();
            $table->string('ecp', 50)->nullable();
            $table->string('ecr', 150)->nullable();
            $table->text('edu')->nullable();
            $table->text('sch')->nullable();
            $table->text('crs')->nullable();
            $table->text('education_years')->nullable();
            $table->smallInteger('yr')->unsigned()->nullable();
            $table->text('distinctions')->nullable();
            $table->text('licensure_exam')->nullable();
            $table->char('licensure_month', 7)->nullable();
            $table->decimal('board_rating', 6, 2)->nullable();
            $table->text('emp')->nullable();
            $table->text('certification_name')->nullable();
            $table->text('background_check_name')->nullable();
            $table->boolean('certification_accepted');
            $table->boolean('background_consent_accepted');
            $table->dateTime('accepted_at');
            $table->string('form_version', 50);
            $table->string('desired_position', 150)->nullable();
            $table->text('contact_email')->nullable();
            $table->text('facebook_account')->nullable();
            $table->string('sss_number', 50)->nullable();
            $table->string('philhealth_number', 50)->nullable();
            $table->string('pagibig_number', 50)->nullable();
            $table->string('tin_number', 50)->nullable();
            $table->text('blood_type')->nullable();
            $table->mediumText('id_photo')->nullable();

            $table->primary(['candidate_id']);

            $table->foreign(['candidate_id'], 'candidate_details_ibfk_1')->references(['id'])->on('candidates')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_details');
    }
};
