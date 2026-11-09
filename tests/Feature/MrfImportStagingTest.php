<?php

namespace Tests\Feature;

use App\Services\Recruitment\MrfImportStaging;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MrfImportStagingTest extends TestCase
{
    public function test_large_binary_uploads_survive_json_sessions_without_entering_the_session_payload(): void
    {
        Storage::fake('local');
        $bytes = random_bytes(2 * 1024 * 1024);
        $token = bin2hex(random_bytes(32));
        $source = ['bytes' => $bytes, 'sha256' => hash('sha256', $bytes), 'admin_id' => 1];
        $stage = MrfImportStaging::store($source, 1, $token);
        $serialized = json_encode(['mrf_imports' => [$token => $stage]], JSON_THROW_ON_ERROR);
        $this->assertLessThan(1024, strlen($serialized));
        $restored = json_decode($serialized, true, 32, JSON_THROW_ON_ERROR)['mrf_imports'][$token];
        $this->assertSame($bytes, MrfImportStaging::restore($restored, 1, $token)['bytes']);
        MrfImportStaging::discard($restored, $token);
        Storage::disk('local')->assertMissing($stage['staged_path']);
    }

    public function test_review_files_are_bound_to_the_acting_hr_account(): void
    {
        Storage::fake('local');
        $token = bin2hex(random_bytes(32));
        $stage = MrfImportStaging::store(['bytes' => 'fixture', 'sha256' => hash('sha256', 'fixture'), 'admin_id' => 1], 1, $token);
        $this->expectException(\InvalidArgumentException::class);
        MrfImportStaging::restore($stage, 2, $token);
    }
}
