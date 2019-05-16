<?php


namespace ExenJer\LaravelFilters;


use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

abstract class AbstractFilter
{
    /**
     * Current model.
     *
     * @var Model
     */
    protected $model;

    /**
     * Allowed request fields for filtering.
     *
     * @var array
     */
    protected $fields = [];

    /**
     * Cast field to the type
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Show with soft deleted
     *
     * @var bool
     */
    protected $withDeletions = false;

    /**
     * Current query builder for the model.
     *
     * @var Builder
     */
    private $builder;

    /**
     * Current filter class.
     *
     * @var self
     */
    private $filter;

    /**
     * AbstractFilter constructor.
     */
    public function __construct()
    {
        $this->setUp();
    }

    /**
     * Main process of filtering.
     *
     * @param array $request Array of request key - values
     * @return Collection
     */
    protected function apply(array $request): Collection
    {
        $this->fieldsCheck($request);

        return $this()->get();
    }

    /**
     * Check all fields
     *
     * @param array $input
     */
    private function fieldsCheck(array $input): void
    {
        foreach ($this->fields as $field) {
            if (array_key_exists($field, $input)) {
                $this->caller($field, $input);
            }
        }
    }

    /**
     * Call special method by field.
     *
     * @param string $key Field
     * @param array $input
     * @param string|null $value
     */
    private function caller(string $key, array $input, ?string $value = null): void
    {
        if (! $value) {
            $value = $input[$key];
        }

        $this->castValueType($key, $value);

        method_exists($this->filter, $key . 'Filter') ?
            call_user_func([$this->filter, $key . 'Filter'], $value)
            : $this->defaultFilterCall($key, $value);
    }

    /**
     * Default filter.
     *
     * @param string $field
     * @param mixed $value
     * @return void
     */
    protected function defaultFilterCall(string $field, $value)
    {
        $this()->where($field, $value);
    }

    /**
     * @return self
     */
    protected function getFilter(): self
    {
        return $this->filter;
    }

    /**
     * @param $filter
     */
    protected function setFilter(self $filter): void
    {
        $this->filter = $filter;
    }

    /**
     * Initialize default data.
     *
     * @return void
     */
    private function setUp(): void
    {
        $this->builder = $this->model::query();
        $this->withTrashed();
    }

    /**
     * Show queries with trashed.
     *
     * @return void
     */
    private function withTrashed(): void
    {
        if ($this->withDeletions) {
            $this()->withTrashed();
        }
    }

    /**
     * Cast value to the type by cast array.
     *
     * @param string $field
     * @param $value
     */
    private function castValueType(string $field, &$value): void
    {
        if (array_key_exists($field, $this->casts)) {
            $type = $this->casts[$field];

            switch ($type) {
                case 'integer':
                case 'int':
                    $value = (int) $value;
                    break;
                case 'boolean':
                case 'bool':
                    $value = (bool) $value;
                    break;
                case 'float':
                    $value = (float) $value;
                    break;
                case 'double':
                    $value = (double) $value;
                    break;
                case 'real':
                    $value = (real) $value;
                    break;
                case 'array':
                    $value = (array) $value;
                    break;
                case 'object':
                    $value = (object) $value;
                    break;
            }
        }
    }

    /**
     * @return Builder
     */
    public function __invoke(): Builder
    {
        return $this->builder;
    }
}