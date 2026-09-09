<?php
include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";
// setting CORS headers
new CorsHeaders("application/json", "POST, OPTIONS", "Content-Type, Authorization", "true");

$http_method = $_SERVER["REQUEST_METHOD"];

switch ($http_method) {
    case "POST":
        // echo "HEllo post";
        post_data($pdo, $_POST);
        break;
    case "OPTIONS":
        http_response_code(204);
        exit;
    default:
        sendDisallowRequestMethodMessage($http_method);

}

function post_data(PDO $pdo, array $data): void
{
    $usr_id = $_SESSION["usr_id"] ?? $data["user_id_test"];
    $evn_id = $data["event_id"] ?? null;
    if (
        (!is_numeric($usr_id) ||
        (int) $usr_id <= 0)
    ) {
        sendErrorMessage(401, "Je moet ingelogd zijn om een ronde te starten.", [
            "sessionId" => $_SESSION["usr_id"],
            "dataId" => $data["user_id_test"],
            "evnId" => $data["event_id"]
        ]);
    }

    $usr_id = (int) $usr_id;


    $gro_id = isset($data["group_id"])
        ? (int) $data["group_id"]
        : 0;


    if ($gro_id <= 0) {
        // http_response_code(4221);
        sendErrorMessage(422, "Er is geen geldige groep geselecteerd.", [
            "groId" => $gro_id,
            "data" => $data
        ]);
    }

    $evn_id = isset($data["event_id"])
        ? (int) $data["event_id"]
        : 0;

    if ($evn_id <= 0) {
        // http_response_code(422);
        sendErrorMessage(422, "Er is geen geldige event geselecteerd.", [
            "evnId" => $evn_id,
            "data" => $data
        ]);
    }

    $loc_id = isset($data["location_id"])
        ? (int) $data["location_id"]
        : 0;
        
    if ($loc_id <= 0) {
        // http_response_code(422);
        sendErrorMessage(422, "Er is geen geldige locatie geselecteerd.", [
            "locId" => $loc_id,
            "data" => $data
        ]);
    }
    create_round_invitation(
        $pdo,
        $gro_id,
        $usr_id,
        $evn_id,
        $loc_id
    );
}

