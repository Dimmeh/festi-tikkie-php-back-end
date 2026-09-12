<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";
include_once __DIR__ . "/../utils/sql/query_fields/balance_fields.php";

new CorsHeaders("application/json", "GET, OPTIONS", "Content-Type", "true");

$http_method = $_SERVER["REQUEST_METHOD"];

switch ($http_method) {
    case "GET":
        get_data($pdo, $_POST);
        break;
    case "OPTIONS":
        http_response_code(204);
        exit;
    default:
        sendDisallowRequestMethodMessage($http_method);
}

function get_data(PDO $pdo){
    $usr_id = $_SESSION["usr_id"] ?? $_GET["usr_id_test"];
    if (
        (!is_numeric($usr_id) ||
        (int) $usr_id <= 0)
    ) {
        sendErrorMessage(401, "Je moet ingelogd zijn om een ronde te starten.", [
            "sessionId" => $_SESSION["usr_id"],
            "dataId" => $_GET["user_id_test"],
            "evnId" => $_GET["event_id"]
        ]);
    }

    $usr_id = (int) $usr_id;
    

    try{
        $balance_per_user_in_group = [];
        $balances = (new GetData($pdo)->by_where(
            select: BalanceFields::SUM_BY_CREATOR,
            from: "fta_debts",
            from_alias: "deb",
            where: ["deb.invrou_creator_id = :creator_id"],
            execute: ['creator_id' => $usr_id],
            group_by: ["deb.invusr_id"]
        ));

        if(is_array($balances)){
            $balance_per_user_in_group = [...$balances];
            $debts_per_user = (new GetData($pdo)->by_where(
                select: BalanceFields::SUM_BY_INVITED_USER,
                from: "fta_debts",
                from_alias: "deb",
                where: ["deb.invusr_id = :creator_id"],
                execute: ['creator_id' => $usr_id],
                group_by: ["deb.invrou_creator_id"]
            ));
            if(is_array($debts_per_user)){
                foreach($balance_per_user_in_group as &$usr){
                    $user_debt = array_find(
                        $debts_per_user,
                        fn($user) => (int) $user["usr_id"] === $usr["usr_id"]
                    );
                    if($user_debt !== null){
                        $usr["deb_amount"] = number_format(
                            (float) $usr["deb_amount"] - (float) $user_debt["deb_amount"],
                            2,
                            ".",
                            ""
                        );
                    }
                }
            }
        }
        sendSuccessMessage('Succesvol order opgehaald', [
            "data" => [
                "balances" => $balance_per_user_in_group
            ]
        ]);
    }
    catch(Throwable $exception){
        sendErrorMessageWithException(500, "Er is iets mis gegaan", $exception);
    }
}