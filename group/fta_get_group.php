<?php
include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/sql/query_fields/user_fields.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";

// setting CORS headers
new CorsHeaders("application/json", "GET, OPTIONS", "Content-Type", "true");
$http_method = $_SERVER["REQUEST_METHOD"];

switch ($http_method) {
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
    if (!isset($_SESSION["usr_id"])) {
        sendErrorMessage(401, "Je bent niet ingelogd.");
    }

    $user_id = (int) $_SESSION["usr_id"];

    $group_id = isset($_GET["group_id"])
        ? (int) $_GET["group_id"]
        : 0;

    if ($group_id > 0) {
        get_group_by_id($pdo, $group_id, $user_id);
    }

    get_groups_by_user_id($pdo, $user_id);
}




/**
 * Haalt alle groepen op waarvan de gebruiker:
 * - de creator is; of
 * - een geaccepteerd groepslid is.
 */
function get_groups_by_user_id(
    PDO $pdo,
    int $user_id
): void {
    try {
        $groups = (new GetData($pdo))->by_join(
            select: [
                "g.*",
                "CASE
                    WHEN g.gro_creator_id = :is_creator_user_id
                    THEN 1
                    ELSE 0
                END AS is_creator"
            ],
            from: "fta_groups",
            from_alias: "g",
            joins: [
                [
                    "type" => "LEFT",
                    "table" => "fta_group_users",
                    "alias" => "gu",
                    "condition" => "gu.gro_id = g.gro_id
                                    AND gu.usr_id = :joined_user_id
                                    AND gu.grus_status = 1"
                ]
            ],
            where: ["g.gro_creator_id = :creator_user_id OR gu.usr_id IS NOT NULL"],
            execute: [
                "is_creator_user_id" => $user_id,
                "joined_user_id" => $user_id,
                "creator_user_id" => $user_id
            ],
            fetch_once: false,
            order_by: ["g.gro_created_at DESC"],
            distinct: true
        );

        foreach ($groups as &$group) {
            format_group_data($group);
        }

        unset($group);
        sendSuccessMessage("Groepen opgehaald.", ["groups" => $groups]);
    } 
    catch (PDOException $exception) {
        sendErrorMessage(500, "Er is iets misgegaan bij het ophalen van de groepen.", ["error" => $exception->getMessage()]);
    }
}


/**
 * Haalt één groep op.
 *
 * Alleen de creator of een geaccepteerd groepslid
 * mag de groep bekijken.
 */
function get_group_by_id(
    PDO $pdo,
    int $group_id,
    int $user_id
): void {
    try {
        $group = (new GetData($pdo))->by_join(
            select: [
                "g.*",
                "CASE
                    WHEN g.gro_creator_id = :is_creator_user_id
                    THEN 1
                    ELSE 0
                END AS is_creator"
            ],
            from: "fta_groups",
            from_alias: "g",
            joins: [
                [
                    "type" => "LEFT",
                    "table" => "fta_group_users",
                    "alias" => "gu",
                    "condition" => "
                        gu.gro_id = g.gro_id
                        AND gu.usr_id = :joined_user_id
                        AND gu.grus_status = 1
                    "
                ]
            ],
            where: [
                "g.gro_id = :group_id",
                "(g.gro_creator_id = :creator_user_id OR gu.usr_id IS NOT NULL)"
            ],
            execute: [
                "is_creator_user_id" => $user_id,
                "joined_user_id" => $user_id,
                "group_id" => $group_id,
                "creator_user_id" => $user_id
            ],
            fetch_once: true,
            distinct: true
        );
        if (!$group) {
            sendErrorMessage(404, "Groep niet gevonden of je hebt geen toegang.");
        }

        format_group_data($group);

        $group["gro_members"] = get_group_members(
            $pdo,
            $group_id
        );
        sendSuccessMessage("Groep opgehaald", ["group" => $group]);
    } catch (PDOException $exception) {
        sendErrorMessage(500, "Er is iets misgegaan bij het ophalen van de groep.", ["error" => $exception->getMessage()]);
    }
}


/**
 * Haalt alle geaccepteerde leden van een groep op.
 */
function get_group_members(
    PDO $pdo,
    int $group_id
): array {
    $select = ["gu.grus_id", "gu.gro_id", "gu.grus_status"];
    foreach(UserFields::ALL_WITHOUT_PASSWORD as $val){
        $select[] = "u.$val";
    }
    $members = (new GetData($pdo))->by_join(
        select: $select,
        from: "fta_group_users",
        from_alias: "gu",
        joins: [
            [
                "table" => "fta_users",
                "alias" => "u",
                "condition" => "u.usr_id = gu.usr_id"
            ]
        ],
        where: ["gu.gro_id = :group_id", "gu.grus_status = 1"],
        execute: ["group_id" => $group_id],
        order_by: ["u.usr_name ASC"]
    );

    foreach ($members as &$member) {
        $member["grus_id"] = (int) $member["grus_id"];
        $member["gro_id"] = (int) $member["gro_id"];
        $member["grus_status"] = (int) $member["grus_status"];
        $member["usr_id"] = (int) $member["usr_id"];
    }

    unset($member);

    return $members;
}


/**
 * Zet numerieke databasewaarden om naar de juiste PHP-types.
 */
function format_group_data(array &$group): void
{
    if (isset($group["gro_id"])) {
        $group["gro_id"] = (int) $group["gro_id"];
    }

    if (isset($group["gro_creator_id"])) {
        $group["gro_creator_id"] =
            (int) $group["gro_creator_id"];
    }

    if (isset($group["is_creator"])) {
        $group["is_creator"] =
            (bool) $group["is_creator"];
    }
}