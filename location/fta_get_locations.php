<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/sql/query_fields/location_fields.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";
// setting CORS headers
new CorsHeaders("application/json; charset=UTF-8", "GET, OPTIONS", "Content-Type", "true");
$http_method = $_SERVER["REQUEST_METHOD"];

switch ($http_method) {
    case "GET":
        get_locations($pdo);
        break;

    case "OPTIONS":
        http_response_code(204);
        exit;

    default:
        sendDisallowRequestMethodMessage($http_method);
}

function get_locations(PDO $pdo): void
{
    $evn_id = isset($_GET["evn_id"])
        ? (int) $_GET["evn_id"]
        : 0;

    if ($evn_id <= 0) {
        sendErrorMessage(422, "Er is geen geldige event geselecteerd.", [
            "get" => $_GET['evn_id']
        ]);
    }
    try{
        $locations = (new GetData($pdo))->by_where(
            select: LocationFields::ALL,
            from: "fta_product_locations",
            from_alias: "proloc",
            where:["evn_id = :evn_id"],
            execute:["evn_id" => $evn_id],
            order_by:["proloc_name ASC"]
        );
        
        sendSuccessMessage("Succesvol", ["data" => [
                "locations" => $locations,
                "location_count" => count($locations)
            ]
        ]);
    }
    catch(Throwable $exception){
        sendErrorMessageWithException(500, "Er is iets mis gegaan", $exception);
    }
   
}