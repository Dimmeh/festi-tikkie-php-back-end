<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/image/configure_image.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";

// setting CORS headers
new CorsHeaders("application/json", "POST, OPTIONS", "Content-Type, Authorization", "true");

$http_method = $_SERVER['REQUEST_METHOD'];

switch($http_method){
  case "POST":
    postData($pdo, $_POST, $_FILES);
    break;
  case "OPTIONS":
    http_response_code(204);
    exit;
  default:
    sendDisallowRequestMethodMessage($http_method);
}

function postData(PDO $pdo, array $data, array $files){
    $usr_name = $data["name"] ?? "";
    $usr_email = $data["email"] ?? "";
    $usr_password = $data["password"] ?? "";
    $usr_id = $data["id"] ??"";
    $usr_profile_photo = $files["profile_photo"] ?? null;
    if($usr_name === "" || $usr_email === ""){
        sendErrorMessage(422, "Niet alle verplicte velden zijn ingevuld.");
    }

    if(!filter_var($usr_email, FILTER_VALIDATE_EMAIL)){
        sendErrorMessage(422, "Ongeldige e-mailadres.", ["data" => $usr_email]);
    }
    
    $user = array(
        "name" => $usr_name,
        "email" => $usr_email,
        "profile_photo_url" => null
    );

    $current_user = [];

    if (!empty($usr_profile_photo)) {
        $current_user = (new GetData($pdo))->by_where(
            select: ["usr_profile_photo_url"],
            from: "fta_users",
            where: ["usr_id = :usr_id"],
            execute: ["usr_id"=> $_SESSION['usr_id']],
            fetch_once: true
        );
        $user["profile_photo_url"] = configureImage($usr_profile_photo);
    }
    
    if(isset($usr_id)) {
        $user["usr_id"] = $usr_id;
    }
    
    if(isset($usr_password)) {
        $user["password"] = $usr_password;
    }
    editUser($pdo, $user, $current_user);
}


function editUser(PDO $pdo, array $user, array $current_user): void
{
    $update_fields = [
        "usr_name = :usr_name",
        "usr_email = :usr_email",
    ];

    $parameters = [
        "usr_name" => $user["name"],
        "usr_email" => $user["email"],
        "user_id" => $_SESSION["usr_id"] ?? $user["usr_id"]
    ];

    if (!empty($user["password"])) {
        $update_fields[] = "usr_password_hash = :password_hash";

        $parameters["password_hash"] = password_hash(
            $user["password"],
            PASSWORD_DEFAULT
        );
    }

    if ($user["profile_photo_url"] !== null) {
        $update_fields[] = "usr_profile_photo_url = :profile_photo_url";

        $parameters["profile_photo_url"] = $user["profile_photo_url"];
    }

    try {
        (new UpdateData($pdo))->update(
            table: "fta_users",
            set: $update_fields,
            where: [
                "usr_id = :user_id"
            ],
            execute: $parameters
        );

        $old_profile_photo_url = $current_user["usr_profile_photo_url"];

        if (
            $user["profile_photo_url"] !== null
            && !empty($old_profile_photo_url)
            && $old_profile_photo_url !== $user["profile_photo_url"]
        ) {
            deleteOldProfilePhoto($old_profile_photo_url);
        }

        sendSuccessMessage("Je account is bijgewerkt.");
    } catch (PDOException $exception) {
        handleDatabaseError($exception);

        if ($user["profile_photo_url"] !== null) {
            deleteOldProfilePhoto($user["profile_photo_url"]);
        }
    }
}

function deleteOldProfilePhoto(string $imagePath): void
{
    if ($imagePath === "") {
        return;
    }

    $absolutePath = dirname(__DIR__, 2) . $imagePath;

    if (is_file($absolutePath)) {
        unlink($absolutePath);
    }
}

function handleDatabaseError(
    PDOException $exception
): void {
    $errorMessage =
        $exception->errorInfo[2]
        ?? $exception->getMessage();

    if (str_contains($errorMessage, "usr_email")) {
        sendErrorMessage(409, "Dit e-mailadres is al in gebruik.");
    }

    if (str_contains($errorMessage, "usr_name")) {
        sendErrorMessage(409, "Deze naam is al in gebruik.");
    }
    sendErrorMessageWithException(500, "Het account kon niet worden bijgewerkt.", $exception);
}