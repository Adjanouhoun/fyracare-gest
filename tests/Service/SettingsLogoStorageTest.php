<?php

namespace App\Tests\Service;

use App\Service\SettingsLogoStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SettingsLogoStorageTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/fyracare-logo-test-'.bin2hex(random_bytes(5));
        mkdir($this->projectDir.'/public', 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->projectDir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->projectDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->projectDir);
    }

    public function testStoresApplicationAndInvoiceLogosWithUniqueSafeNames(): void
    {
        $storage = new SettingsLogoStorage($this->projectDir);
        $sourceA = $this->createPng();
        $sourceB = $this->createPng();

        $appPath = $storage->store(new UploadedFile($sourceA, 'logo.png', 'image/png', null, true), 'app');
        $invoicePath = $storage->store(new UploadedFile($sourceB, 'logo.png', 'image/png', null, true), 'invoice');

        self::assertMatchesRegularExpression('#^uploads/settings/logo-app-[a-f0-9]{24}\.png$#', $appPath);
        self::assertMatchesRegularExpression('#^uploads/settings/logo-invoice-[a-f0-9]{24}\.png$#', $invoicePath);
        self::assertNotSame($appPath, $invoicePath);
        self::assertFileExists($this->projectDir.'/public/'.$appPath);
        self::assertFileExists($this->projectDir.'/public/'.$invoicePath);
    }

    public function testDeleteOnlyRemovesFilesFromTheManagedSettingsDirectory(): void
    {
        $storage = new SettingsLogoStorage($this->projectDir);
        $source = $this->createPng();
        $path = $storage->store(new UploadedFile($source, 'logo.png', 'image/png', null, true), 'app');

        $legacy = $this->projectDir.'/public/uploads/legacy.png';
        file_put_contents($legacy, 'legacy');

        $storage->delete($path);
        $storage->delete('uploads/legacy.png');

        self::assertFileDoesNotExist($this->projectDir.'/public/'.$path);
        self::assertFileExists($legacy);
    }

    public function testRejectsUnknownLogoType(): void
    {
        $storage = new SettingsLogoStorage($this->projectDir);
        $source = $this->createPng();

        $this->expectException(\InvalidArgumentException::class);
        $storage->store(new UploadedFile($source, 'logo.png', 'image/png', null, true), 'unknown');
    }

    private function createPng(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'logo-');
        file_put_contents(
            $path,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );

        return $path;
    }
}
