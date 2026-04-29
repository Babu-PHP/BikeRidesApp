<?php
/**
 * BeachCruiserService
 *
 * Business logic for beach cruiser rental operations.
 * In .NET this was a service class injected via constructor into the handler.
 * In PHP this is also a service class injected via constructor.
 * Some things transcend platforms. Constructors are one of them.
 *
 * The difference: in .NET the service lived for the app's lifetime.
 * Here it lives for exactly one HTTP request, which is typically 30–200ms,
 * depending on disk speed and how many deprecated functions are being called.
 * Cherish it. It will not be here when you return.
 */
class BeachCruiserService {

    /** @var BeachCruiserRepository */
    private $repo;

    public function __construct(BeachCruiserRepository $repo) {
        $this->repo = $repo;
    }

    /**
     * Get all beach cruisers.
     * Delegates directly to the repository. The service layer is not shy about this.
     * In some architectures, this indirection would feel pointless.
     * In this one, it allows us to put business logic here later.
     * We have not put business logic here. The method remains optimistic.
     *
     * @return array
     */
    public function getAll() {
        return $this->repo->getAll();
    }

    /**
     * Rent a bike by ID.
     *
     * Finds the bike by ID, checks availability, marks it unavailable, saves.
     * Uses a reference (&$bike) to modify the array in place.
     * The & reference in foreach is a classic PHP footgun: if you forget to
     * unset($bike) after the loop, the last element is still referenced by $bike
     * and a future loop on the same variable will silently corrupt your data.
     * We do not have a second loop here, so we are fine.
     * This time.
     *
     * @param int $bikeId
     * @return bool true if rented, false if not found or not available
     */
    public function rentBike($bikeId) {
        $bikes = $this->repo->getAll();

        foreach ($bikes as &$bike) {
            if ($bike['bike_id'] === intval($bikeId)) {
                if (!$bike['is_available']) {
                    return false; // Already rented. Someone got there first. The early bird and all that.
                }
                $bike['is_available'] = false;
                $this->repo->save($bikes);
                return true;
            }
        }

        return false; // Bike not found. Did it ever exist? Records suggest yes. Current behavior suggests maybe.
    }

    /**
     * Reset all bikes to default availability.
     *
     * Hardcoded defaults. IDs 1–6. Available: true/true/false/true/true/false.
     * In .NET this reloaded from an embedded resource.
     * Here we just list them. Thirty lines. Honest. Slightly embarrassing at parties.
     * But it works, and the defaults haven't changed since 2008, so here we are.
     */
    public function resetToDefaults() {
        $defaults = [
            ['bike_id' => 1, 'model_name' => 'Sunset Drifter',  'color' => 'Coral',      'frame_size' => 'Medium', 'daily_rate' => 14.99, 'is_available' => true],
            ['bike_id' => 2, 'model_name' => 'Ocean Breeze',    'color' => 'Teal',       'frame_size' => 'Large',  'daily_rate' => 16.99, 'is_available' => true],
            ['bike_id' => 3, 'model_name' => 'Sandy Shores',    'color' => 'Cream',      'frame_size' => 'Small',  'daily_rate' => 12.99, 'is_available' => false],
            ['bike_id' => 4, 'model_name' => 'Tropical Wave',   'color' => 'Lime Green', 'frame_size' => 'Medium', 'daily_rate' => 15.99, 'is_available' => true],
            ['bike_id' => 5, 'model_name' => 'Breezy Blue',     'color' => 'Sky Blue',   'frame_size' => 'Large',  'daily_rate' => 17.99, 'is_available' => true],
            ['bike_id' => 6, 'model_name' => 'Flamingo Glide',  'color' => 'Hot Pink',   'frame_size' => 'Small',  'daily_rate' => 13.99, 'is_available' => false],
        ];
        $this->repo->save($defaults);
    }
}