function create_round_invitation(
    PDO $pdo,
    int $gro_id,
    int $usr_id,
    int $evn_id,
    int $loc_id
): void {
    try {
        $pdo->beginTransaction();

        /*
         * Controleer of de groep bestaat en of de gebruiker
         * een actief lid van de groep is.
         */
        $membership = (new GetData($pdo))->by_join(
            select: [
                "group_users.grus_id"
            ],
            from: "fta_group_users",
            from_alias: "group_users",
            joins: [
                [
                    "type" => "INNER",
                    "table" => "fta_groups",
                    "alias" => "groups",
                    "condition" => "groups.gro_id = group_users.gro_id"
                ]
            ],
            where: [
                "group_users.gro_id = :gro_id",
                "group_users.usr_id = :usr_id",
                "group_users.grus_status = 1"
            ],
            execute: [
                "gro_id" => $gro_id,
                "usr_id" => $usr_id
            ],
            fetch_once: true
        );

        if (!$membership) {
            $pdo->rollBack();
            sendErrorMessage(403, "Je bent geen lid van deze groep.");
        }



        /*
         * Controleer of de gebruiker zelf al een actieve
         * uitnodigingsronde heeft aangemaakt.
         */
        $created_round = (new GetData($pdo))->by_where(
            select: [
                "invrou_id"
            ],
            from: "fta_invite_rounds",
            from_alias: "invrou",
            where: [
                "invrou_creator_id = :usr_id",
                "invrou_status = 1"
            ],
            execute: [
                "usr_id" => $usr_id
            ],
            fetch_once: true
        );

        if ($created_round) {
            $pdo->rollBack();
            sendErrorMessage(409, "Je hebt al een actieve uitnodiging.",
                [
                    "data" => [
                        "invite_round_id" =>(int) $created_round["invrou_id"],
                    ]
                ]
            );
        }

        /*
         * Controleer of de gebruiker al geaccepteerd heeft
         * voor een andere actieve uitnodigingsronde.
         *
         * Alleen status 1 betekent dat de gebruiker echt
         * in een andere lobby zit.
         */
        $active_lobby = (new GetData($pdo))->by_join(
            select: [
                "invited_user.invrou_id"
            ],
            from: "fta_invited_users",
            from_alias: "invited_user",
            joins: [
                [
                    "type" => "INNER",
                    "table" => "fta_invite_rounds",
                    "alias" => "invite_round",
                    "condition" => "invite_round.invrou_id = invited_user.invrou_id"
                ]
            ],
            where: [
                "invited_user.usr_id = :usr_id",
                "invited_user.invusr_status = 1",
                "invite_round.invrou_status = 1"
            ],
            execute: [
                "usr_id" => $usr_id
            ],
            fetch_once: true
        );

        if ($active_lobby) {
            $pdo->rollBack();

            http_response_code();
            sendErrorMessage(409, "Je zit al in een andere actieve lobby.",
                [
                    "data" => [
                        "invite_round_id" => (int) $active_lobby["inro_id"],
                    ]
                ]);
        }

        /*
         * Maak de uitnodigingsronde aan.
         *
         * De ingelogde gebruiker is de maker van de ronde.
         */
        $invite_round_id = (int) (new PostData($pdo))->insert(
            table: "fta_invite_rounds",
            data: [
                "invrou_creator_id" => $usr_id,
                "gro_id" => $gro_id,
                "evn_id" => $evn_id,
                "proloc_id" => $loc_id
            ],
            raw_values: [
                "invrou_status" => "'open'",
                "invrou_expires_at" => "DATE_ADD(NOW(), INTERVAL 10 MINUTE)"
            ],
            return_last_insert_id: true
        );

        /*
         * Prepared statement voor alle gebruikers die aan
         * de uitnodigingsronde worden gekoppeld.
         */
        (new PostData($pdo))->insert(
            table: "fta_invited_users",
            data: [
                "invrou_id" => $invite_round_id,
                "usr_id" => $usr_id
            ],
            raw_values: [
                "invusr_status" => "'joined'",
                "invusr_joined_at" => "NOW()",
                "invusr_responded_at" => "NOW()"
            ]
        );

        /*
         * Haal alle beschikbare groepsleden op.
         *
         * Een groepslid is niet beschikbaar wanneer die al
         * geaccepteerd heeft voor een andere actieve lobby.
         */
        $subquery = (new SelectQueryBuilder())
            ->select("1")
            ->from("fta_invited_users", "invited_users")
            ->join(
                table: "fta_invite_rounds",
                alias: "invite_rounds",
                condition: "invite_rounds.invrou_id = invited_users.invrou_id"
            )
            ->where(
                "invited_users.usr_id = group_users.usr_id",
                "invited_users.invusr_status = 'joined'",
                "invite_rounds.invrou_status = 'open'",
                "invite_rounds.invrou_expires_at > NOW()"
            );

        $query = (new SelectQueryBuilder())
            ->select("group_users.usr_id")
            ->from("fta_group_users", "group_users")
            ->where(
                "group_users.gro_id = :gro_id",
                "group_users.grus_status = 1",
                "group_users.usr_id <> :creator_id"
            )
            ->where_not_exists($subquery);

        $available_members = (new GetData($pdo))->execute_query(
            query: $query,
            execute: [
                "gro_id" => $gro_id,
                "creator_id" => $usr_id
            ]
        );
        /*
         * Voeg beschikbare groepsleden toe met status invited.
         */

        $insert_query = (new InsertQueryBuilder())
            ->into("fta_invited_users")
            ->values(
                [
                    "invrou_id",
                    "usr_id"
                ]
            )
        ;
        foreach ($available_members as $member) {
            new PostData($pdo)->execute_query(
                query: $insert_query,
                execute:[
                "invrou_id" => $invite_round_id,
                "usr_id" => (int) $member["usr_id"]
            ]);
        }

        $pdo->commit();

        http_response_code(201);
        sendSuccessMessage("De uitnodiging voor de ronde is aangemaakt.", [
            "data" => [
                "invite_round_id" => $invite_round_id,
                "group_id" => $gro_id,
                "invited_user_count" =>  count($available_members),
            ]
        ]);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendErrorMessageWithException(500, "De uitnodiging kon niet worden aangemaakt.", $exception,
            [
                "groId" => $gro_id,
                "usrId" => $usr_id,
                "evn_Id" => $evn_id
            ]);
    }
}