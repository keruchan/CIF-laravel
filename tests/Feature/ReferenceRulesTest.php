<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReferenceRulesTest extends TestCase
{
    public static function referenceChecks(): array
    {
        $checks = [];
        foreach (glob(dirname(__DIR__).'/Reference/*.php') as $file) {
            $checks[basename($file, '.php')] = [$file];
        }

        return $checks;
    }

    #[DataProvider('referenceChecks')]
    public function test_ported_rules_against_the_current_reference_checks(string $file): void
    {
        $arguments = [PHP_BINARY, $file];
        $directory = null;
        if (basename($file) === 'mrf-document.php') {
            $directory = sys_get_temp_dir().'/cif-mrf-import-test-'.bin2hex(random_bytes(8));
            mkdir($directory);
            $arguments[] = $directory;
        }
        try {
            $process = new Process($arguments, base_path(), ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'MAIL_MAILER' => 'array']);
            $process->setTimeout(30)->run();
            $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
            $this->assertStringContainsString('PASS', $process->getOutput());
        } finally {
            if ($directory) {
                foreach (glob($directory.'/*') as $fixture) {
                    unlink($fixture);
                }
                rmdir($directory);
            }
        }
    }
}
