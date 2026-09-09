<?php

include_once __DIR__ . "/../sql/query_builders/delete_builder.php";

class DeleteData
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Verwijdert één of meerdere records aan de hand van WHERE-condities.
     *
     * @param string $from Naam van de tabel.
     *                     Voorbeeld: "fta_group_users"
     *
     * @param array $where WHERE-condities.
     *                     Voorbeeld:
     *                     [
     *                         "gro_id = :gro_id",
     *                         "usr_id = :usr_id"
     *                     ]
     *
     * @param array $execute Waarden voor de SQL-placeholders.
     *                       Voorbeeld:
     *                       [
     *                           "gro_id" => 1,
     *                           "usr_id" => 5
     *                       ]
     *
     * @return int Het aantal verwijderde records.
     */
    public function delete(
        string $from,
        array $where,
        array $execute = []
    ): int {
        $query = (new DeleteQueryBuilder())
            ->from($from)
            ->where(...$where);

        return $this->execute_query(
            query: $query,
            execute: $execute
        );
    }

    /**
     * Voert een handmatig opgebouwde DELETE-query uit.
     *
     * @param DeleteQueryBuilder $query De opgebouwde DELETE-query.
     * @param array $execute Waarden voor de SQL-placeholders.
     *
     * @return int Het aantal verwijderde records.
     */
    public function execute_query(
        DeleteQueryBuilder $query,
        array $execute = []
    ): int {
        $stmt = $this->pdo->prepare((string) $query);
        $stmt->execute($execute);

        return $stmt->rowCount();
    }
}