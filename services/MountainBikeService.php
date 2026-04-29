<?php
/**
 * MountainBikeService
 *
 * Business logic for mountain bike rental operations.
 * Structurally identical to BeachCruiserService.
 * The bikes are different. The code is the same.
 * This is copy-paste polymorphism. It is not polymorphism.
 * A base class would solve this. A trait would solve this. An interface would help.
 * We have none of those things. We have two service files that are nearly identical
 * and the quiet understanding that whoever upgrades this will sort it out.
 */
class MountainBikeService {

    /** @var MountainBikeRepository */
    private $repo;

    public function __construct(MountainBikeRepository $repo) {
        $this->repo = $repo;
    }

    /**
     * Get all mountain bikes.
     * Returns an array of associative arrays. Not typed. Not validated.
     * Just arrays all the way down. PHP 7 trusts you to know what's in them.
     * PHP 8 introduced named arguments and union types, which would help here.
     * We are on PHP 7. We have docblocks and optimism.
     *
     * @return array
     */
    public function getAll() {
        return $this->repo->getAll();
    }

    /**
     * Rent a mountain bike by ID.
     *
     * Identical in shape to BeachCruiserService::rentBike(), except the key
     * is 'BikeID' instead of 'bike_id' and 'IsAvailable' instead of 'is_available'.
     * PascalCase because the JSON uses PascalCase and consistency within a type
     * is the best kind of consistency we have to offer.
     * The inconsistency between types is a different problem. Noted. Deferred.
     *
     * The foreach reference (&$bike) footgun applies here too. See BeachCruiserService.
     * The mountain waits for no one, and neither does an unset reference variable.
     *
     * @param int $bikeId
     * @return bool
     */
    public function rentBike($bikeId) {
        $bikes = $this->repo->getAll();

        foreach ($bikes as &$bike) {
            if ($bike['BikeID'] === intval($bikeId)) {
                if (!$bike['IsAvailable']) {
                    return false; // Already rented. The mountain waits; this bike does not.
                }
                $bike['IsAvailable'] = false;
                $this->repo->save($bikes);
                return true;
            }
        }

        return false; // Bike ID not found. The trail goes cold here.
    }

    /**
     * Reset all mountain bikes to default availability.
     * IDs 101–106. Available: true/true/false/true/true/false.
     * Hardcoded like everything else in this class.
     * The opposite of enterprise. The exact amount of enterprise needed.
     */
    public function resetToDefaults() {
        $defaults = [
            ['BikeID' => 101, 'ModelName' => 'TrailBlazer X9',   'Brand' => 'ApexRide',   'GearCount' => 21, 'SuspensionType' => 'Full',     'FrameMaterial' => 'Aluminum',     'DailyRate' => 24.99, 'IsAvailable' => true,  'Terrain' => 'All-Mountain',  'WeightKg' => 13.5],
            ['BikeID' => 102, 'ModelName' => 'Summit Shredder',  'Brand' => 'PeakForce',  'GearCount' => 27, 'SuspensionType' => 'Full',     'FrameMaterial' => 'Carbon Fiber', 'DailyRate' => 34.99, 'IsAvailable' => true,  'Terrain' => 'Enduro',        'WeightKg' => 11.2],
            ['BikeID' => 103, 'ModelName' => 'Canyon Crusher',   'Brand' => 'TerraRide',  'GearCount' => 18, 'SuspensionType' => 'Hardtail', 'FrameMaterial' => 'Steel',        'DailyRate' => 19.99, 'IsAvailable' => false, 'Terrain' => 'Cross-Country', 'WeightKg' => 14.8],
            ['BikeID' => 104, 'ModelName' => 'Ridge Runner',     'Brand' => 'ApexRide',   'GearCount' => 24, 'SuspensionType' => 'Hardtail', 'FrameMaterial' => 'Aluminum',     'DailyRate' => 22.99, 'IsAvailable' => true,  'Terrain' => 'Trail',         'WeightKg' => 12.9],
            ['BikeID' => 105, 'ModelName' => 'Peak Predator',    'Brand' => 'SummitX',    'GearCount' => 30, 'SuspensionType' => 'Full',     'FrameMaterial' => 'Carbon Fiber', 'DailyRate' => 39.99, 'IsAvailable' => true,  'Terrain' => 'Downhill',      'WeightKg' => 15.3],
            ['BikeID' => 106, 'ModelName' => 'Mud Maverick',     'Brand' => 'TerraRide',  'GearCount' => 21, 'SuspensionType' => 'Full',     'FrameMaterial' => 'Aluminum',     'DailyRate' => 27.99, 'IsAvailable' => false, 'Terrain' => 'Enduro',        'WeightKg' => 13.1],
        ];
        $this->repo->save($defaults);
    }
}
