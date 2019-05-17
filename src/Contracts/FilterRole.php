<?php


namespace ExenJer\LaravelFilters\Contracts;


interface FilterRole
{
    /**
     * Handle all request values except array.
     *
     * @param mixed $value
     */
    public function handle($value): void;

    /**
     * Handle arrays.
     *
     * @param array $values
     */
    public function arrayHandle(array $values): void;
}