<?php
    
    function sendErrorMessage(int $http_code, string $message, array $data = []): never{
        http_response_code($http_code);
        echo json_encode([
            "success" => false,
            "message" => $message,
            ...$data
        ]);
        exit;
    }
    function sendErrorMessageWithException(int $http_code, string $message, Throwable $exception, array $data = []): never{
        http_response_code($http_code);
        echo json_encode([
            "success" => false,
            "message" => $message,
            "exception" => [
                "error" => $exception->getMessage(),
                "file" => $exception->getFile(),
                "line" => $exception->getLine()
            ],
            ...$data
        ]);
        exit;
    }

    function sendSuccessMessage(string $message, array $data = [], int $http_code = 200): never{
        http_response_code($http_code);
        echo json_encode([
            "success" => true,
            "message" => $message,
            ...$data
        ]);
        exit;
    }

    function sendDisallowRequestMethodMessage(string $http_method){
        sendErrorMessage(405, "Deze requestmethode is niet toegestaan.", ["method" => $http_method]);
    };
