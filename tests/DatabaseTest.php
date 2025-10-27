<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class DatabaseTest extends TestCase {
    private string $tmpFile;

    protected function setUp(): void {
        \Database::resetInstance();
        $this->tmpFile = sys_get_temp_dir() . '/php_rest_logging_test_' . bin2hex(random_bytes(6)) . '.json';
        if (file_exists($this->tmpFile)) @unlink($this->tmpFile);
    }

    protected function tearDown(): void {
        \Database::resetInstance();
        if (file_exists($this->tmpFile) && is_file($this->tmpFile)) @unlink($this->tmpFile);
    }

    public function testLoadWhenFileMissingReturnsDefault(): void {
        if (file_exists($this->tmpFile)) @unlink($this->tmpFile);
        $db = \Database::getInstance($this->tmpFile);
        $data = $db->load();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('nextId', $data);
        $this->assertSame([], $data['items']);
        $this->assertSame(1, $data['nextId']);
    }

    public function testSaveAndLoadRoundtrip(): void {
        $db = \Database::getInstance($this->tmpFile);
        $payload = ['items' => [['id' => 1, 'value' => 'x']], 'nextId' => 2];
        $db->save($payload);

        // Reset instance to force re-read from file
        \Database::resetInstance();
        $db2 = \Database::getInstance($this->tmpFile);
        $loaded = $db2->load();

        $this->assertSame($payload, $loaded);
    }

    public function testSaveFailsThrowsStorageException(): void {
        // Use a path that is a directory to force fopen failure when trying to open it as a file
        $dirPath = sys_get_temp_dir() . '/php_rest_logging_test_dir_' . bin2hex(random_bytes(6));
        mkdir($dirPath, 0775);

        $this->expectException(\StorageException::class);

        // Initialize Database with the path that points to a directory
        $db = \Database::getInstance($dirPath);
        $db->save(['items' => [], 'nextId' => 1]);

        // cleanup
        if (is_dir($dirPath)) @rmdir($dirPath);
    }
}
