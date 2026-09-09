<?php

include_once __DIR__ . "/../sql/query_builders/update_builder.php";

class UpdateData
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Voert een normale UPDATE uit.
     *
     * @param string $table Naam van de tabel.
     *
     * @param array $set SET-waardes.
     *                   Voorbeeld:
     *                   [
     *                       "usr_name = :usr_name",
     *                       "usr_updated_at = NOW()"
     *                   ]
     *
     * @param array $where WHERE-condities.
     *                     Voorbeeld:
     *                     [
     *                       "usr_id = :usr_id"
     *                     ]
     *
     * @param array $execute Waarden voor de SQL-placeholders.
     *                       Voorbeeld:
     *                       [
     *                           "usr_name" => "John Doe",
     *                           "usr_id" => 1
     *                       ]
     *
     * @return int
     */
    public function update(
        string $table,
        array $set,
        array $where,
        array $execute = []
    ): int {
        $query = (new UpdateQueryBuilder())
            ->table($table)
            ->set(...$set)
            ->where(...$where);

        return $this->execute_query(
            query: $query,
            execute: $execute
        );
    }

    /**
     * Voert een UPDATE met één of meerdere INNER JOINs uit.
     *
     * @param string $table Naam van de hoofdtabel.
     *
     * @param string $table_alias Alias van de hoofdtabel.
     *
     * @param array $joins JOIN-configuratie.
     *                     Voorbeeld:
     *                     [
     *                         [
     *                             "table" => "fta_invite_rounds",
     *                             "alias" => "invite_rounds",
     *                             "condition" => "invite_rounds.invrou_id = invited_users.invrou_id"
     *                         ]
     *                     ]
     *
     * @param array $set SET-waardes.
     *
     * @param array $where WHERE-condities.
     *
     * @param array $execute Waarden voor de SQL-placeholders.
     *
     * @return int
     */
    public function by_join(
        string $table,
        string $table_alias,
        array $joins,
        array $set,
        array $where,
        array $execute = []
    ): int {
        $query = (new UpdateQueryBuilder())
            ->table($table, $table_alias);

        foreach ($joins as $join) {
            $query->join(
                table: $join["table"],
                condition: $join["condition"],
                alias: $join["alias"] ?? null
            );
        }

        $query
            ->set(...$set)
            ->where(...$where);
        return $this->execute_query(
            query: $query,
            execute: $execute
        );
    }

    /**
     * Voert een handmatig opgebouwde UpdateQueryBuilder-query uit.
     *
     * Bedoeld voor complexere UPDATE-queries die niet volledig binnen
     * update() of by_join() passen.
     *
     * @param UpdateQueryBuilder $query De opgebouwde UPDATE-query.
     *
     * @param array $execute Waarden voor de SQL-placeholders.
     *
     * @return int Het aantal records dat door de UPDATE is gewijzigd.
     */
    public function execute_query(
        UpdateQueryBuilder $query,
        array $execute = []
    ): int {
        $stmt = $this->pdo->prepare((string) $query);
        $stmt->execute($execute);
        return $stmt->rowCount();
    }
}