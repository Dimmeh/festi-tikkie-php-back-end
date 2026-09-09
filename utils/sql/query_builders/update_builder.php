<?php

class UpdateQueryBuilder
{
    private string $table = "";
    private ?string $alias = null;

    private array $joins = [];
    private array $set = [];
    private array $conditions = [];


    public function __toString(): string
    {
        $table = $this->alias === null
            ? $this->table
            : "{$this->table} AS {$this->alias}";

        $joins = $this->joins === []
            ? ""
            : " " . implode(" ", $this->joins);

        $set = " SET " . implode(", ", $this->set);

        $where = $this->conditions === []
            ? ""
            : " WHERE " . implode(" AND ", $this->conditions);

        return "UPDATE "
            . $table
            . $joins
            . $set
            . $where;
    }
    
    public function table(
        string $table,
        ?string $alias = null
    ): self {
        $this->table = $table;
        $this->alias = $alias;

        return $this;
    }

    public function join(
        string $table,
        string $condition,
        ?string $alias = null
    ): self {
        $join_table = $alias === null
            ? $table
            : "$table AS $alias";

        $this->joins[] = "INNER JOIN $join_table ON $condition";

        return $this;
    }

    public function set(string ...$set): self
    {
        foreach ($set as $value) {
            $this->set[] = $value;
        }

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