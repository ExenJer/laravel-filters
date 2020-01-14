<?php


namespace ExenJer\LaravelFilters\Contracts;


use Illuminate\Database\Eloquent\Builder;

interface FilterRule
{
    /**
     * Handle all request values except array.
     *
     * @param mixed $value
     * @param mixed $builder
     * @return void
     */
    public function handle($value, $builder): void;

    /**
     * Handle arrays.
     *
     * @param array $values
     * @param mixed $builder
     * @return void
     */
    public function handleArray(array $values, $builder): void;
}
