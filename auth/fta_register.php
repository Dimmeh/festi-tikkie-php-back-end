<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/image/configure_image.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";

// setting CORS headers
new CorsHeaders("application/json", "POST, OPTIONS", "Content-Type", "true");
$http_method = $_SERVER['REQUEST_METHOD'];

switch($http_method){
  case "POST":
    postData($pdo, $_POST, $_FILES);
    break;
  case "OPTIONS":
    http_response_code(200);
    exit;
  default:
    sendDisallowRequestMethodMessage($http_method);
}

function postData(PDO $pdo, array $data, array $files){

  $usr_name = $data["name"] ?? "";
  $usr_email = $data["email"] ?? "";
  $usr_password = $data["password"] ?? "";
  $usr_profile_photo = $files["profile_photo"] ?? null;
  
  if($usr_name === "" || $usr_email === "" || $usr_password === ""){
    sendErrorMessage(422, "Niet alle verplicte velden zijn ingevuld.");
  }

  if(!filter_var($usr_email, FILTER_VALIDATE_EMAIL)){
    sendErrorMessage(422, "Ongeldige e-mailadres.", ["data" => $usr_email]);
  }

  if ($usr_profile_photo === null || $usr_profile_photo["error"] !== UPLOAD_ERR_OK) {
    sendErrorMessage(500, "De profielfoto kon niet worden geüploaded.");
  }

  $usr_password_hash = password_hash($usr_password, PASSWORD_DEFAULT);

  $user = array(
    "name" => $usr_name,
    "password_hash" => $usr_password_hash,
    "email" => $usr_email,
    "profile_photo_url" => configureImage($usr_profile_photo),
    "code" => ""
  );

    createUser($pdo, $user);
}

function createFriendCode(){
    return str_pad(
      (string) random_int(0, 999999),
      6,
      "0",
      STR_PAD_LEFT
    );
}

function createUser(PDO $pdo, array $user){
  header("Content-Type: application/json");
  $usr_name = $user['name'];
  $usr_password_hash = $user['password_hash'];
  $usr_email = $user['email'];
  $usr_profile_photo_url = $user['profile_photo_url'];
  $usr_code = createFriendCode();
  try{
    $last_id = (new PostData($pdo))->insert(
      table: "fta_users",
      data: [
        "usr_name" => $usr_name,
        "usr_password_hash" => $usr_password_hash,
        "usr_email" => $usr_email,
        "usr_profile_photo_url" => $usr_profile_photo_url,
        "usr_code" => $usr_code
      ],
      return_last_insert_id:true
    );
    // echo $stmt; 
    sendSuccessMessage("Je account is aangemaakt.", ["last_id" => $last_id]);

  }catch(PDOException $exception){
    $errorMessage = $exception->errorInfo[2] ?? "";
    if(str_contains($errorMessage, 'usr_code')){
      createUser($pdo, $user);
    }
    elseif(str_contains($errorMessage, 'usr_email')){
      sendErrorMessageWithException(409, "Het e-mailadres is al gebruikt.", $exception);
    }
    elseif(str_contains($errorMessage, 'usr_name')){
      sendErrorMessageWithException(409, "De naam is al gebruikt.", $exception);
    }
    else{
      sendErrorMessageWithException(403, "Er is iets mis. Controleer je code.", $exception);
    }
  }
}