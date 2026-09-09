<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";
include_once __DIR__ . "/fta_check_round_is_valid.php";
// setting CORS headers
new CorsHeaders("application/json; charset=UTF-8", "GET, OPTIONS", "Content-Type, Authorization", "true");
$http_method = $_SERVER["REQUEST_METHOD"];

switch ($http_method) {
    case "GET":
        get_round_invitations($pdo);
        break;

    case "OPTIONS":
        http_response_code(204);
        exit;

    default:
        sendDisallowRequestMethodMessage($http_method);
}

function get_round_invitations(PDO $pdo): void
{
    /*
     * Gebruik hier dezelfde sessiesleutel als bij het inloggen.
     * In jouw huidige code lijkt dat "user_id" te zijn.
     */
    $usr_id = $_SESSION["usr_id"] ?? null;
    // if(isset($_GET["usr_test_id"])){
    //     $usr_id = $_GET["usr_test_id"];
    // }
    $invusr_status = "invited";
    if(isset($_GET['invusr_status'])){
        $invusr_status = $_GET['invusr_status'];
    }
    if (
        !is_numeric($usr_id) ||
        (int) $usr_id <= 0
    ) {
        sendErrorMessage(422, "Niet alle verplicte velden zijn ingevuld.");
        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Je moet ingelogd zijn om uitnodigingen te bekijken.",
        ]);

        return;
    }

    $usr_id = (int) $usr_id;

    try {
        /*
         * Zet eerst verlopen uitnodigingen op expired.
         *
         * Dit geldt alleen voor uitnodigingen waarop de gebruiker
         * nog niet heeft gereageerd.
         */
        (new UpdateData($pdo))->by_join(
            table: "fta_invited_users",
            table_alias: "invited_users",
            joins: [
                [
                    "table" => "fta_invite_rounds",
                    "alias" => "invite_rounds",
                    "condition" => "invite_rounds.invrou_id = invited_users.invrou_id"
                ]
            ],
            set: [
                "invited_users.invusr_status = 'expired'",
                "invited_users.invusr_responded_at = NOW()"
            ],
            where: [
                "invited_users.usr_id = :usr_id",
                "invited_users.invusr_status = 'invited'",
                "invite_rounds.invrou_status = 'open'",
                "invite_rounds.invrou_expires_at <= NOW()"
            ],
            execute: [
                "usr_id" => $usr_id
            ]
        );

        /*
         * Haal daarna alleen geldige openstaande uitnodigingen op.
         */

        // $invusr_status_arr = [];
        // $execute_arr= [];
        if($invusr_status == "*"){
            $invusr_status_arr = [
                "(invited_users.invusr_status = :invusr_status OR invited_users.invusr_status = :invusr_status_2)"
            ];
            
            $execute_arr = [
                "invusr_status" => "joined",
                "invusr_status_2" => "invited"
            ];
        }
        else{
            $invusr_status_arr = [
                "invited_users.invusr_status = :invusr_status",
            ];
            $execute_arr = [
                "invusr_status" => $invusr_status
            ];
        }
        $invitations = (new GetData($pdo))->by_join(
            select: [
                "invited_users.invusr_id",
                "invited_users.invrou_id",
                "invited_users.invusr_status",
                "invited_users.invusr_created_at",
                "invite_rounds.invrou_creator_id",
                "invite_rounds.gro_id",
                "invite_rounds.evn_id",
                "invite_rounds.invrou_status",
                "invite_rounds.invrou_expires_at",
                "invite_rounds.invrou_created_at",
                "groups.gro_name",
                "groups.gro_profile_photo_url",
                "creator.usr_name AS creator_name",
                "creator.usr_profile_photo_url AS creator_profile_photo_url"
            ],
            from: "fta_invited_users",
            from_alias: "invited_users",
            joins: [
                [
                    "type" => "INNER",
                    "table" => "fta_invite_rounds",
                    "alias" => "invite_rounds",
                    "condition" => "invite_rounds.invrou_id = invited_users.invrou_id"
                ],
                [
                    "type" => "INNER",
                    "table" => "fta_groups",
                    "alias" => "groups",
                    "condition" => "groups.gro_id = invite_rounds.gro_id"
                ],
                [
                    "type" => "INNER",
                    "table" => "fta_users",
                    "alias" => "creator",
                    "condition" => "creator.usr_id = invite_rounds.invrou_creator_id"
                ]
            ],
            where: [
                "invited_users.usr_id = :usr_id",
                "invite_rounds.invrou_status = 'open'",
                "invite_rounds.invrou_expires_at > NOW()",
                ...$invusr_status_arr
            ],
            execute: [
                "usr_id" => $usr_id,
                ...$execute_arr
            ],
            order_by: [
                "invite_rounds.invrou_created_at DESC"
            ]
        );

        $formatted_invitations = array_map(
            static function (array $invitation): array {
                return [
                    "invusr_id" =>
                        (int) $invitation["invusr_id"],

                    "invrou_id" =>
                        (int) $invitation["invrou_id"],

                    "invusr_status" =>
                        $invitation["invusr_status"],

                    "invrou_creator_id" =>
                        (int) $invitation["invrou_creator_id"],

                    "group_id" =>
                        (int) $invitation["gro_id"],

                    "event_id" =>
                        (int) $invitation["evn_id"],

                    "group_name" =>
                        $invitation["gro_name"],

                    "group_profile_photo_url" =>
                        $invitation["gro_profile_photo_url"],

                    "creator_name" =>
                        $invitation["creator_name"],

                    "creator_profile_photo_url" =>
                        $invitation["creator_profile_photo_url"],

                    "expires_at" =>
                        $invitation["invrou_expires_at"],

                    "created_at" =>
                        $invitation["invrou_created_at"],
                ];
            },
            $invitations
        );
        $message = $formatted_invitations === []
                ? "Je hebt geen openstaande uitnodigingen."
                : "De uitnodigingen zijn opgehaald.";
        sendSuccessMessage($message, [
            "data" => [
                "invitations" => $formatted_invitations,
                "invitation_count" =>
                    count($formatted_invitations),
            ],
            "usr_id" => $usr_id]
        );
    } catch (Throwable $exception) {
        sendErrorMessageWithException(500, "De uitnodigingen konden niet worden opgehaald.", $exception);
    }
}