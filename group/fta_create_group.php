<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/image/configure_image.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";

// setting CORS headers
new CorsHeaders("application/json", "POST, OPTIONS", "Content-Type, Authorization", "true");
$http_method = $_SERVER["REQUEST_METHOD"];

switch ($http_method) {
    case "POST":
        post_data($pdo, $_POST, $_FILES);
        break;

    case "OPTIONS":
        http_response_code(204);
        exit;

    default:
        sendDisallowRequestMethodMessage($http_method);
}

function post_data(
    PDO $pdo,
    array $data,
    array $files
): void {
    $gro_name = trim($data["name"] ?? "");
    $gro_member_ids_json = $data["member_ids"] ?? "[]";
    $gro_profile_photo = $files["profile_photo"] ?? null;

    /*
     * Gebruik bij voorkeur de ingelogde gebruiker uit de sessie.
     * Pas "user_id" eventueel aan naar de naam die jij in
     * $_SESSION gebruikt.
     */
    $gro_creator_id = $_SESSION["usr_id"] ?? null;

    if (
        $gro_name === "" ||
        !is_numeric($gro_creator_id) ||
        (int) $gro_creator_id <= 0
    ) {
        sendErrorMessage(422, "Niet alle verplicte velden zijn ingevuld.");
    }

    $gro_creator_id = (int) $gro_creator_id;

    $gro_member_ids = json_decode(
        $gro_member_ids_json,
        true
    );

    if (!is_array($gro_member_ids)) {
        sendErrorMessage(422, "De vriendenlijst heeft een ongeldig formaat.");
    }

    /*
     * Zet alle waarden om naar integers.
     * Verwijder ongeldige en dubbele user-ID's.
     */
    $gro_member_ids = array_values(
        array_unique(
            array_filter(
                array_map(
                    "intval",
                    $gro_member_ids
                ),
                static fn (int $usr_id): bool => $usr_id > 0
            )
        )
    );

    /*
     * Voeg de maker van de groep automatisch toe
     * als die nog niet in de ledenlijst staat.
     */
    if (
        !in_array(
            $gro_creator_id,
            $gro_member_ids,
            true
        )
    ) {
        $gro_member_ids[] = $gro_creator_id;
    }

    if (
        $gro_profile_photo === null ||
        (
            $gro_profile_photo["error"] ??
            UPLOAD_ERR_NO_FILE
        ) !== UPLOAD_ERR_OK
    ) {
        sendErrorMessage(422, "De profielfoto kon niet worden geüpload.");
    }

    try {
        $gro_profile_photo_url = configureImage(
            $gro_profile_photo,
            "group-images"
        );

        $group = [
            "name" => $gro_name,
            "member_ids" => $gro_member_ids,
            "creator_id" => $gro_creator_id,
            "profile_photo_url" => $gro_profile_photo_url,
        ];

        create_group(
            $pdo,
            $group
        );
    } 
    catch (Throwable $exception) {
        sendErrorMessageWithException(500, "Er is een onverwachte fout opgetreden.", $exception);
    }
}

function create_group(
    PDO $pdo,
    array $group
): void {
    $gro_name = $group["name"];
    $gro_member_ids = $group["member_ids"];
    $gro_creator_id = $group["creator_id"];
    $gro_profile_photo_url = $group["profile_photo_url"];

    try {
        $pdo->beginTransaction();

        $post_data = new PostData($pdo);

        $created_group_id = (int) $post_data->insert(
            table: "fta_groups",
            data: [
                "gro_name" => $gro_name,
                "gro_profile_photo_url" => $gro_profile_photo_url,
                "gro_creator_id" => $gro_creator_id
            ],
            return_last_insert_id: true
        );

        if ($gro_member_ids !== []) {
            foreach ($gro_member_ids as $usr_id) {
                $post_data->insert(
                    table: "fta_group_users",
                    data: [
                        "gro_id" => $created_group_id,
                        "usr_id" => $usr_id
                    ]
                );
            }
        }

        $pdo->commit();

        sendSuccessMessage(
            "De groep is aangemaakt",
            [
                "data" => [
                    "group_id" => $created_group_id
                ]
            ]
        );
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendErrorMessageWithException(500, "Er is iets mis gegaan", $exception);
    }
}