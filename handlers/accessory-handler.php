<?php
// Same error_reporting suppression as bike-handler.php.
// AccessoryService uses create_function() and FILTER_SANITIZE_STRING, both of which
// PHP 7 will complain about loudly if you let it.
// We do not let it. Silence, deprecated functions. You still work. That's enough.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

/**
 * accessory-handler.php
 *
 * GET: return accessories (all, or filtered by bikeType).
 * POST: process an order. Validate stock. Apply bundle deal. Deduct. Save. Return JSON.
 *
 * In .NET this was an ApiController with [HttpGet] and [HttpPost] attributes,
 * model binding, and a pipeline that handled content negotiation automatically.
 * Here it is an if/else on $_SERVER['REQUEST_METHOD'].
 * Same outcome. Fewer annotations. More character.
 */

require_once __DIR__ . '/../data/BeachCruiserRepository.php';
require_once __DIR__ . '/../data/MountainBikeRepository.php';
require_once __DIR__ . '/../data/AccessoryRepository.php';
require_once __DIR__ . '/../services/BeachCruiserService.php';
require_once __DIR__ . '/../services/MountainBikeService.php';
require_once __DIR__ . '/../services/AccessoryService.php';
require_once __DIR__ . '/../services/ApplicationServices.php';

$dataFolder = __DIR__ . '/../SampleData';
ApplicationServices::initialize($dataFolder);

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return accessories, optionally filtered by bikeType.
    // getCompatibleWith() uses create_function() internally, which PHP would like you to know
    // it finds distasteful. We've asked it to keep its opinions to itself (see top of file).
    $accessoryService = ApplicationServices::getAccessoryService();

    if (isset($_GET['bikeType']) && $_GET['bikeType'] !== '') {
        $accessories = $accessoryService->getCompatibleWith($_GET['bikeType']);
    } else {
        $accessories = $accessoryService->getAll();
    }

    // Sanitize string fields. htmlspecialchars() is the old reliable bouncer at the XSS door.
    // Not glamorous. Does the job.
    $result = [];
    foreach ($accessories as $acc) {
        $result[] = [
            'AccessoryID'    => intval($acc['AccessoryID']),
            'Name'           => htmlspecialchars($acc['Name'],        ENT_QUOTES, 'UTF-8'),
            'Category'       => htmlspecialchars($acc['Category'],    ENT_QUOTES, 'UTF-8'),
            'Description'    => htmlspecialchars($acc['Description'], ENT_QUOTES, 'UTF-8'),
            'UnitPrice'      => floatval($acc['UnitPrice']),
            'StockCount'     => intval($acc['StockCount']),
            'CompatibleWith' => $acc['CompatibleWith'],
        ];
    }

    echo json_encode($result);

} elseif ($method === 'POST') {
    // Process an accessory order.
    // @ on json_decode: see bike-handler.php for the full philosophical treatise.
    // Short version: it suppresses warnings, it's been discouraged for 15 years,
    // and it absolutely still works. The PHP manual sighs every time.
    $body = file_get_contents('php://input');
    $data = @json_decode($body, true); // @ error suppressor. Old faithful. Tired but functional.

    if ($data === null || !is_array($data)) {
        http_response_code(400);
        echo json_encode([
            'Success'              => false,
            'Message'              => 'Invalid JSON body. Expected an array of {AccessoryID, Quantity} objects. Got something else entirely.',
            'TotalPrice'           => 0.0,
            'DiscountAmount'       => 0.0,
            'BundleDiscountApplied' => false,
        ]);
        exit;
    }

    $result = ApplicationServices::getAccessoryService()->processOrder($data);
    echo json_encode($result);

} else {
    http_response_code(405);
    echo json_encode([
        'Success'  => false,
        'Message'  => 'Method not allowed. GET to browse. POST to buy. ' . $method . ' to confuse everyone.',
    ]);
}
