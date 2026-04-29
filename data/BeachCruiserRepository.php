<?php
/**
 * BeachCruiserRepository
 *
 * Loads beach cruiser data from XML, caches it with serialize().
 *
 * serialize()/unserialize() — PHP's version of "I'll just save this as a blob."
 * Works great until someone manually edits the .cache file, at which point
 * unserialize() returns false and offers no further comment on the matter.
 * The PHP equivalent of opening a mystery tupperware from the fridge.
 * You asked for leftovers. You got false. No refunds.
 */
class BeachCruiserRepository {

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

        $this->dataPath  = $dataFolder . DIRECTORY_SEPARATOR . 'beach_cruisers.xml';
        $this->cachePath = $this->dataPath . '.cache';
    }

    /**
     * Get all beach cruisers.
     *
     * Checks if the .cache sidecar is newer than the .xml source.
     * If yes: deserializes the blob and returns it. Fast. Opaque. Trusting.
     * If no: parses the XML, serializes the result, saves it, returns it.
     * If the cache is corrupt: silently falls back to XML with the energy of
     * someone who has been through this before and no longer finds it interesting.
     *
     * @return array
     */
    public function getAll()
    {
        if ($this->isCacheFresh() && is_file($this->cachePath)) {

            $content = file_get_contents($this->cachePath);

            if ($content !== false) {
                $cached = @unserialize($content);

                if ($cached !== false || $content === serialize(false)) {
                    return $cached;
                }
            }
            // Cache is corrupt → fallback silently
        }

        $bikes = $this->loadFromXml();

        file_put_contents(
            $this->cachePath,
            serialize($bikes),
            LOCK_EX
        );

        return $bikes;
    }

    /**
     * Save bikes back to XML and refresh the cache.
     * The file is the database. The cache is the optimization.
     * Together they are a persistence layer with exactly zero transactions.
     *
     * @param array $bikes
     */
    public function save($bikes)
    {
        // First persist source of truth
        $this->writeToXml($bikes);

        // Then update cache safely
        $this->safeWrite(
            $this->cachePath,
            serialize($bikes)
        );
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
     * Returns true if the cache file exists and is newer than the data file.
     * filemtime() is unaware of time zones and doesn't care. Neither do we.
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
     * Load bikes from XML using SimpleXML.
     * SimpleXML turns XML nodes into magic objects that look like arrays,
     * act like strings, and are secretly neither. Cast everything to string
     * or PHP will hand you a SimpleXMLElement when you asked for a name.
     * You've been warned. We learned the hard way so you didn't have to.
     */
    private function loadFromXml()
    {
        if (!is_file($this->dataPath)) {
            throw new \RuntimeException("XML file not found: {$this->dataPath}");
        }

        libxml_use_internal_errors(true);

        $xml = simplexml_load_file($this->dataPath);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            throw new \RuntimeException(
                "Failed to parse XML: " . print_r($errors, true)
            );
        }

        $bikes = [];

        if (!isset($xml->Bike)) {
            return $bikes; // no data, return empty
        }

        foreach ($xml->Bike as $bikeNode) {
            $bikes[] = [
                'bike_id'      => isset($bikeNode->bike_id) ? (int)$bikeNode->bike_id : null,
                'model_name'   => (string)($bikeNode->model_name ?? ''),
                'color'        => (string)($bikeNode->color ?? ''),
                'frame_size'   => (string)($bikeNode->frame_size ?? ''),
                'daily_rate'   => isset($bikeNode->daily_rate) ? (float)$bikeNode->daily_rate : 0.0,
                'is_available' => filter_var(
                    (string)($bikeNode->is_available ?? false),
                    FILTER_VALIDATE_BOOLEAN
                ),
            ];
        }

        return $bikes;
    }

    /**
     * Write bikes back to XML.
     *
     * Uses each() to iterate over the array with the internal array pointer.
     * each() was already old when most PHP developers learned to code.
     * foreach() has been the right answer since PHP 4. We use each() anyway.
     * You will notice this when you run it on PHP 8, because each() will be gone
     * and replaced by a very clear error message. Consider this foreshadowing.
     * reset() is called first because each() will start wherever the pointer is,
     * and if something moved it, we'd skip bikes. We reset. We iterate. We persist.
     */
    private function writeToXml($bikes)
    {
        $xml = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="utf-8"?><BeachCruisers/>'
        );

        foreach ($bikes as $bike) {

            if (!is_array($bike)) {
                continue;
            }

            $bikeNode = $xml->addChild('Bike');

            $bikeNode->addChild('bike_id',      (string)($bike['bike_id'] ?? ''));
            $bikeNode->addChild('model_name',   (string)($bike['model_name'] ?? ''));
            $bikeNode->addChild('color',        (string)($bike['color'] ?? ''));
            $bikeNode->addChild('frame_size',   (string)($bike['frame_size'] ?? ''));
            $bikeNode->addChild('daily_rate',   (string)($bike['daily_rate'] ?? '0'));
            $bikeNode->addChild(
                'is_available',
                !empty($bike['is_available']) ? 'true' : 'false'
            );
        }

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        // Atomic write (VERY important)
        $tempFile = $this->dataPath . '.tmp';

        if ($dom->save($tempFile) === false) {
            throw new \RuntimeException("Failed to write XML temp file");
        }

        if (!rename($tempFile, $this->dataPath)) {
            throw new \RuntimeException("Failed to replace XML file");
        }
    }
}
