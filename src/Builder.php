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

        $query = DB::connection('hogql')->query();

        // Set the table
        if (isset($parsedQuery['FROM'])) {
            $table = $parsedQuery['FROM'][0]['table'];
            $query->from($table);
        }else{
            $query->from(config('hogql.default_table', 'events'));
        }

        // Add select columns
        if (isset($parsedQuery['SELECT'])) {
            $columns = array_map(function ($column) {
                return $column['base_expr'];
            }, $parsedQuery['SELECT']);
            $query->select($columns);
        }

        // Add where conditions
        if (isset($parsedQuery['WHERE'])) {
            foreach ($parsedQuery['WHERE'] as $condition) {
                if ($condition['expr_type'] === 'colref') {
                    $column = $condition['base_expr'];
                    $operator = $condition['next_expr']['operator'] ?? '=';
                    $value = $condition['next_expr']['sub_tree'][0]['base_expr'] ?? null;
                    $query->where($column, $operator, $value);
                }
            }
        }

        // Add group by columns
        if (isset($parsedQuery['GROUP'])) {
            $groups = array_map(function ($group) {
                return $group['base_expr'];
            }, $parsedQuery['GROUP']);
            $query->groupBy($groups);
        }

        // Add order by columns
        if (isset($parsedQuery['ORDER'])) {
            foreach ($parsedQuery['ORDER'] as $order) {
                $column = $order['base_expr'];
                $direction = isset($order['direction']) ? $order['direction'] : 'ASC';
                $query->orderBy($column, $direction);
            }
        }

        // Add limit (if applicable)
        if (isset($parsedQuery['LIMIT'])) {
            $limit = $parsedQuery['LIMIT']['rowcount'];
            $offset = $parsedQuery['LIMIT']['offset'] ?? null;
            $query->limit($limit, $offset);
        }

        // Add join conditions (if applicable)
        if (isset($parsedQuery['JOIN'])) {
            foreach ($parsedQuery['JOIN'] as $join) {
                $type = $join['join_type'];
                $table = $join['table'];
                $on = $join['ref_type'] === 'ON' ? $join['ref_clause'] : null;
                $query->join($table, function($joinQuery) use ($on) {
                    // Process ON clause
                    foreach ($on as $condition) {
                        if ($condition['expr_type'] === 'colref') {
                            $column = $condition['base_expr'];
                            $operator = $condition['next_expr']['operator'] ?? '=';
                            $value = $condition['next_expr']['sub_tree'][0]['base_expr'] ?? null;
                            $joinQuery->on($column, $operator, $value);
                        }
                    }
                }, $type);
            }
        }

        return $query;
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
        $start = "toDateTime('{$values[0]}')";
        $end = "toDateTime('{$values[1]}')";

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

        $columns = array_map([$this, 'applyAlias'], $columns);

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
}
