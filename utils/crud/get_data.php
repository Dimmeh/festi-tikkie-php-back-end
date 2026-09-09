<?php
    include_once __DIR__ . "/../sql/query_builders/select_builder.php";
    class GetData{
        private PDO $pdo;

        public function __construct(PDO $pdo){
            $this->pdo = $pdo;
        }

        /**
         * Haalt alle data op
         * @param array $select Kolommen die opgehaald moeten worden.
         *                      Voorbeeld: ["example_id", "example_name", "example_group"] of ExampleFields::ALL
         * @param string $from  Naam van de tabel
         *                      Voorbeeld: "example_table".
         * @param string $from_alias        Alias voor de hoofdtabel.
         *                                  Voorbeeld: "exa".
         * 
         * @param array $order_by           Orderen op kolomnaam.
         *                                  Voorbeeld: ["exa.example_name ASC"].
         * @return array
         */
        public function all(array $select, string $from, string $from_alias , array $order_by = []):array{
            $query = (new SelectQueryBuilder())
                ->select(...$select)
                ->from($from, $from_alias);
                

            if ($order_by !== []) {
                foreach ($order_by as $order) {
                    $query->order_by_raw($order);
                }
            }

            return $this->execute_query(
                query: $query
            );
        }

        /**
         * Haalt data op met een conditie
         * @param array $select     Kolommen die opgehaald moeten worden.
         *                          Voorbeeld: ["example_id", "example_name", "example_group"] of ExampleFields::ALL
         * @param string $from      Naam van de tabel
         *                          Voorbeeld: "example_table"
         * @param string $from_alias        Alias voor de hoofdtabel.
         *                                  Voorbeeld: "exa".
         * @param array $where      WHERE-conditie
         *                          Voorbeeld: ["example_id = :example_id", "example_name" = :example_name] 
         *                          // :example_id & :example_name zijn de placeholders voor de execute()
         * @param array $execute    Waarden voor de placeholders
         *                          Voorbeeld: ["example_id" => 1, "example_name" => "John Doe"]
         * @param bool $fetch_once  Standaard op false. True = 1 record, false = alle records
         * @param array $order_by           Orderen op kolomnaam.
         *                                  Voorbeeld: ["exa.example_name ASC"].
         * @return array|false
         */
        public function by_where(
            array $select, 
            string $from, 
            string $from_alias, 
            array $where, 
            array $execute, 
            bool $fetch_once = false,
            array $order_by = []
        ):array | false{
            $query = (new SelectQueryBuilder())
                ->select(...$select)
                ->from($from, $from_alias)
                ->where(...$where);

            if ($order_by !== []) {
                foreach ($order_by as $order) {
                    $query->order_by_raw($order);
                }
            }
            
            return $this->execute_query(
                query: $query,
                execute: $execute,
                fetch_once: $fetch_once
            );
        }

        /**
         * Haalt data op met één of meerdere JOINs.
         * @param array $select             Kolommen die opgehaald moeten worden.
         *                                  Voorbeeld: ["example_id", "example_name", "example_group"] of ExampleFields::ALL
         * @param string $from              Naam van de tabel.
         *                                  Voorbeeld: "example_table".
         * @param string $from_alias        Alias voor de hoofdtabel.
         *                                  Voorbeeld: "exa".
         * @param array $joins              JOIN-configuratie.
         *                                  Iedere JOIN bevat:
         *                                  - type: "INNER" of "LEFT"
         *                                  - table: tabelnaam
         *                                  - alias: optionele alias
         *                                  - condition: ON-conditie
         *
         *                                  Voorbeeld:
         *                                  [
         *                                      [
         *                                          "type" => "INNER",
         *                                          "table" => "fta_groups",
         *                                          "alias" => "g",
         *                                          "condition" => "g.gro_id = u.gro_id"
         *                                      ],
         *                                      [
         *                                          "type" => "LEFT",
         *                                          "table" => "fta_group_users",
         *                                          "alias" => "gu",
         *                                          "condition" => "gu.gro_id = g.gro_id"
         *                                      ]
         *                                  ]
         * @param array $where              WHERE-conditie.
         *                                  Voorbeeld: ["example_id = :example_id", "example_name" = :example_name];
         *                                  // :example_id & :example_name zijn de placeholders voor de execute().
         * @param array $execute            Waarden voor de placeholders.
         *                                  Voorbeeld: ["example_id" => 1, "example_name" => "John Doe"];
         * @param bool $fetch_once          Standaard op false. True = 1 record, false = alle records.
         * @param array $order_by           Orderen op kolomnaam.
         *                                  Voorbeeld: ["exa.example_name ASC"].
         * @param bool $distinct            True = SELECT DISTINCT gebruiken.
         * @param bool $for_update          True = FOR UPDATE gebruiken.

         * @return array|false
         */
        public function by_join(
            array $select,
            string $from,
            string $from_alias,
            array $joins,
            array $where = [],
            array $execute = [],
            bool $fetch_once = false,
            array $order_by = [],
            bool $distinct = false,
            bool $for_update = false
        ): array|false {
            $query = (new SelectQueryBuilder())
                ->select(...$select)
                ->from($from, $from_alias);

            if ($distinct) {
                $query->distinct();
            }

            if ($for_update) {
                $query->for_update();
            }

            foreach ($joins as $join) {
                $join_type = strtoupper($join["type"] ?? "INNER");

                if ($join_type === "LEFT") {
                    $query->left_join(
                        table: $join["table"],
                        condition: $join["condition"],
                        alias: $join["alias"] ?? null
                    );
                } else {
                    $query->join(
                        table: $join["table"],
                        condition: $join["condition"],
                        alias: $join["alias"] ?? null
                    );
                }
            }

            if ($where !== []) {
                $query->where(...$where);
            }

            if ($order_by !== []) {
                foreach ($order_by as $order) {
                    $query->order_by_raw($order);
                }
            }
            return $this->execute_query(
                query: $query,
                execute: $execute,
                fetch_once: $fetch_once
            );
        }

        /**
         * Voert een handmatig opgebouwde SelectQueryBuilder-query uit.
         *
         * Bedoeld voor complexere queries die niet volledig binnen
         * by_where() of by_join() passen, zoals queries met subqueries,
         * EXISTS of NOT EXISTS.
         *
         * @param SelectQueryBuilder $query   De opgebouwde SelectQueryBuilder-query.
         *                              Voorbeeld:
         *                              $query = (new SelectQueryBuilder())
         *                                  ->select("u.usr_id", "u.usr_name")
         *                                  ->from("fta_users", "u")
         *                                  ->where("u.usr_id = :usr_id");
         *
         * @param array $execute        Waarden voor de SQL-placeholders.
         *                              Voorbeeld:
         *                              [
         *                                  "usr_id" => 1,
         *                                  "group_id" => 5
         *                              ]
         *
         * @param bool $fetch_once      Standaard false.
         *                              True = één record ophalen met fetch().
         *                              False = alle records ophalen met fetchAll().
         *
         * @return array|false          Array met één of meerdere resultaten.
         *                              False wanneer fetch_once true is en
         *                              er geen record gevonden is.
         */
        public function execute_query(
            SelectQueryBuilder $query,
            array $execute = [],
            bool $fetch_once = false
        ): array|false {
            $stmt = $this->pdo->prepare((string) $query);
            $stmt->execute($execute);

            return $fetch_once
                ? $stmt->fetch(PDO::FETCH_ASSOC)
                : $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }