<?php

namespace Tests\Unit\Services;

use App\Services\BackupService;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupServiceTest extends TestCase
{
    /**
     * @var array<int,string>
     */
    private array $tempDirs = [];

    public function test_create_storage_archive_treats_permission_denied_as_warning_when_archive_exists(): void
    {
        Process::fake([
            '*' => Process::result(
                '',
                implode(PHP_EOL, [
                    'tar: ./private/qmh: Cannot open: Permission denied',
                    'tar: ./tmp/qmh/.config: Cannot open: Permission denied',
                    'tar: Exiting with failure status due to previous errors',
                ]),
                2
            ),
        ]);

        $service = app(BackupService::class);
        $outputPath = $this->makeTempDir();
        file_put_contents($outputPath.'/storage.tar.gz', 'fake-tar-content');

        $result = $service->createStorageArchive($outputPath);

        $this->assertSame($outputPath.'/storage.tar.gz', $result['path']);
        $this->assertGreaterThan(0, $result['size']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_create_storage_archive_throws_on_non_ignorable_tar_failure(): void
    {
        Process::fake([
            '*' => Process::result('', 'tar: unrecognized option -- bad-flag', 2),
        ]);

        $service = app(BackupService::class);
        $outputPath = $this->makeTempDir();
        file_put_contents($outputPath.'/storage.tar.gz', 'fake-tar-content');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Storage archive failed');

        $service->createStorageArchive($outputPath);
    }

    private function makeTempDir(): string
    {
        $path = storage_path('framework/testing/backup-service-'.Str::uuid()->toString());
        mkdir($path, 0755, true);
        $this->tempDirs[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            foreach (glob($dir.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            @rmdir($dir);
        }

        parent::tearDown();
    }
}
