<?php

include_once __DIR__ . "/../config/index.php";
include_once __DIR__ . "/../utils/crud/index.php";
include_once __DIR__ . "/../utils/messages/sending_message.php";
// setting CORS headers
new CorsHeaders("application/json", "POST, OPTIONS", "Content-Type, Authorization", "true");

$http_method = $_SERVER["REQUEST_METHOD"];

switch ($http_method) {
    case "POST":
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
    $usr_id = $_SESSION["usr_id"] ?? $data["user_id_test"] ??null;
    $invusr_status = $data["invusr_status"] ?? null;
    if (
        !is_numeric($usr_id) ||
        (int) $usr_id <= 0
    ) {
        sendErrorMessage(401, "Je moet ingelogd zijn om een uitnodiging te accepteren.");
    }

    if($invusr_status === null){
        sendErrorMessage(422, "Er is geen geldige status gegeven.");
    }

    $usr_id = (int) $usr_id;


    $invusr_id = isset($data["invusr_id"])
        ? (int) $data["invusr_id"]
        : 0;

    if ($invusr_id <= 0) {
        sendErrorMessage(422, "Er is geen geldige uitnodiging geselecteerd.");
    }

    confirm_status_round_invitation(
        $pdo,
        $invusr_id,
        $usr_id,
        $invusr_status
    );
}

function confirm_status_round_invitation(
    PDO $pdo,
    int $invusr_id,
    int $usr_id,
    string $invusr_status
): void {
    if($invusr_status !== "*"){
        $status_translated = $invusr_status === "joined" ? "geaccepteerd" : "geweigerd";  
    }
    else{
        $status_translated = "bijgewerkt.";
    }

    try {
        $pdo->beginTransaction();
        /*
         * Haal de uitnodiging op en vergrendel het record.
         * Zo voorkom je dat dezelfde uitnodiging twee keer
         * tegelijk wordt geaccepteerd.
         */
        $invitation = (new GetData($pdo))->by_join(
            select: [
                "invited_users.invusr_id",
                "invited_users.invrou_id",
                "invited_users.usr_id",
                "invited_users.invusr_status",
                "invite_rounds.gro_id",
                "invite_rounds.evn_id",
                "invite_rounds.invrou_status",
                "invite_rounds.invrou_expires_at"
            ],
            from: "fta_invited_users",
            from_alias: "invited_users",
            joins: [
                [
                    "type" => "INNER",
                    "table" => "fta_invite_rounds",
                    "alias" => "invite_rounds",
                    "condition" => "invite_rounds.invrou_id = invited_users.invrou_id"
                ]
            ],
            where: ["invited_users.invusr_id = :invusr_id", "invited_users.usr_id = :usr_id"],
            execute: [
                "invusr_id" => $invusr_id,
                "usr_id" => $usr_id,
            ],
            fetch_once: true,
            for_update: true
        );
        if (!$invitation) {
            $pdo->rollBack();
            sendErrorMessage(404, "De uitnodiging kon niet worden gevonden.");
        }

        if ($invitation["invusr_status"] !== "invited" && ($invitation["invusr_status"] === "joined" && $invusr_status !== 'declined' ) ) {
            $pdo->rollBack();
            sendErrorMessage(409, "Je hebt al op deze uitnodiging gereageerd.", ["invitation" => $invitation]);
        }

        if ($invitation["invrou_status"] !== "open") {
            $pdo->rollBack();
            sendErrorMessage(409, "Deze ronde is niet meer open.");
        }

        /*
         * Controleer of de uitnodiging inmiddels verlopen is.
         */
        $expires_at = strtotime(
            $invitation["invrou_expires_at"]
        );

        if (
            $expires_at === false ||
            $expires_at <= time()
        ) {
            (new UpdateData($pdo))->update(
                table: "fta_invited_users",
                set: [
                    "invusr_status = 'expired'",
                    "invusr_responded_at = NOW()"
                ],
                where: [
                    "invusr_id = :invusr_id"
                ],
                execute: [
                    "invusr_id" => $invusr_id
                ]
            );
            $pdo->commit();
            sendErrorMessage(404, "Deze uitnodiging is verlopen.");
        }

        /*
         * Controleer of de gebruiker al in een andere open lobby zit.
         */

        $active_lobby = (new GetData($pdo))->by_join(
            select: [
                "invited_users.invusr_id",
                "invited_users.invrou_id"
            ],
            from: "fta_invited_users",
            from_alias: "invited_users",
            joins: [
                [
                    "table" => "fta_invite_rounds",
                    "alias" => 'invite_rounds',
                    "condition" => "invite_rounds.invrou_id = invited_users.invrou_id"
                ]
            ],
            where: [
                "invited_users.usr_id = :usr_id",
                "invited_users.invusr_status = 'joined'",
                "invite_rounds.invrou_status = 'open'",
                "invite_rounds.invrou_expires_at > NOW()",
                "invited_users.invrou_id <> :invrou_id"
            ],
            execute: [
                "usr_id" => $usr_id,
                "invrou_id" => (int) $invitation["invrou_id"]
            ],
            fetch_once: true
        );

        if ($active_lobby) {
            $pdo->rollBack();
            sendErrorMessage(409, "Je zit al in een andere actieve lobby.", ["data" => [
                    "invite_round_id" =>
                        (int) $active_lobby["invrou_id"],
                ]
            ]);
        }

        /*
         * Accepteer de uitnodiging.
         */
        $affected_rows = (new UpdateData($pdo))->update(
            table: "fta_invited_users",
            set: [
                "invusr_status = :invusr_status",
                "invusr_joined_at = NOW()",
                "invusr_responded_at = NOW()"
            ],
            where: [
                "invusr_id = :invusr_id",
                "usr_id = :usr_id"
            ],
            execute: [
                "invusr_id" => $invusr_id,
                "usr_id" => $usr_id,
                "invusr_status" => $invusr_status
            ]
        );

        if ($affected_rows !== 1) {
            $pdo->rollBack();
            sendErrorMessage(500, "De uitnodiging kon niet worden bijgewerkt.");
        }

        $pdo->commit();
        sendSuccessMessage("Je hebt de uitnodiging $status_translated.", [
            "data" => [
                "invusr_id" => $invusr_id,
                "invite_round_id" =>
                    (int) $invitation["invrou_id"],
                "group_id" =>
                    (int) $invitation["gro_id"],
                "event_id" =>
                    (int) $invitation["evn_id"],
            ]]);
    } 
    catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendErrorMessageWithException(500, "De uitnodiging kon niet worden $status_translated.", $exception);
    }
}