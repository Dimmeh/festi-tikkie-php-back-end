<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../sql/query_fields/user_fields.php";

new CorsHeaders('application/json', 'GET, OPTIONS', 'Content-Type', 'true');

$http_method = $_SERVER["REQUEST_METHOD"];
switch($http_method){
  case "GET":
    $friendcode = isset($_GET["friendcode"]) ?? "";
    friendcodeCheck($pdo, $friendcode);
    break;
  case "OPTIONS":
    http_response_code(204);
    break;
  default:
    sendErrorMessage(405, "Deze requestmethode is niet toegestaan.", ["method" => $http_method]);
}

function friendcodeCheck($pdo, $friendcode){
   
    if(!$friendcode){
      sendErrorMessage(405, "Vul een geldige friendcode in.");
    }      

    $user = (new GetData($pdo)->by_where(
      select: UserFields::ALL_WITHOUT_PASSWORD, 
      from: "fta_users",
      from_alias: "usr",
      where: ["usr = :friendcode", "usr_id != :usr_id"],
      execute: ["friendcode" => $friendcode, "usr_id" => $_SESSION["usr_id"]],
      fetch_once: true));

    if(!$user || count($user) === 0){
      sendErrorMessage(403, "Ongeldige code.", [
        'user' => $user,
        'session' => $_SESSION,
        'friendcode' => $friendcode
      ]);
    }

    sendSuccessMessage("Gebruiker gevonden", ['user' => $user]);
}