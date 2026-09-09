<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";
include_once __DIR__ . "/../utils/sql/query_fields/product_fields.php";

new CorsHeaders("application/json", "GET, OPTIONS", "Content-Type", "true");

$http_method = $_SERVER["REQUEST_METHOD"];

switch ($http_method) {
    case "GET":
        get_data($pdo);
        break;
    case "OPTIONS":
        http_response_code(204);
        exit;
    default:
        sendDisallowRequestMethodMessage($http_method);
}

function get_data(PDO $pdo){
    $invrou_id = filter_input(
        INPUT_GET,
        "invrou_id",
        FILTER_VALIDATE_INT
    );

    if (!$invrou_id || $invrou_id <= 0) {
        sendErrorMessage(400, "Er is geen geldige rondje geselecteerd.");
    }

    try{
        $orders = (new GetData($pdo))->by_join(
            select: ProductFields::PRODUCT_WITH_USER,
            from: "fta_ordered_products",
            from_alias: "ordpro",
            joins: [
                [
                    "type" => "INNER",
                    "table" => "fta_users",
                    "alias" => "usr",
                    "condition" => "ordpro.usr_id = usr.usr_id"
                ],
                [
                    "type" => "INNER",
                    "table" => "fta_products",
                    "alias" => "pro",
                    "condition" => "ordpro.pro_id = pro.pro_id"
                ]
            ],
            where: ["invrou_id = :invrou_id"],
            execute: ["invrou_id" => $invrou_id]
        );

        sendSuccessMessage('Succesvol order opgehaald', [
            "data" => [
                ...$orders
            ]
        ]);
    }
    catch(Throwable $exception){
        sendErrorMessageWithException(500, "Er is iets mis gegaan", $exception);
    }
}