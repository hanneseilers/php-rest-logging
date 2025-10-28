<?php

require_once __DIR__ . '/exceptions.php';

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
      * (now private - storage internal)
      *
      * @return array ['items'=>array]
     * @throws StorageException on I/O error
     */
    private function load(): array {
        if (!file_exists($this->dataFile)) return ['items' => []];

        $fp = fopen($this->dataFile, 'r');
        if (!$fp) throw new StorageException('STORAGE_ERROR', 'Failed to open storage file for reading');

        flock($fp, LOCK_SH);
            // If the storage path points at a directory, treat it as an explicit storage error
            if (is_dir($this->dataFile)) {
                throw new StorageException('STORAGE_ERROR', 'Storage path is a directory');
            }

            // Suppress warnings from fopen/stream_get_contents and handle failures explicitly
            $fp = @fopen($this->dataFile, 'r');
            if (!$fp) throw new StorageException('STORAGE_ERROR', 'Failed to open storage file for reading');

            flock($fp, LOCK_SH);
            $json = @stream_get_contents($fp);
            $json = $json ?: '';
        flock($fp, LOCK_UN);
        fclose($fp);

        $data = json_decode($json, true);
        if (is_array($data) && isset($data['items']) && is_array($data['items'])) {
            return ['items' => $data['items']];
        }

        return ['items' => []];
    }

    /**
     * Save data to storage atomically.
     * (private - internal)
     *
     * @param array $data
     * @throws StorageException on I/O error
     */
    private function save(array $data): void {
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

    /**
     * Public: retrieve a single item by id. Returns null if not found.
     *
     * @param int $id
     * @return array|null
     * @throws StorageException on I/O error
     */
    public function getItem(int $id): ?array {
        $db = $this->load();
        return $db['items'][(string)$id] ?? null;
    }

    /**
     * Public: save an item. If $id is null, a new id is assigned. Otherwise item is created/updated.
     * Storage decides whether it's a create or update and handles timestamps.
     * Returns the saved item.
     *
     * @param array $userData
     * @param int|null $id
     * @return array
     * @throws StorageException on I/O error
     */
    public function saveItem(array $userData, ?int $id = null): array {
        $db = $this->load();
        $now = gmdate('c');

        if ($id === null) {
            $id = $this->getNextId();
            $created = $now;
        } else {
            $key = (string)$id;
            $created = isset($db['items'][$key]['createdAt']) ? $db['items'][$key]['createdAt'] : $now;
        }

        $item = $userData;
        $item['id'] = $id;
        $item['createdAt'] = $created;
        $item['lastUpdatedAt'] = $now;

        $db['items'][(string)$id] = $item;
        $this->save($db);
        return $item;
    }

    /**
     * Public: return a list of available ids (as ints)
     *
     * @return int[]
     * @throws StorageException on I/O error
     */
    public function listIds(): array {
        $db = $this->load();
        $keys = array_keys($db['items']);
        sort($keys, SORT_NUMERIC);
        return array_map('intval', $keys);
    }

    /**
     * Public: compute the next id based on existing ids (highest + 1).
     * Returns 1 when no ids exist.
     *
     * @return int
     * @throws StorageException on I/O error
     */
    public function getNextId(): int {
        $ids = $this->listIds();
        if (empty($ids)) return 1;
        return max($ids) + 1;
    }
    
}