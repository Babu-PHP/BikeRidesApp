<?php
require_once __DIR__ . '/../data/BeachCruiserRepository.php';
require_once __DIR__ . '/../data/MountainBikeRepository.php';
require_once __DIR__ . '/../data/AccessoryRepository.php';
require_once __DIR__ . '/BeachCruiserService.php';
require_once __DIR__ . '/MountainBikeService.php';
require_once __DIR__ . '/AccessoryService.php';

/**
 * ApplicationServices
 *
 * Static service locator. A classic. A fossil. A design pattern that predates
 * the widespread adoption of dependency injection containers and is widely considered
 * an anti-pattern by people who have used dependency injection containers.
 * Those people are right. This is still here.
 *
 * In .NET, this was called once in Application_Start and the services lived
 * in memory for the lifetime of the application — warm, persistent, comfortable.
 * In PHP, every request is its own private universe. initialize() is called every time.
 * The static properties last exactly one request and then cease to exist forever.
 * It is a very transient kind of singleton. An impermanent permanence.
 * The PHP runtime does not find this ironic. It has no opinions about irony.
 */
class ApplicationServices {

    /** @var BeachCruiserService */
    private static $beachCruiserService;

    /** @var MountainBikeService */
    private static $mountainBikeService;

    /** @var AccessoryService */
    private static $accessoryService;

    /**
     * Initialize all repositories and services.
     *
     * Call this at the top of every handler file. Every request. Without exception.
     * If you forget, the static properties will be null and getBeachCruiserService()
     * will return null and everything will fail in a way that points nowhere near here.
     * That's the Service Locator experience. Enjoy it. Then consider dependency injection.
     *
     * @param string $dataFolder Absolute path to the SampleData directory.
     */
    public static function initialize($dataFolder) {
        $beachRepo    = new BeachCruiserRepository($dataFolder);
        $mountainRepo = new MountainBikeRepository($dataFolder);
        $accessoryRepo = new AccessoryRepository($dataFolder);

        self::$beachCruiserService = new BeachCruiserService($beachRepo);
        self::$mountainBikeService = new MountainBikeService($mountainRepo);
        self::$accessoryService    = new AccessoryService($accessoryRepo);
    }

    /**
     * @return BeachCruiserService
     */
    public static function getBeachCruiserService() {
        return self::$beachCruiserService;
    }

    /**
     * @return MountainBikeService
     */
    public static function getMountainBikeService() {
        return self::$mountainBikeService;
    }

    /**
     * @return AccessoryService
     */
    public static function getAccessoryService() {
        return self::$accessoryService;
    }
}
