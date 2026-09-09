<?php
include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";
// setting CORS headers
new CorsHeaders("application/json", "POST, OPTIONS", "Content-Type", "true");

$http_method = $_SERVER["REQUEST_METHOD"];

switch ($http_method) {
    case "GET":
        ping_data($pdo);
        break;
    case "OPTIONS":
        http_response_code(204);
        exit;
    default:
        sendDisallowRequestMethodMessage($http_method);

}

function ping_data(PDO $pdo): void
{
    $invrou_id = filter_input(
        INPUT_GET,
        "invrou_id",
        FILTER_VALIDATE_INT
    );

    if (!$invrou_id || $invrou_id <= 0) {
        sendErrorMessage(400, "Er is geen geldige rondje geselecteerd.");
    }

    try{
        $users = (new GetData($pdo))->by_where(
            select: [
                "COUNT(*) AS total_users"
            ],
            from: "fta_invited_users",
            where: ["invrou_id = :invrou_id"],
            execute:["invrou_id" => $invrou_id],
            fetch_once:true
        );
        $orders = (new GetData($pdo))->by_where(
            select: [
                "COUNT(DISTINCT usr_id) AS total_orders"
            ],
            from: "fta_ordered_products",
            where: ["invrou_id = :invrou_id"],
            execute:["invrou_id" => $invrou_id],
            fetch_once:true
        );

        sendSuccessMessage("Succesvol data opgehaald", [
            "data"=> [
                "total_users" => (int) $users['total_users'],
                "total_orders" => (int) $orders['total_orders'],
            ]
        ]);
    }
    catch(Throwable $exception){
        sendErrorMessageWithException(500, "Er is iets mis gegaan", $exception);
    }
}