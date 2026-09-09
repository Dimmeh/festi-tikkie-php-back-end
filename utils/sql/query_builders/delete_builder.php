<?php

class DeleteQueryBuilder
{
    private string $table = "";
    private array $conditions = [];


    public function __toString(): string
    {
        $where = $this->conditions === []
            ? ""
            : " WHERE " . implode(" AND ", $this->conditions);

        return "DELETE FROM "
            . $this->table
            . $where;
    }
    
    public function from(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    public function where(string ...$where): self
    {
        foreach ($where as $condition) {
            $this->conditions[] = $condition;
        }

        return $this;
    }
}