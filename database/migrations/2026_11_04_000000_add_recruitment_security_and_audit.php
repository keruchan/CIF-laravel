<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('security_settings')->insertOrIgnore(['id' => 1, 'schema_version' => 3]);

        foreach (require database_path('triggers/2026_11_04_recruitment_audit.php') as $statement) {
            DB::unprepared($statement);
        }

        DB::table('security_settings')->where('id', 1)->update(['audit_started' => DB::raw('UTC_TIMESTAMP(6)')]);
    }

    /**
     * Preserve security and audit history during rollback.
     */
    public function down(): void
    {
        throw new RuntimeException('Security and audit history are preserved. Restore an approved private backup to roll back this schema.');
    }
};
