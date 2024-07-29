<?php

namespace MOIREI\HogQl;

use Illuminate\Support\Facades\DB;

class HogQl
{
    /**
     * Create a new Posthog query.
     *
     * @param  string|null                             $table
     * @return \Illuminate\Database\Query\Builder
     */
    public function query(?string $table = null)
    {
        $table = $table ?? config('hogql.default_table', 'events');
        return DB::connection('hogql')->table($table);
    }

    /**
     * Set the columns to be selected.
     *
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Query\Builder
     */
    public function select($columns = ['*'])
    {
        return $this->query()->select($columns);
    }

    /**
     * Begin querying the defined Eloquent Model for Posthog.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function model()
    {
        $class = config('hogql.model', Model::class);
        return (new $class)->newQuery();
    }

    /**
     * Alias of `model`.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function eloquent()
    {
        return $this->model();
    }

    /**
     * Parse a raw string query and return a Builder object.
     *
     * @param string $query
     * @return \Illuminate\Database\Query\Builder
     */
    public function parse(string $query)
    {
        return Builder::parse($query);
    }

    /**
     * Get a Posthog analytics query using HogQL.
     */
    public function get(string $query)
    {
        return $this->parse($query)->get();
    }

    /**
     * Get a Posthog analytics query using HogQL and return the raw result.
     */
    public function getRaw(string $query)
    {
        return $this->parse($query)->getRaw();
    }
}
