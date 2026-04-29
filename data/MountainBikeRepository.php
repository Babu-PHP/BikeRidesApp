<?php
/**
 * MountainBikeRepository
 *
 * Loads mountain bike data from JSON, caches it with serialize().
 *
 * serialize()/unserialize(): same approach as BeachCruiserRepository.
 * You might wonder why we serialize a PHP array back to disk when we could
 * just re-parse the JSON. The answer is "performance" and "legacy reasons"
 * and the faint smell of decisions made at 11pm. The cache is fast. The JSON parse is slower.
 * This is the optimization. It works. It is also the kind of thing you replace
 * with Redis or Memcached and feel very modern for exactly one sprint.
 */
class MountainBikeRepository {

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

        $this->dataPath  = $dataFolder . DIRECTORY_SEPARATOR . 'mountain_bikes.json';
        $this->cachePath = $dataFolder . DIRECTORY_SEPARATOR . 'mountain_bikes.cache';
    }

    /**
     * Get all mountain bikes.
     *
     * Cache freshness check via filemtime() comparison.
     * If the cache is fresh: unserialize and return. Zero JSON parsing.
     * If not: load from JSON, serialize to cache, return.
     * PascalCase keys because the JSON uses PascalCase and we respect the source.
     * Even when it's inconsistent with every other file in this project.
     * Especially then.
     *
     * @return array
     */
    public function getAll()
    {
        if ($this->isCacheFresh() && file_exists($this->cachePath)) {

            $content = file_get_contents($this->cachePath);

            if ($content !== false) {
                $cached = @unserialize($content);

                if ($cached !== false || $content === serialize(false)) {
                    return $cached;
                }
            }
            // corrupted cache → fallback
        }

        $bikes = $this->loadFromJson();

        file_put_contents(
            $this->cachePath,
            serialize($bikes),
            LOCK_EX
        );

        return $bikes;
    }

    /**
     * Save bikes to JSON and refresh the cache.
     * JSON_PRETTY_PRINT because we are courteous to future humans who open the file directly.
     * The cache file has no such courtesy. It is for machines.
     *
     * @param array $bikes
     */
    public function save($bikes)
    {
        $json = json_encode($bikes, JSON_PRETTY_PRINT);

        if ($json === false) {
            throw new \RuntimeException('JSON encoding failed: ' . json_last_error_msg());
        }

        // Write JSON safely
        $this->safeWrite($this->dataPath, $json);

        // Write cache safely
        $this->safeWrite($this->cachePath, serialize($bikes));
    }

    private function safeWrite($path, $content)
    {
        $tempFile = $path . '.tmp';

        // Write to temp file first
        if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
            throw new \RuntimeException("Failed to write temp file: {$tempFile}");
        }

        // Atomic replace
        if (!rename($tempFile, $path)) {
            throw new \RuntimeException("Failed to replace file: {$path}");
        }
    }

    /**
     * Returns true if the cache sidecar exists and is newer than the JSON data file.
     * file_exists() called twice. In a world with better architecture, this would be atomic.
     * In this world, there is a race condition nobody has ever triggered. Yet.
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

        return $cacheTime >= $dataTime;
    }

    /**
     * Load bikes from JSON.
     * json_decode() with true returns an associative array, not an object.
     * The true parameter has been there since PHP 5.2 and has caused exactly
     * one argument per team about whether it should be the default.
     * It is not the default. You must remember to pass it.
     * We remember. Today.
     */
    private function loadFromJson()
    {
        if (!is_file($this->dataPath)) {
            throw new \RuntimeException("Data file not found: {$this->dataPath}");
        }

        $contents = file_get_contents($this->dataPath);

        if ($contents === false) {
            throw new \RuntimeException("Failed to read data file: {$this->dataPath}");
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

        $bikes = [];

        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue; // skip invalid entries
            }

            $bikes[] = [
                'BikeID'         => isset($item['BikeID']) ? (int)$item['BikeID'] : null,
                'ModelName'      => $item['ModelName'] ?? '',
                'Brand'          => $item['Brand'] ?? '',
                'GearCount'      => isset($item['GearCount']) ? (int)$item['GearCount'] : 0,
                'SuspensionType' => $item['SuspensionType'] ?? '',
                'FrameMaterial'  => $item['FrameMaterial'] ?? '',
                'DailyRate'      => isset($item['DailyRate']) ? (float)$item['DailyRate'] : 0.0,
                'IsAvailable'    => isset($item['IsAvailable']) ? (bool)$item['IsAvailable'] : false,
                'Terrain'        => $item['Terrain'] ?? '',
                'WeightKg'       => isset($item['WeightKg']) ? (float)$item['WeightKg'] : 0.0,
            ];
        }

        return $bikes;
    }
}
