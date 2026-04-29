<?php
/**
 * AccessoryService
 *
 * Business logic for accessory browsing and order processing.
 * Bundle deal: AccessoryID 1 (Water Bottle) + AccessoryID 3 (Bike Light) = 10% off.
 * Why those two? Marketing said so. The code does not question Marketing.
 * Marketing has opinions. The code has constants. The relationship works.
 */
class AccessoryService {

    /** @var AccessoryRepository */
    private $repo;

    // Bundle deal: these two accessory IDs together get a 10% discount.
    // Hardcoded because "it'll never change" — words spoken before every change.
    const BUNDLE_ID_A = 1;
    const BUNDLE_ID_B = 3;
    const BUNDLE_DISCOUNT_RATE = 0.10;

    public function __construct(AccessoryRepository $repo) {
        $this->repo = $repo;
    }

    /**
     * Get all accessories.
     *
     * @return array
     */
    public function getAll() {
        return $this->repo->getAll();
    }

    /**
     * Get accessories compatible with a given bike type.
     *
     * First sanitizes $bikeType using FILTER_SANITIZE_STRING.
     * FILTER_SANITIZE_STRING strips HTML tags and encodes special characters.
     * It has been called "surprising" in the PHP docs, which is a very polite way
     * of saying "it does things you wouldn't expect." It works in PHP 7.
     * Check the changelog before running this on PHP 8.1+. You'll have a surprise of your own.
     *
     * Then filters using create_function().
     * create_function() builds a PHP string and evals it. It is literally eval() in a trench coat.
     * A proper anonymous function (function($x) use ($y) {}) has existed since PHP 5.3.
     * We are on PHP 7. We are using create_function() anyway.
     * It still works here. It will not work everywhere. You'll know when you find the edge.
     *
     * @param string $bikeType 'beach' or 'mountain'
     * @return array
     */
    public function getCompatibleWith($bikeType) {
        $bikeType = filter_var($bikeType, FILTER_SANITIZE_STRING); // FILTER_SANITIZE_STRING: works in PHP 7, less so later
        $bikeType = strtolower(trim($bikeType));

        $accessories = $this->repo->getAll();

        // create_function(): eval in a trench coat. Been deprecated since PHP 7.2.
        // The $bikeType variable is interpolated into the string body because
        // create_function() has no 'use' clause — it's not a closure, it's a string that runs.
        // addslashes() is here to keep $bikeType from accidentally becoming PHP code.
        // "Accidentally becoming PHP code" is the kind of sentence that should give you pause.
        /* $filterFn = create_function(
            '$accessory',
            'return in_array("' . addslashes($bikeType) . '", $accessory["CompatibleWith"]) || in_array("all", $accessory["CompatibleWith"]);'
        ); */ // ---- This will work on php7 ----//
        $filterFn = function ($accessory) use ($bikeType) {
            return in_array($bikeType, $accessory['CompatibleWith']) ||
                in_array('all', $accessory['CompatibleWith']);
        };// ---- This will work on php8 ----//

        return array_values(array_filter($accessories, $filterFn));
    }

