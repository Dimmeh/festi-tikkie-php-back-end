<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/sql/query_fields/user_fields.php";
// setting CORS headers
new CorsHeaders("application/json", "GET, OPTIONS", "Content-Type, Authorization", "true");

$http_method = $_SERVER["REQUEST_METHOD"];
switch($http_method){
  case "GET":
    authCheck($pdo);
    break;
  case "OPTIONS":
    http_response_code(204);
    break;
  default:
    sendErrorMessage(405, "Deze requestmethode is niet toegestaan.", ["method" => $http_method]);
}

function authCheck(PDO $pdo){
    if (!isset($_SESSION["usr_id"])) {
        sendErrorMessage(401, "Je bent niet ingelogd.");
    }
    $user = (new GetData($pdo)->by_where(
                select: UserFields::ALL_WITHOUT_PASSWORD, 
                from: "fta_users", 
                where: ["usr_id = :usr_id"],
                execute: ["usr_id" => $_SESSION["usr_id"]],
                fetch_once: true
            ));

    if (!$user) {
        $_SESSION = [];
        session_destroy();
        sendErrorMessage(401, "De gebruiker bestaat niet meer.");
    }
    sendSuccessMessage("Toegang accepteerd", ["user" => $user]);
}

