<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";
// setting CORS headers
new CorsHeaders("application/json", "GET, OPTIONS", "Content-Type", "true");
$http_method = $_SERVER['REQUEST_METHOD'];

switch($http_method){
  case "GET":
    get_data($pdo);
    break;
  case "OPTIONS":
    http_response_code(204);
    exit;
  default:
    sendDisallowRequestMethodMessage($http_method);
}

function get_data(PDO $pdo){
    session_start();

    if (!isset($_SESSION["usr_id"])) {
        sendErrorMessage(401, "Je moet ingelogd zijn om een ronde te starten.");
    }

    $user_id = (int) $_SESSION["usr_id"];

    $group_id = filter_input(
        INPUT_GET,
        "group_id",
        FILTER_VALIDATE_INT
    );

    if (!$group_id || $group_id <= 0) {
        sendErrorMessage(400, "Er is geen geldige groep geselecteerd.");
    }

    try {
        /*
        * Controleer of de ingelogde gebruiker een geaccepteerd
        * lid van deze groep is.
        */
        $membership = (new GetData($pdo))->by_where(
            select: [
                "grus_id"
            ],
            from: "fta_group_users",
            where: [
                "gro_id = :group_id",
                "usr_id = :user_id",
                "grus_status = 1"
            ],
            execute: [
                "group_id" => $group_id,
                "user_id" => $user_id
            ],
            fetch_once: true
        );

        if (!$membership) {
            sendErrorMessage(403, "Je bent geen lid van deze groep.");
        }

        /*
        * Haal de groep op.
        */
        $group = (new GetData($pdo))->by_where(
            select: [
                "gro_id",
                "gro_name",
                "gro_profile_photo_url",
                "gro_creator_id"
            ],
            from: "fta_groups",
            where: [
                "gro_id = :group_id"
            ],
            execute: [
                "group_id" => $group_id
            ],
            fetch_once: true
        );

        if (!$group) {
            sendErrorMessage(404, "De groep kon niet worden gevonden.");
        }

        /*
        * Haal alle geaccepteerde groepsleden op.
        */
        $members = (new GetData($pdo))->by_join(
            select: [
                "users.usr_id",
                "users.usr_name",
                "users.usr_profile_photo_url",
                "users.usr_code"
            ],
            from: "fta_group_users",
            from_alias: "group_users",
            joins: [
                [
                    "type" => "INNER",
                    "table" => "fta_users",
                    "alias" => "users",
                    "condition" => "users.usr_id = group_users.usr_id"
                ]
            ],
            where: [
                "group_users.gro_id = :group_id",
                "group_users.grus_status = 1"
            ],
            execute: [
                "group_id" => $group_id,
                "current_user_id" => $user_id
            ],
            order_by: [
                "CASE
                    WHEN users.usr_id = :current_user_id THEN 0
                    ELSE 1
                END",
                "users.usr_name ASC"
            ]
        );
        $members = array_map(
            static function (array $member) use ($user_id): array {
                return [
                    "usr_id" => (int) $member["usr_id"],
                    "usr_name" => $member["usr_name"],
                    "usr_profile_photo_url" =>
                        $member["usr_profile_photo_url"],
                    "usr_code" => $member["usr_code"],
                    "is_current_user" =>
                        (int) $member["usr_id"] === $user_id,
                ];
            },
            $members
        );

        /*
        * Haal productcategorieën op.
        *
        * Pas prc_id en prc_name aan wanneer jouw
        * categorievelden anders heten.
        */
        $categories_statement = $pdo->query("
            SELECT
                prc_id,
                prc_name
            FROM fta_product_categories
            ORDER BY prc_name ASC
        ");

        $categories = $categories_statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        /*
        * Haal producten op.
        *
        * Dit voorbeeld gaat ervan uit dat:
        * - pro_category_id naar fta_product_categories verwijst
        * - pro_active bepaalt of een product beschikbaar is
        *
        * Verwijder de pro_active-regel als die kolom niet bestaat.
        */
        $products_statement = $pdo->query("
            SELECT
                pro_id,
                pro_category_id,
                pro_name,
                pro_price,
                pro_description
            FROM fta_products
            WHERE pro_active = 1
            ORDER BY pro_name ASC
        ");

        $products = $products_statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        /*
        * Koppel producten aan hun categorie.
        */
        $categories_with_products = array_map(
            static function (array $category) use ($products): array {
                $category_products = array_filter(
                    $products,
                    static fn (array $product): bool =>
                        (int) $product["pro_category_id"] ===
                        (int) $category["prc_id"]
                );

                $category_products = array_map(
                    static function (array $product): array {
                        return [
                            "pro_id" => (int) $product["pro_id"],
                            "pro_name" => $product["pro_name"],
                            "pro_price" => number_format(
                                (float) $product["pro_price"],
                                2,
                                ".",
                                ""
                            ),
                            "pro_description" =>
                                $product["pro_description"],
                        ];
                    },
                    array_values($category_products)
                );

                return [
                    "prc_id" => (int) $category["prc_id"],
                    "prc_name" => $category["prc_name"],
                    "products" => $category_products,
                ];
            },
            $categories
        );
        sendSuccessMessage("Ronde is gemaakt", [
            "group" => [
                "gro_id" => (int) $group["gro_id"],
                "gro_name" => $group["gro_name"],
                "gro_profile_photo_url" =>
                    $group["gro_profile_photo_url"],
                "gro_creator_id" =>
                    (int) $group["gro_creator_id"],
            ],
            "members" => $members,
            "categories" => $categories_with_products,
        ]);
    } 
    catch (PDOException $exception) {
        sendErrorMessageWithException(500, "De gegevens voor de ronde konden niet worden opgehaald.", $exception);
    } 
}
