<?php

namespace MOIREI\HogQl;

use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PHPSQLParser\PHPSQLParser;

class Builder extends BaseBuilder
{
    protected array $aliasMap;
    protected array $allowedTables;

    public function __construct($connection)
    {
        parent::__construct($connection);
        $this->aliasMap = config('hogql.aliases', []);
        $this->allowedTables = config('hogql.allowed_tables', []);
    }

    /**
     * Parse a raw string query and return a Builder object.
     *
     * @param string $query
     * @return Builder
     */
    public static function parse(string $query){
        $parser = new PHPSQLParser();
        $parsedQuery = $parser->parse($query);

        $queryBuilder = DB::connection('hogql')->query();

        // Set the table
        if (isset($parsedQuery['FROM'])) {
            $table = $parsedQuery['FROM'][0]['table'];
            $queryBuilder->from($table);
        } else {
            $queryBuilder->from(config('hogql.default_table', 'events'));
        }

        // Add select columns
        if (isset($parsedQuery['SELECT'])) {
            $columns = [];
            foreach ($parsedQuery['SELECT'] as $select) {
                if ($select['expr_type'] === 'colref') {
                    $columns[] = $select['base_expr'];
                } elseif ($select['expr_type'] === 'aggregate_function') {
                    $functionName = $select['base_expr'];
                    $subTree = $select['sub_tree'][0]['base_expr'];
                    $alias = $select['alias']['name'] ?? null;
                    $columns[] = DB::raw("{$functionName}({$subTree})" . ($alias ? " AS {$alias}" : ""));
                }
            }
            $queryBuilder->select($columns);
        }

        // Add where conditions
        if (isset($parsedQuery['WHERE'])) {
            for ($i = 0; $i < count($parsedQuery['WHERE']); $i += 4) {
                $column = $parsedQuery['WHERE'][$i]['base_expr'];
                $operator = $parsedQuery['WHERE'][$i + 1]['base_expr'];
                $value = $parsedQuery['WHERE'][$i + 2]['base_expr'];

                // Trim quotes from value if they exist
                if (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }

                $queryBuilder->where($column, $operator, $value);
            }
        }

        // Add group by columns
        if (isset($parsedQuery['GROUP'])) {
            $groups = array_map(function ($group) {
                return $group['base_expr'];
            }, $parsedQuery['GROUP']);
            $queryBuilder->groupBy($groups);
        }

        // Add order by columns
        if (isset($parsedQuery['ORDER'])) {
            foreach ($parsedQuery['ORDER'] as $order) {
                $column = $order['base_expr'];
                $direction = strtoupper($order['direction']) === 'DESC' ? 'DESC' : 'ASC';
                $queryBuilder->orderBy($column, $direction);
            }
        }

        // Add limit (if applicable)
        if (isset($parsedQuery['LIMIT'])) {
            $limit = isset($parsedQuery['LIMIT']['rowcount']) ? $parsedQuery['LIMIT']['rowcount'] : null;
            $offset = isset($parsedQuery['LIMIT']['offset']) ? $parsedQuery['LIMIT']['offset'] : null;

            if ($limit !== null) {
                $queryBuilder->limit($limit, $offset);
            }
        }

        // Add join conditions (if applicable)
        if (isset($parsedQuery['JOIN'])) {
            foreach ($parsedQuery['JOIN'] as $join) {
                $type = strtolower($join['join_type']);
                $table = $join['table'];
                $on = $join['ref_clause'] ?? [];

                $joinMethod = match ($type) {
                    'left' => 'leftJoin',
                    'right' => 'rightJoin',
                    'outer' => 'outerJoin',
                    default => 'join',
                };

                $queryBuilder->{$joinMethod}($table, function ($joinQuery) use ($on) {
                    foreach ($on as $condition) {
                        if ($condition['expr_type'] === 'colref') {
                            $column = $condition['base_expr'];
                            $operator = $condition['next_expr']['base_expr'] ?? '=';
                            $value = $condition['next_expr']['sub_tree'][0]['base_expr'] ?? null;
                            $joinQuery->on($column, $operator, $value);
                        }
                    }
                });
            }
        }

        return $queryBuilder;
    }

    /**
     * Set the table which the query is targeting.
     *
     * TODO: validate non string table inputs.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|string  $table
     * @param  string|null  $as
     * @return $this
     */
    public function from($table, $as = null)
    {
        $table = Utils::trim($table);
        $as = $as ? Utils::trim($as) : null;

        // Validate the table name
        if (!in_array($table, $this->allowedTables)) {
            throw new \InvalidArgumentException("Table '{$table}' is not allowed.");
        }

        return parent::from($table, $as);
    }

    /**
     * Set the columns to be selected.
     *
     * @param  array|mixed $columns
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $columns = array_map([$this, 'applyAlias'], $columns);

        return parent::select($columns);
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  \Closure|string|array $column
     * @param  mixed                 $operator
     * @param  mixed                 $value
     * @param  string                $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $column = $this->applyAlias($column);

        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param  array|string ...$groups
     * @return $this
     */
    public function groupBy(...$groups)
    {
        $groups = array_map([$this, 'applyAlias'], $groups);

        return parent::groupBy($groups);
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string|\Illuminate\Database\Query\Expression $column
     * @param  string                                       $boolean
     * @param  bool                                         $not
     * @return $this
     */
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false)
    {
        // Apply alias if necessary
        $column = $this->applyAlias($column);

        // Format the values using toDateTime
        $start = $values[0];
        $end = $values[1];

        // TODO: check against order datetime functions.
        // See https://clickhouse.com/docs/en/sql-reference/functions/date-time-functions
        // ['toDateTime', 'toYear', 'toQuarter', 'toMonth', 'toDay', 'toHour', 'toUnixTimestamp'];

        // Check if the values are already formatted with toDateTime or fromUnixTimestamp
        if (!str_contains($start, 'toDateTime(')) {
            $start = "toDateTime('{$start}')";
        }
        if (!str_contains($end, 'toDateTime(')) {
            $end = "toDateTime('{$end}')";
        }

        if ($not) {
            return $this->whereRaw("{$column} < {$start} or {$column} > {$end}", [], $boolean);
        } else {
            return $this->whereRaw("{$column} >= {$start} and {$column} <= {$end}", [], $boolean);
        }
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array|string                   $columns
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        $result = $this->getRaw($columns);

        $columns = Arr::get($result, 'columns', []);
        $results = Arr::get($result, 'results', []);

        $columns = array_map([$this, 'getAliasRef'], $columns);

        return collect($results)->map(function ($row) use ($columns) {
            return array_combine($columns, $row);
        });
    }

    /**
     * Execute the query as a "select" statement and return the raw HogQl response.
     *
     * @param  array|string                   $columns
     * @return \Illuminate\Support\Collection
     */
    public function getRaw($columns = ['*'])
    {
        $columns = Arr::wrap($columns);
        $columns = array_map([$this, 'applyAlias'], $columns);

        return $this->onceWithColumns($columns, function () {
            $query = $this->toSql();
            $bindings = $this->getBindings();

            // Replace placeholders with actual values in the SQL string
            foreach ($bindings as $binding) {
                $query = preg_replace('/\?/', is_numeric($binding) ? $binding : "'".$binding."'", $query, 1);
            }

            $result = Utils::queryApi($query);

            return $result;
        });
    }

    protected function applyAlias($column)
    {
        if (! is_int($column) && ! is_string($column)) {
            return $column;
        }

        return Arr::get($this->aliasMap, $column, $column);
    }

    protected function getAliasRef($column)
    {
        return Arr::get(array_flip($this->aliasMap), $column, $column);
    }
}
