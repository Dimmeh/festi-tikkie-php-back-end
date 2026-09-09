<?php
include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/sql/query_fields/event_fields.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";

// setting CORS headers
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

function get_data(PDO $pdo): void{
    $group_id = filter_input(
        INPUT_GET,
        "group_id",
        FILTER_VALIDATE_INT
    );

    if (
        $group_id === false ||
        ($group_id !== null && $group_id <= 0)
    ) {
        sendErrorMessage(
            422,
            "Geen geldige groep geselecteerd."
        );
    }

    $where = [];
    $execute = [];

    if ($group_id !== null) {
        $where[] = "e.gro_id = :gro_id";
        $execute["gro_id"] = $group_id;
    }

    try {
        $events = (new GetData($pdo))->by_join(
            select: EventFields::ALL_WITH_CURRENCY,
            from: "fta_events",
            from_alias: "e",
            joins: [
                [
                    "type" => "INNER",
                    "table" => "fta_currency",
                    "alias" => "c",
                    "condition" => "e.cur_id = c.cur_id"
                ]
            ],
            where: $where,
            execute: $execute,
            order_by: [
                "e.evn_created_at DESC"
            ]
        );

        sendSuccessMessage(
            "Succesvol",
            [
                "data" => [
                    "events" => $events,
                    "event_count" => count($events)
                ]
            ]
        );
    } catch (Throwable $exception) {
        sendErrorMessageWithException(
            500,
            "Er is iets mis gegaan",
            $exception
        );
    }
}