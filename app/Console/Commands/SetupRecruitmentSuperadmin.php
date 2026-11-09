<?php

namespace App\Console\Commands;

use App\Services\Recruitment\AuditSetup;
use App\Services\Recruitment\RecruitmentDatabase;
use App\Services\Recruitment\Security;
use Illuminate\Console\Command;
use RuntimeException;

class SetupRecruitmentSuperadmin extends Command
{
    protected $signature = 'recruitment:setup-superadmin {--username=superadmin} {--name=Superadmin} {--credentials-file=} {--password-stdin}';

    protected $description = 'Install additive account/audit support and create a separate first Superadmin without resetting existing credentials';

    public function handle(): int
    {
        if (app()->environment('local', 'testing') && config('database.connections.mysql.database') === 'cif') {
            throw new RuntimeException('Use an isolated Laravel database. The reference is preserved.');
        }
        $pdo = RecruitmentDatabase::pdo();
        Security::cifSecuritySchema($pdo);
        $audit = AuditSetup::cifInstallAuditTriggers($pdo);
        if ($audit['missing']) {
            throw new RuntimeException('Audit setup is incomplete.');
        }
        $imported = AuditSetup::cifImportAuditHistory($pdo);
        $login = strtolower(trim($this->option('username')));
        $name = trim($this->option('name'));
        if (! preg_match('/^[a-z0-9_.@+\-]{3,254}$/D', $login) || $name === '' || mb_strlen($name) > 150) {
            throw new RuntimeException('Choose a valid username and display name.');
        }
        $q = $pdo->prepare('SELECT role FROM admins WHERE email=?');
        $q->execute([$login]);
        $existing = $q->fetchColumn();
        if ($existing !== false) {
            if ($existing !== 'superadmin') {
                throw new RuntimeException('This username belongs to HR. Choose a separate Superadmin username.');
            }
            $this->info('Existing Superadmin preserved; no password was reset.');
        } else {
            $password = $this->option('password-stdin') ? rtrim(stream_get_contents(STDIN), "\r\n") : bin2hex(random_bytes(12)).'!aA7';
            if (mb_strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
                throw new RuntimeException('Use at least 8 characters for the initial password.');
            }
            $file = $this->option('credentials-file') ?: dirname(base_path()).'/cif-superadmin-credentials.txt';
            $parent = realpath(dirname($file));
            if (! $parent) {
                throw new RuntimeException('Choose an existing private directory.');
            }
            $normalized = strtolower(str_replace('\\', '/', $parent).'/');
            foreach ([base_path(), 'C:/xampp/htdocs'] as $root) {
                if (str_starts_with($normalized, strtolower(str_replace('\\', '/', $root).'/'))) {
                    throw new RuntimeException('Store credentials outside application and website directories.');
                }
            }
            $handle = fopen($file, 'x');
            if (! $handle) {
                throw new RuntimeException('Choose a new private credentials file; existing files are preserved.');
            }
            try {
                $pdo->beginTransaction();
                Security::cifAuditContext($pdo, 'System', null, 'Superadmin setup');
                $q = $pdo->prepare("INSERT INTO admins(email,name,password_hash,role) VALUES(?,?,?,'superadmin')");
                $q->execute([$login, $name, password_hash($password, PASSWORD_DEFAULT)]);
                $details = "APEC Homes Superadmin\r\nPage: /pages/superadmin/\r\nUsername: $login\r\nTemporary password: $password\r\n\r\nChange the password after sign-in. Keep this file private.\r\n";
                if (fwrite($handle, $details) !== strlen($details)) {
                    throw new RuntimeException('Could not save private sign-in details.');
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                } throw $e;
            } finally {
                fclose($handle);
            }
            $this->info('Separate Superadmin created. Sign-in details saved privately to: '.$file);
        }
        $this->info('Audit triggers installed: '.$audit['installed'].'. Earlier history imported: '.$imported.'. HR requestor management: retained.');

        return self::SUCCESS;
    }
}
