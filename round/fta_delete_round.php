<?php
include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";
// setting CORS headers
new CorsHeaders("application/json", "DELETE, OPTIONS", "Content-Type", "true");

$http_method = $_SERVER["REQUEST_METHOD"];

switch ($http_method) {
    case "DELETE":
        delete_data($pdo);
        break;
    case "OPTIONS":
        http_response_code(204);
        exit;
    default:
        sendDisallowRequestMethodMessage($http_method);

}

function delete_data(PDO $pdo): void
{

// INPUT_GET verwijst naar $_GET.
// "invrou_id" is de naam van $_GET["invrou_id"].
// FILTER_VALIDATE_INT controleert of de waarde een geldige integer is.
    $invrou_id = filter_input(
        INPUT_GET,
        "invrou_id",
        FILTER_VALIDATE_INT
    );

    if ($invrou_id === false || $invrou_id === null || $invrou_id <= 0) {
        sendErrorMessage(422, "Geen geldige ronde geselecteerd.");
    }

    try {
        $deleted_rows = (new DeleteData($pdo))->delete(
            from: "fta_invite_rounds",
            where: ["invrou_id = :invrou_id"],
            execute: ["invrou_id" => $invrou_id]
        );

        if ($deleted_rows === 0) {
            sendErrorMessage(404, "De ronde bestaat niet.");
        }

        sendSuccessMessage("De ronde is succesvol verwijderd.");
    } catch (Throwable $exception) {
        sendErrorMessageWithException(500, "De ronde kon niet worden verwijderd.", $exception);
    }
}