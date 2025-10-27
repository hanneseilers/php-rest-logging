<?php

/**
 * StorageException
 *
 * Exception thrown when storage operations cannot be completed.
 */
class StorageException extends \Exception {
    public function __construct(string $reason = 'STORAGE_ERROR', string $message = '') {
        parent::__construct($message ?: $reason);
    }
}

/**
 * Database
 *
 * Simple file-based JSON storage handler with atomic load/save using file locks.
 * Implemented as a singleton.
 */
class Database {
    /** @var Database|null */
    private static ?Database $instance = null;

    /** @var string */
    private string $dataFile;

    /**
     * Private constructor.
     *
     * @param string $dataFile
     */
    private function __construct(string $dataFile) {
        $this->dataFile = $dataFile;
    }

    /**
     * Get the singleton instance.
     *
     * @param string|null $dataFile Optional path used on first initialization.
     * @return Database
     */
    public static function getInstance(?string $dataFile = null): Database {
        if (self::$instance === null) {
            $dataFile = $dataFile ?? (defined('DATA_FILE') ? DATA_FILE : (__DIR__ . '/data.json'));
            self::$instance = new Database($dataFile);
        }
        return self::$instance;
    }

    /**
     * Reset the singleton (for tests).
     */
    public static function resetInstance(): void {
        self::$instance = null;
    }

    /**
     * Load data from the JSON storage file.
     *
     * @return array ['items'=>array, 'nextId'=>int]
     * @throws StorageException on I/O error
     */
    public function load(): array {
        if (!file_exists($this->dataFile)) return ['items' => [], 'nextId' => 1];

        $fp = fopen($this->dataFile, 'r');
        if (!$fp) throw new StorageException('STORAGE_ERROR', 'Failed to open storage file for reading');

        flock($fp, LOCK_SH);
        $json = stream_get_contents($fp) ?: '';
        flock($fp, LOCK_UN);
        fclose($fp);

        $data = json_decode($json, true);
        return (is_array($data) && isset($data['items'], $data['nextId'])) ? $data : ['items' => [], 'nextId' => 1];
    }

    /**
     * Save data to storage atomically.
     *
     * @param array $data
     * @throws StorageException on I/O error
     */
    public function save(array $data): void {
        $dir = dirname($this->dataFile);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        // If the target path is an existing directory, treat it as a storage error
        if (is_dir($this->dataFile)) {
            throw new StorageException('STORAGE_ERROR', 'Storage path is a directory');
        }

        // Suppress warnings from fopen and handle failures explicitly so PHPUnit sees the exception
        $fp = @fopen($this->dataFile, 'c+');
        if (!$fp) {
            throw new StorageException('STORAGE_ERROR', 'Failed to open storage file for writing');
        }

        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}