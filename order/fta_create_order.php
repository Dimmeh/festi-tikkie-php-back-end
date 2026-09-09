<?php
include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";
// setting CORS headers
new CorsHeaders("application/json", "POST, OPTIONS", "Content-Type", "true");

$http_method = $_SERVER["REQUEST_METHOD"];

switch ($http_method) {
    case "POST":
        post_data($pdo, $_POST);
        break;
    case "OPTIONS":
        http_response_code(204);
        exit;
    default:
        sendDisallowRequestMethodMessage($http_method);

}

function post_data(PDO $pdo, array $data): void
{
    $usr_id = $_SESSION["usr_id"] ?? $data["user_id_test"];
    if (
        (!is_numeric($usr_id) ||
        (int) $usr_id <= 0)
    ) {
        sendErrorMessage(401, "Je moet ingelogd zijn om een ronde te starten.", [
            "sessionId" => $_SESSION["usr_id"],
            "dataId" => $data["user_id_test"],
            "evnId" => $data["event_id"]
        ]);
    }

    $usr_id = (int) $usr_id;


    $invrou_id = $data["invrou_id"] ?? null;

    if ($invrou_id === null || !is_numeric($invrou_id)) {
        sendErrorMessage(422, "Er is geen geldige groep geselecteerd.", [
            "invrou_id" => $invrou_id,
            "data" => $data
        ]);
    }

    $invrou_id = (int) $invrou_id;

    $products_json = $data["products"] ?? null;
    if ($products_json === null) {
        sendErrorMessage(
            422,
            "Er zijn geen producten ontvangen.",
            [
                "products" => $products_json
            ]
        );
    }

    $products = json_decode(
        $products_json,
        true
    );
    if (!is_array($products) || empty($products)) {
        sendErrorMessage(
            422,
            "Er zijn geen geldige producten geselecteerd.",
            [
                "products" => $products,
                "products_raw" => $products_json
            ]
        );
    }

    create_order(
        $pdo,
        $usr_id,
        $invrou_id,
        $products
    );
}

function create_order(
    PDO $pdo,
    int $usr_id,
    int $invrou_id,
    array $products
): void {
        try{
            foreach($products as $product){
                (new PostData($pdo))->insert(
                    table:"fta_ordered_products",
                    data:[
                        "invrou_id" => $invrou_id,
                        "usr_id" => $usr_id,
                        "pro_id" => $product["pro_id"],
                        "ordpro_amount" => $product["product_amount"] 
                    ]
                );
            }
            $round_invite = (new GetData($pdo))->by_where(
                select: ['invrou_creator_id'],
                from: "fta_invite_rounds",
                where: ["invrou_id = :invrou_id"],
                execute:["invrou_id" => $invrou_id],
                fetch_once: true
            );
            sendSuccessMessage("Order is geplaatst", [
                "data" => [
                    "is_creator" => $round_invite['invrou_creator_id'] == $usr_id 
                ]
            ]);
        }
        catch(Throwable $exception){
            sendErrorMessageWithException(500, "Er is iets mis gegaan", $exception);
        }

}