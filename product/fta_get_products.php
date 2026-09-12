<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/sql/query_fields/product_fields.php";
include_once __DIR__ . "/../utils/sql/query_fields/round_fields.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";
// setting CORS headers
new CorsHeaders("application/json; charset=UTF-8", "GET, OPTIONS", "Content-Type", "true");
$http_method = $_SERVER["REQUEST_METHOD"];

switch ($http_method) {
    case "GET":
        get_products($pdo);
        break;

    case "OPTIONS":
        http_response_code(204);
        exit;

    default:
        sendDisallowRequestMethodMessage($http_method);
}

function get_products(PDO $pdo): void
{
    $rou_id = isset($_GET["round_id"])
        ? (int) $_GET["round_id"]
        : 0;

    if ($rou_id <= 0) {
        sendErrorMessage(422, "Er is geen geldige rounde geselecteerd.", [
            "get" => $_GET['round_id']
        ]);
    }
    try{
        $round = (new GetData($pdo))->by_where(
            select: RoundFields::ALL,
            from: "fta_invite_rounds",
            from_alias: "invrou",
            where: ["invrou.invrou_id = :invrou_id"],
            execute: ["invrou_id" => $rou_id],
            fetch_once: true
        );

        if($round){
            $proloc_id = $round["proloc_id"];
            $products = (new GetData($pdo))->by_join(
                select: [
                    ...ProductFields::ALL_EXTRA_INFO,
                    "proloc.proloc_id"
                ],
                from: "fta_products_product_locations",
                from_alias:"proloc",
                joins:[
                    [
                        "type" => "INNER",
                        "table" => "fta_products",
                        "alias" => "pro",
                        "condition" => "proloc.pro_id = pro.pro_id" 
                    ],
                    [
                        "type" => "INNER",
                        "table" => "fta_product_categories",
                        "alias" => "procat",
                        "condition" => "pro.procat_id = procat.procat_id" 
                    ],
                    [
                        "type" => "INNER",
                        "table" => "fta_deposits",
                        "alias" => "dep",
                        "condition" => "pro.dep_id = dep.dep_id" 
                    ]
                ],
                where:["proloc.proloc_id = :proloc_id"],
                execute:["proloc_id" => $proloc_id],
                order_by:["pro.procat_id"]
            );
        }
        
        
        sendSuccessMessage("Succesvol", ["data" => [
                "products" => $products,
                "product_count" => count($products)
            ]
        ]);
    }
    catch(Throwable $exception){
        sendErrorMessageWithException(500, "Er is iets mis gegaan", $exception);
    }
   
}