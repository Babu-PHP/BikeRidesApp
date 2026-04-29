<?php
/**
 * AccessoryRepository
 *
 * Loads accessory data from JSON, caches it with serialize().
 * Third repository. Third time doing the exact same caching pattern.
 * We could have abstracted this into a base class. We did not.
 * Copy-paste is also a design pattern. It is not a good one.
 * But here we are, and it works, and refactoring it is left as an exercise
 * for the developer who gets handed this codebase in 2026 and says "what is this."
 */
class AccessoryRepository {

    private $dataPath;
    private $cachePath;

    public function __construct($dataFolder)
    {
        $dataFolder = rtrim($dataFolder, DIRECTORY_SEPARATOR);

        if (!is_dir($dataFolder)) {
            throw new \InvalidArgumentException("Invalid data folder: {$dataFolder}");
        }

        if (!is_readable($dataFolder)) {
            throw new \RuntimeException("Data folder is not readable: {$dataFolder}");
        }

        if (!is_writable($dataFolder)) {
            throw new \RuntimeException("Data folder is not writable: {$dataFolder}");
        }

        $this->dataPath  = $dataFolder . DIRECTORY_SEPARATOR . 'accessories.json';
        $this->cachePath = $this->dataPath . '.cache';
    }

    /**
     * Get all accessories.
     *
     * Cache check → unserialize → return. Or: reload from JSON → serialize → return.
     * The cycle of life. Birth (load), storage (serialize), retrieval (unserialize), death (request ends).
     * PHP is very Buddhist about object lifetime. Everything is impermanent. Especially static variables.
     *
     * @return array
     */
    public function getAll()
    {
        if ($this->isCacheFresh() && is_file($this->cachePath)) {

            $content = file_get_contents($this->cachePath);

            if ($content !== false && $content !== '') {

                $cached = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $cached;
                }
            }
            // Cache invalid → fallback
        }

        $accessories = $this->loadFromJson();

        $json = json_encode($accessories);

        if ($json === false) {
            throw new \RuntimeException(
                'JSON encode failed: ' . json_last_error_msg()
            );
        }

        // Atomic write
        $tempFile = $this->cachePath . '.tmp';

        if (file_put_contents($tempFile, $json, LOCK_EX) === false) {
            throw new \RuntimeException("Failed to write cache temp file");
        }

        if (!rename($tempFile, $this->cachePath)) {
            throw new \RuntimeException("Failed to replace cache file");
        }

        return $accessories;
    }

    /**
     * Save accessories to JSON and refresh the cache.
     * Overwrites the file completely on every save.
     * There are no partial updates. There are no transactions.
     * If the server crashes between writing the JSON and writing the cache,
     * the cache will be stale and the JSON will be current and everything will be fine
     * because isCacheFresh() will catch it. Probably.
     *
     * @param array $accessories
     */
    public function save($accessories)
    {
        $json = json_encode($accessories, JSON_PRETTY_PRINT);

        if ($json === false) {
            throw new \RuntimeException(
                'JSON encode failed: ' . json_last_error_msg()
            );
        }

        // Write source of truth
        $this->safeWrite($this->dataPath, $json);

        // Write cache in SAME format (JSON)
        $this->safeWrite($this->cachePath, $json);
    }

    private function safeWrite($path, $content)
    {
        $temp = $path . '.tmp';

        if (file_put_contents($temp, $content, LOCK_EX) === false) {
            throw new \RuntimeException("Failed writing temp file: {$temp}");
        }

        if (!rename($temp, $path)) {
            throw new \RuntimeException("Failed replacing file: {$path}");
        }
    }

    /**
     * Returns true if the cache sidecar is newer than the JSON source.
     * Two file_exists() calls. No locking. No atomicity.
     * Works fine in development where one person uses it at a time.
     * "Works fine in development" is the motto of this entire codebase.
     */
    private function isCacheFresh()
    {
        if (!is_file($this->cachePath) || !is_file($this->dataPath)) {
            return false;
        }

        $cacheTime = @filemtime($this->cachePath);
        $dataTime  = @filemtime($this->dataPath);

        if ($cacheTime === false || $dataTime === false) {
            return false;
        }

        // Optional: reject empty cache
        if (filesize($this->cachePath) === 0) {
            return false;
        }

        return $cacheTime >= $dataTime;
    }

    /**
     * Load accessories from JSON.
     * Fields: AccessoryID, Name, Category, Description, UnitPrice, StockCount, CompatibleWith.
     * CompatibleWith is an array of strings like ["beach", "mountain"] or ["all"].
     * (array) cast on an already-array value is harmless. On a non-array value it is forgiving.
     * PHP is nothing if not forgiving. Sometimes too forgiving. This is one of those times.
     */
    private function loadFromJson()
    {
        if (!is_file($this->dataPath)) {
            throw new \RuntimeException("Data file not found: {$this->dataPath}");
        }

        $contents = file_get_contents($this->dataPath);

        if ($contents === false) {
            throw new \RuntimeException("Failed to read file: {$this->dataPath}");
        }

        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'JSON decode error: ' . json_last_error_msg()
            );
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException("Invalid JSON structure: expected array");
        }

        $accessories = [];

        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue; // skip invalid entries
            }

            $accessories[] = [
                'AccessoryID'    => isset($item['AccessoryID']) ? (int)$item['AccessoryID'] : null,
                'Name'           => $item['Name'] ?? '',
                'Category'       => $item['Category'] ?? '',
                'Description'    => $item['Description'] ?? '',
                'UnitPrice'      => isset($item['UnitPrice']) ? (float)$item['UnitPrice'] : 0.0,
                'StockCount'     => isset($item['StockCount']) ? (int)$item['StockCount'] : 0,
                'CompatibleWith' => isset($item['CompatibleWith']) && is_array($item['CompatibleWith'])
                                    ? $item['CompatibleWith']
                                    : [],
            ];
        }

        return $accessories;
    }
}
