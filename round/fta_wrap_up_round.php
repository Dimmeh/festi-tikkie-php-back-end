<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";

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

function post_data(PDO $pdo, array $data){
    $usr_id = $_SESSION["usr_id"] ?? $data["usr_id_test"];
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
    $invrou_id = filter_input(
        INPUT_POST,
        "invrou_id",
        FILTER_VALIDATE_INT
    );

    if (!$invrou_id || $invrou_id <= 0) {
        sendErrorMessage(400, "Er is geen geldige rondje geselecteerd.");
    }


    if (!isset($data['order_with_users'])) {
        sendErrorMessage(400, "Er is geen data verstuurd.");
    }
    
    $json = json_decode($data['order_with_users'], true);

    if(!$json || count($json) === 0){
        sendErrorMessage(403, "Er is geen data verstuurd.");
    }

    try{
        foreach ($json as $order) {
            if($order["usr_id"] != $_SESSION["usr_id"]){
                (new PostData($pdo))->insert(
                    table:"fta_debts",
                    data: [
                        "invrou_id" => $invrou_id,
                        "invrou_creator_id" => $_SESSION["usr_id"],
                        "invusr_id" => $order["usr_id"],
                        "deb_amount"=> $order["usr_total_price"]
                    ]
                );
            }
        }

        (new UpdateData($pdo))->update(
            table: "fta_invite_rounds",
            set: ["invrou_status = :invrou_status"],
            where: ["invrou_id = :invrou_id"],
            execute: [
                "invrou_status" => "completed",
                "invrou_id" => $invrou_id
            ]
        );

        sendSuccessMessage('Succesvol data opgeslagen');
    }
    catch(Throwable $exception){
        sendErrorMessageWithException(500, "Er is iets mis gegaan", $exception);
    }
}