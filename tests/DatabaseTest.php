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

        // When file is missing, listing ids returns empty and next id will be assigned on save
        $ids = $db->listIds();
        $this->assertIsArray($ids);
        $this->assertSame([], $ids);
    }

    public function testSaveAndLoadRoundtrip(): void {
        $db = \Database::getInstance($this->tmpFile);

        // Save an item using the public API
        $item = $db->saveItem(['value' => 'x']);
        $this->assertArrayHasKey('id', $item);
        $id = $item['id'];

        // Reset instance to force re-read from file
        \Database::resetInstance();
        $db2 = \Database::getInstance($this->tmpFile);
        $loaded = $db2->getItem($id);

        $this->assertIsArray($loaded);
        $this->assertSame($id, $loaded['id']);
        $this->assertSame('x', $loaded['value']);
    }

    public function testSaveFailsThrowsStorageException(): void {
        // Use a path that is a directory to force fopen failure when trying to open it as a file
        $dirPath = sys_get_temp_dir() . '/php_rest_logging_test_dir_' . bin2hex(random_bytes(6));
        mkdir($dirPath, 0775);

        $this->expectException(\StorageException::class);

        // Initialize Database with the path that points to a directory
        $db = \Database::getInstance($dirPath);
        // Attempting to save an item should throw StorageException because path is a directory
        $db->saveItem(['value' => 'y']);

        // cleanup
        if (is_dir($dirPath)) @rmdir($dirPath);
    }
}
