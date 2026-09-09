<?php

class CorsHeaders
{
    private string $cors_header_origin = "http://localhost:5173";

    public function __construct(
        string $content_type,
        string $methods,
        string $headers,
        string $credentials
    ) {
        header("Access-Control-Allow-Origin: " . $this->cors_header_origin);

        if ($content_type !== "") {
            header("Content-Type: " . $content_type);
        }

        if ($methods !== "") {
            header("Access-Control-Allow-Methods: " . $methods);
        }

        if ($headers !== "") {
            header("Access-Control-Allow-Headers: " . $headers);
        }

        if ($credentials !== "") {
            header("Access-Control-Allow-Credentials: " . $credentials);
        }
    }
}