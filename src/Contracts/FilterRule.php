<?php


namespace ExenJer\LaravelFilters\Contracts;


use Illuminate\Database\Eloquent\Builder;

interface FilterRule
{
    /**
     * Handle all request values except array.
     *
     * @param mixed $value
     * @param Builder $builder
     * @return void
     */
    public function handle($value, Builder $builder): void;

    /**
     * Handle arrays.
     *
     * @param array $values
     * @param Builder $builder
     * @return void
     */
    public function handleArray(array $values, Builder $builder): void;
}