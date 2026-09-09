<?php

    class InsertQueryBuilder
    {
        private string $table = '';
        private array $columns = [];
        private array $placeholders = [];

        public function __toString(): string
        {
            return 'INSERT INTO ' . $this->table
                . ' (' . implode(', ', $this->columns) . ')'
                . ' VALUES (' . implode(', ', $this->placeholders) . ')';
        }
        public function into(string $table): self
        {
            $this->table = $table;

            return $this;
        }

        public function values(
            array $columns,
            array $raw_values = []
        ): self {
            $this->columns = $columns;

            foreach ($columns as $column) {
                $this->placeholders[] = array_key_exists($column, $raw_values)
                    ? $raw_values[$column]
                    : ':' . $column;
            }

            return $this;
        }
    }