    /**
     * Process an accessory order.
     *
     * Validates stock, applies bundle discount if applicable, deducts stock, saves.
     * All validation happens before any stock changes.
     * This is the correct order of operations. We are proud of this.
     * Everything else in this file can be debated. This part is right.
     *
     * @param array $quantities Array of ['AccessoryID' => id, 'Quantity' => qty] objects
     * @return array ['Success', 'Message', 'TotalPrice', 'DiscountAmount', 'BundleDiscountApplied']
     */
    public function processOrder($quantities) {
        $accessories = $this->repo->getAll();

        // Build a lookup map by AccessoryID.
        // Note the & reference in the foreach. This is intentional and load-bearing.
        // The $accessoryMap values reference the same data as $accessories,
        // so when we deduct stock via $accessoryMap, the $accessories array reflects it.
        // It is elegant. It is also the kind of thing that confuses people for 20 minutes
        // before they see the & and have a small epiphany.
        $accessoryMap = [];
        foreach ($accessories as &$acc) {
            $accessoryMap[$acc['AccessoryID']] = &$acc;
        }

        // Validate all quantities before touching stock. Optimistic loop, pessimistic trust.
        $orderedQuantities = [];
        foreach ($quantities as $item) {
            $id  = intval($item['AccessoryID']);
            $qty = intval($item['Quantity']);

            if ($qty <= 0) {
                continue; // Zero or negative quantities are quietly ignored, like most problems in life.
            }

            if (!isset($accessoryMap[$id])) {
                return [
                    'Success'              => false,
                    'Message'              => 'Accessory ID ' . $id . ' not found. It may never have existed. Taxonomy is hard.',
                    'TotalPrice'           => 0.0,
                    'DiscountAmount'       => 0.0,
                    'BundleDiscountApplied' => false,
                ];
            }

            if ($accessoryMap[$id]['StockCount'] < $qty) {
                return [
                    'Success'              => false,
                    'Message'              => 'Not enough stock for: ' . $accessoryMap[$id]['Name'] . '. Available: ' . $accessoryMap[$id]['StockCount'] . '. Requested: ' . $qty . '. Math is undefeated.',
                    'TotalPrice'           => 0.0,
                    'DiscountAmount'       => 0.0,
                    'BundleDiscountApplied' => false,
                ];
            }

            $orderedQuantities[$id] = $qty;
        }

        if (empty($orderedQuantities)) {
            return [
                'Success'              => false,
                'Message'              => 'No items ordered. A cart with nothing in it is just a rectangle.',
                'TotalPrice'           => 0.0,
                'DiscountAmount'       => 0.0,
                'BundleDiscountApplied' => false,
            ];
        }

        // Calculate subtotal. floats doing float things. round() at the end because
        // 0.1 + 0.2 is not 0.3 and PHP is not unique in this regard. IEEE 754 sends its regards.
        $subtotal = 0.0;
        foreach ($orderedQuantities as $id => $qty) {
            $subtotal += $accessoryMap[$id]['UnitPrice'] * $qty;
        }

        // Bundle deal: IDs 1 and 3 both ordered (any quantity) = 10% off the whole order.
        // Marketing's idea. Still Marketing's idea. Always has been.
        $bundleApplied  = isset($orderedQuantities[self::BUNDLE_ID_A]) && isset($orderedQuantities[self::BUNDLE_ID_B]);
        $discountAmount = $bundleApplied ? round($subtotal * self::BUNDLE_DISCOUNT_RATE, 2) : 0.0;
        $totalPrice     = round($subtotal - $discountAmount, 2);

        // Validation passed. Deduct stock. The point of no return.
        // If save() fails after this, stock will be incorrect. There is no rollback.
        // This is not a transactional system. This is a PHP file.
        foreach ($orderedQuantities as $id => $qty) {
            $accessoryMap[$id]['StockCount'] -= $qty;
        }

        $this->repo->save($accessories);

        return [
            'Success'              => true,
            'Message'              => $bundleApplied
                ? 'Order placed! Bundle deal applied: 10% off for Water Bottle + Bike Light. Marketing nods approvingly.'
                : 'Order placed successfully. No bundle discount this time. Consider the Water Bottle.',
            'TotalPrice'           => $totalPrice,
            'DiscountAmount'       => $discountAmount,
            'BundleDiscountApplied' => $bundleApplied,
        ];
    }

    /**
     * Reset all accessory stock to defaults.
     * Stock counts: 1=>15, 2=>8, 3=>20, 4=>6.
     * These are the numbers Marketing approved in 2008. They have not been revisited.
     * Do not change them without scheduling a meeting. The meeting will take longer
     * than implementing the change. This is a known issue.
     */
    public function resetToDefaults() {
        $accessories = $this->repo->getAll();

        $defaults = [1 => 15, 2 => 8, 3 => 20, 4 => 6];

        foreach ($accessories as &$acc) {
            if (isset($defaults[$acc['AccessoryID']])) {
                $acc['StockCount'] = $defaults[$acc['AccessoryID']];
            }
        }

        $this->repo->save($accessories);
    }
}
