<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/update_data.php";

try {
    (new UpdateData($pdo))->update(
        table: "fta_invite_rounds",
        set: [
            "invrou_status = :invrou_status"
        ],
        where: [
            "invrou_expires_at <= NOW()",
            "invrou_status = 'open'"
        ],
        execute: [
            "invrou_status" => "expired"
        ]
    );
} catch (Throwable $exception) {
    sendErrorMessageWithException(500, "Update query kon niet uitgevoerd worden.", $exception);
}