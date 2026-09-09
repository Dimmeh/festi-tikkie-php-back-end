<?php
include_once __DIR__ . "/../config/cors_headers.php";
include_once __DIR__ . "/../config/session_config.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";

// setting CORS headers
new CorsHeaders("application/json", "POST, OPTIONS", "Content-Type", "true");
$http_method = $_SERVER["REQUEST_METHOD"];
switch($http_method){
  case "POST":
    logout();
    break;
  case "OPTIONS":
    http_response_code(204);
    exit;
  default:
    sendDisallowRequestMethodMessage($http_method);
}

function logout(){
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $cookieParams = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            [
                "expires" => time() - 3600,
                "path" => $cookieParams["path"],
                "domain" => $cookieParams["domain"],
                "secure" => $cookieParams["secure"],
                "httponly" => $cookieParams["httponly"],
                "samesite" => $cookieParams["samesite"],
            ]
        );
    }

    session_destroy();
    sendSuccessMessage("Je bent uitgelogd.");
}