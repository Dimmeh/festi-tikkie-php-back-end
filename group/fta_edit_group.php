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
    $gro_id = filter_var(
        $data["id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $gro_name = trim($data["name"] ?? "");
    $gro_member_ids_json = $data["member_ids"] ?? "[]";
    $gro_profile_photo = $files["profile_photo"] ?? null;

    /*
     * Gebruik bij voorkeur de ingelogde gebruiker uit de sessie
     * om te controleren of die deze groep mag aanpassen.
     */
    $usr_id = $_SESSION["usr_id"] ?? (int) $data["usr_id_test"];

    if (
        $gro_id === false ||
        $gro_id === null ||
        $gro_id <= 0 ||
        $gro_name === "" ||
        !is_numeric($usr_id)
    ) {
        http_response_code(422);

        echo json_encode([
            "success" => false,
            "message" => "Niet alle verplichte velden zijn geldig ingevuld.",
            "data" => [
                "id" => $gro_id,
                "name"=> $gro_name,
                "members"=> $gro_member_ids_json,
                "photo" => $gro_profile_photo,
                "creatorId" => $data["creator_id"]
            ]
        ]);

        return;
    }

    $usr_id = (int) $usr_id;

    $gro_member_ids = json_decode(
        $gro_member_ids_json,
        true
    );

    if (!is_array($gro_member_ids)) {
        http_response_code(422);

        echo json_encode([
            "success" => false,
            "message" => "De vriendenlijst heeft een ongeldig formaat.",
        ]);

        return;
    }

    $gro_member_ids = array_values(
        array_unique(
            array_filter(
                array_map(
                    "intval",
                    $gro_member_ids
                ),
                static fn (int $member_id): bool =>
                    $member_id > 0
            )
        )
    );

    $current_group = (new GetData($pdo)->by_where(
        select: ["gro_id", "gro_creator_id", "gro_profile_photo_url"],
        from: "fta_groups",
        from_alias: "gro",
        where: ["gro_id = :gro_id"],
        execute: ["gro_id" => $gro_id],
        fetch_once: true
    ));

    if (!$current_group) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "De groep bestaat niet.",
        ]);

        return;
    }

    if ((int) $current_group["gro_creator_id"] !== $usr_id) {
        // http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Je mag deze groep niet bewerken.",
            "data" => [
                "creatorId"=> $current_group["gro_creator_id"],
                "usr_id" => $usr_id,
                "session" => $_SESSION
            ]
        ]);

        return;
    }

    $new_profile_photo_url = null;

    try {
        if (
            $gro_profile_photo !== null &&
            (
                $gro_profile_photo["error"] ??
                UPLOAD_ERR_NO_FILE
            ) === UPLOAD_ERR_OK
        ) {
            $new_profile_photo_url = configureImage(
                $gro_profile_photo,
                "group-images"
            );
        }

        edit_group(
            $pdo,
            [
                "gro_id" => $gro_id,
                "gro_name" => $gro_name,
                "member_ids" => $gro_member_ids,
                "profile_photo_url" => $new_profile_photo_url,
            ],
            $current_group
        );
    } catch (RuntimeException $exception) {
        if ($new_profile_photo_url !== null) {
            delete_profile_photo(
                $new_profile_photo_url
            );
        }

        http_response_code(422);

        echo json_encode([
            "success" => false,
            "message" => $exception->getMessage(),
        ]);
    } catch (Throwable $exception) {
        if ($new_profile_photo_url !== null) {
            delete_profile_photo(
                $new_profile_photo_url
            );
        }

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "De groep kon niet worden bijgewerkt.",
        ]);
    }
}

function edit_group(
    PDO $pdo,
    array $group,
    array $current_group
): void {
    $gro_id = $group["gro_id"];
    $gro_name = $group["gro_name"];
    $new_member_ids = $group["member_ids"];
    $new_profile_photo_url =
        $group["profile_photo_url"];

    $old_profile_photo_url =
        $current_group["gro_profile_photo_url"] ?? null;

    try {
        $pdo->beginTransaction();

        /*
         * Groepsgegevens bijwerken.
         */
        $update_fields = [
            "gro_name = :gro_name",
        ];

        $parameters = [
            "gro_name" => $gro_name,
            "gro_id" => $gro_id,
        ];

        if ($new_profile_photo_url !== null) {
            $update_fields[] = "gro_profile_photo_url = :gro_profile_photo_url";

            $parameters["gro_profile_photo_url"] = $new_profile_photo_url;
        }

        (new UpdateData($pdo))->update(
            table: "fta_groups",
            set: $update_fields,
            where: [
                "gro_id = :gro_id"
            ],
            execute: $parameters
        );

        /*
         * Huidige members ophalen.
         */

        $current_members = (new GetData($pdo)->by_where(
            select: ['usr_id'],
            from: "fta_group_users",
            from_alias: "grus",
            where: ["gro_id = :gro_id"],
            execute: ["gro_id" => $gro_id]
        ));

        $current_member_ids = array_map(
            "intval",
            array_column($current_members, "usr_id")
        );

        /*
         * Verschillen bepalen.
         */
        $member_ids_to_add = array_diff(
            $new_member_ids,
            $current_member_ids
        );

        $member_ids_to_remove = array_diff(
            $current_member_ids,
            $new_member_ids
        );
        
        /*
        * Nieuwe members toevoegen.
        */
        if ($member_ids_to_add !== []) {
            $post_data = new PostData($pdo);

            foreach ($member_ids_to_add as $usr_id) {
                $post_data->insert(
                    table: "fta_group_users",
                    data: [
                        "gro_id" => $gro_id,
                        "usr_id" => $usr_id
                    ]
                );
            }
        }

        /*
         * Verwijderde members verwijderen.
         */
        if ($member_ids_to_remove !== []) {
            $delete_data = new DeleteData($pdo);

            foreach ($member_ids_to_remove as $usr_id) {
                $delete_data->delete(
                    from: "fta_group_users",
                    where: [
                        "gro_id = :gro_id",
                        "usr_id = :usr_id"
                    ],
                    execute: [
                        "gro_id" => $gro_id,
                        "usr_id" => $usr_id
                    ]
                );
            }
        }

        $pdo->commit();

        /*
         * Oude afbeelding pas na een succesvolle commit verwijderen.
         */
        if (
            $new_profile_photo_url !== null &&
            !empty($old_profile_photo_url) &&
            $old_profile_photo_url !==
                $new_profile_photo_url
        ) {
            delete_profile_photo(
                $old_profile_photo_url
            );
        }

        echo json_encode([
            "success" => true,
            "message" => "Je groep is bijgewerkt.",
            "data" => [
                "added_member_ids" => array_values(
                    $member_ids_to_add
                ),
                "removed_member_ids" => array_values(
                    $member_ids_to_remove
                ),
            ],
        ]);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function delete_profile_photo(
    string $image_path
): void {
    if ($image_path === "") {
        return;
    }

    $absolute_path =
        dirname(__DIR__, 2) . $image_path;

    if (is_file($absolute_path)) {
        unlink($absolute_path);
    }
}