<?php

class SelectQueryBuilder
{
    private array $fields = [];
    private array $conditions = [];
    private array $from = [];
    private array $joins = [];
    private array $order_by = [];
    private bool $distinct = false;
    private bool $for_update = false;

    public function __toString(): string
    {
        $distinct = $this->distinct
            ? 'DISTINCT '
            : '';

        $join = $this->joins === []
            ? ''
            : ' ' . implode(' ', $this->joins);

        $where = $this->conditions === []
            ? ''
            : ' WHERE ' . implode(' AND ', $this->conditions);

        $order_by = $this->order_by === []
            ? ''
            : ' ORDER BY ' . implode(', ', $this->order_by);

        $for_update = $this->for_update
            ? ' FOR UPDATE'
            : '';

        return 'SELECT '
            . $distinct
            . implode(', ', $this->fields)
            . ' FROM '
            . implode(', ', $this->from)
            . $join
            . $where
            . $order_by
            . $for_update;
    }

    public function select(string ...$select): self
    {
        $this->fields = $select;

        return $this;
    }

    public function distinct(): self
    {
        $this->distinct = true;

        return $this;
    }

    // FOR UPDATE vergrendelt de gevonden database-records binnen een transactie, zodat een andere transactie ze niet tegelijkertijd kan wijzigen.
    public function for_update(): self
    {
        $this->for_update = true;

        return $this;
    }

    public function from(string $table, ?string $alias = null): self
    {
        $this->from[] = $alias === null
            ? $table
            : "$table AS $alias";

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

    public function left_join(
        string $table,
        string $condition,
        ?string $alias = null
    ): self {
        $join_table = $alias === null
            ? $table
            : "$table AS $alias";

        $this->joins[] = "LEFT JOIN $join_table ON $condition";

        return $this;
    }

    public function where(string ...$where): self
    {
        foreach ($where as $condition) {
            $this->conditions[] = $condition;
        }

        return $this;
    }

    public function order_by_raw(string $order): self
    {
        $this->order_by[] = $order;

        return $this;
    }

    public function where_not_exists(SelectQueryBuilder $subquery): self
    {
        $this->conditions[] = "NOT EXISTS ($subquery)";

        return $this;
    }
}