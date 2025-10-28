<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class StorageTest extends TestCase {
    private string $tmpFile;

    protected function setUp(): void {
        \Database::resetInstance();
        $this->tmpFile = sys_get_temp_dir() . '/php_rest_storage_test_' . bin2hex(random_bytes(6)) . '.json';
        if (file_exists($this->tmpFile)) @unlink($this->tmpFile);
    }

    protected function tearDown(): void {
        \Database::resetInstance();
        if (file_exists($this->tmpFile) && is_file($this->tmpFile)) @unlink($this->tmpFile);
    }

    public function testSaveItemCreatesAndAssignsId(): void {
        $db = \Database::getInstance($this->tmpFile);
        $item = $db->saveItem(['name' => 'alpha']);

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('createdAt', $item);
        $this->assertArrayHasKey('lastUpdatedAt', $item);
        $this->assertSame('alpha', $item['name']);

        // persisted
        \Database::resetInstance();
        $db2 = \Database::getInstance($this->tmpFile);
        $fetched = $db2->getItem($item['id']);
        $this->assertSame($item['id'], $fetched['id']);
        $this->assertSame('alpha', $fetched['name']);
    }

    public function testListIdsReturnsSortedInts(): void {
        $db = \Database::getInstance($this->tmpFile);
        $a = $db->saveItem(['name' => 'a']);
        $b = $db->saveItem(['name' => 'b']);
        $ids = $db->listIds();
        $this->assertSame([ $a['id'], $b['id'] ], $ids);
    }

    public function testUpdateItemPreservesCreatedAtAndUpdatesTimestamp(): void {
        $db = \Database::getInstance($this->tmpFile);
        $item = $db->saveItem(['name' => 'first']);
        $created = $item['createdAt'];

        // update
        sleep(1);
        $updated = $db->saveItem(['name' => 'second'], $item['id']);
        $this->assertSame($item['id'], $updated['id']);
        $this->assertSame($created, $updated['createdAt']);
        $this->assertNotSame($updated['lastUpdatedAt'], $created);
        $this->assertSame('second', $updated['name']);
    }

    public function testGetItemNotFoundReturnsNull(): void {
        $db = \Database::getInstance($this->tmpFile);
        $this->assertNull($db->getItem(9999));
    }

    public function testSaveItemToDirectoryPathThrowsStorageException(): void {
        $dirPath = sys_get_temp_dir() . '/php_rest_storage_test_dir_' . bin2hex(random_bytes(6));
        mkdir($dirPath, 0775);

        $this->expectException(\StorageException::class);
        $db = \Database::getInstance($dirPath);
        $db->saveItem(['name' => 'x']);

        if (is_dir($dirPath)) @rmdir($dirPath);
    }
}
