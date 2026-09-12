<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";
include_once __DIR__ . "/../utils/sql/query_fields/user_fields.php";

// setting CORS headers
new CorsHeaders("application/json", "POST, OPTIONS", "Content-Type, Authorization", "true");
$http_method = $_SERVER['REQUEST_METHOD'];

switch($http_method){
  case "POST":
    authUser($pdo, $_POST);
    break;
  case "OPTIONS":
    http_response_code(204);
    exit;
  default:
    sendDisallowRequestMethodMessage($http_method);
}

function authUser(PDO $pdo, array $data){

  $usr_email = trim($data["usr_email"] ?? "");
  $usr_password = trim($data["usr_password"] ?? "");
  
  // Controleren of de variabelen zijn gevuld.
  if($usr_email === "" || $usr_password === ""){
    sendErrorMessage(401, "Ongeldinge e-mailadres of wachtwoord!. Probeer het nog een keer.");
    exit;
  }

  // Controleren of het emailadres correct is.
  if(!filter_var($usr_email, FILTER_VALIDATE_EMAIL)){
    sendErrorMessage(401, "Ongeldinge e-mailadres of wachtwoord!!. Probeer het nog een keer.");
    exit;
  }
  $user = (new GetData($pdo)->by_where(
            select: UserFields::ALL,
            from: "fta_users",
            from_alias:"usr",
            where: ["usr.usr_email = :usr_email"],
            execute: ["usr_email" => $usr_email],
            fetch_once: true
          ));
  // Controleer of de user bestaat en het wachtwoord correct is.
  if(!$user || !password_verify($usr_password, $user["usr_password_hash"])){
    sendErrorMessage(401, "Ongeldinge e-mailadres of wachtwoord!!!. Probeer het nog een keer.",
    [
      "user" => $user,
      "valid_password" => password_verify($usr_password, $user["usr_password_hash"]) 
    ]);
  }

  // Genereer een nieuwe sessie id.
  session_regenerate_id(true);

  // Bewaar alleen usr_id.
  $_SESSION["usr_id"] = (int) $user["usr_id"];

  // Haal wachtwoord uit het object. Wachtwoord mag nooit verstuurd worden.
  unset($user["usr_password_hash"]);

  // Verstuur data en succesmelding.
  sendSuccessMessage("Je bent aangemeld.", ["user" => $user,"session" => $_SESSION]);
}