<?php
include_once __DIR__ . "/../sql/query_builders/insert_builder.php";
class PostData{
    private PDO $pdo;
    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    /**
     * Summary of insert
     * 
     * @param string $table                 Voer de naam van de tabel in
     *                                      Voorbeeld: "example_table"
     * @param array $data                   Voor de kolomnamen en de waarden in. De kolomnamen worden ook tevens gebruikt als placeholders.
     *                                      Voorbeeld:
     *                                      [
     *                                          "gro_name" => $group_name,
     *                                          "gro_creator_id" => $usr_id
     *                                      ],         
     * @param array $raw_values             Directe waardes, zoals NOW().
     *                                      Voorbeeld:
     *                                      [
     *                                          "datetime": NOW()
     *                                      ]
     * @param bool $return_last_insert_id   Standaard false.
     *                                      True = laatst toegevoegde record's id ophalen.
     *
     * @return bool|string
     */
    public function insert(
        string $table,
        array $data,
        array $raw_values = [],
        bool $return_last_insert_id = false
    ): bool|string {
        $columns = [
            ...array_keys($data),
            ...array_keys($raw_values)
        ];

        $query = (new InsertQueryBuilder())
            ->into($table)
            ->values($columns, $raw_values);

        return $this->execute_query(
            query: $query,
            execute: $data,
            return_last_insert_id: $return_last_insert_id
        );
    }

    /**
     * Voert een handmatig opgebouwde InsertQueryBuilder-query uit.
     *
     * @param InsertQueryBuilder $query     De opgebouwde InsertQueryBuilder-query.
     *                                      Voorbeeld:
     *                                      $query = (new InsertQueryBuilder())
     *                                      ->into($table)
     *                                      ->values(array_keys($data));
     *
     * @param array $execute                Waarden voor de SQL-placeholders.
     *                                      Voorbeeld:
     *                                      [
     *                                          "usr_id" => 1,
     *                                          "group_id" => 5
     *                                      ]
     *
     * @param bool $return_last_insert_id   Standaard false.
     *                                      True = laatst toegevoegde record's id ophalen.
     *
     * @return bool|string                  True bij succesvolle INSERT,
     *                                      false bij mislukte execute(),
     *                                      of het laatst toegevoegde ID als string
     *                                      wanneer return_last_insert_id true is.
    */
        public function execute_query(
            InsertQueryBuilder $query,
            array $execute = [],
            bool $return_last_insert_id = false
        ): bool|string {
            $stmt = $this->pdo->prepare((string) $query);

            $success = $stmt->execute($execute);

            if (!$success) {
                return false;
            }

            if ($return_last_insert_id) {
                return $this->pdo->lastInsertId();
            }

            return true;
        }
   
}