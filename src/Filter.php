<?php


namespace ExenJer\LaravelFilters;


use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

abstract class Filter
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
     * Cast field to the type.
     * int (integer), bool (boolean), float, double, real, array, object.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Show with soft deleted.
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
     * Custom filter handlers.
     * [name => [filter, arrayFilter]]
     *
     * @var array
     */
    private $filters = [];

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
     * @return self
     */
    protected function apply(array $request): self
    {
        $this->fieldsCheck($request);
        $this->filtersCheck($request);

        return $this;
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function get(array $columns = ['*']): Collection
    {
        return $this()->get($columns);
    }

    /**
     * @param int|null $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return LengthAwarePaginator
     */
    public function paginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $pageName = 'page',
        ?int $page = null
    ): LengthAwarePaginator
    {
        return $this()->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * @param int|null $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return Paginator
     */
    public function simplePaginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $pageName = 'page',
        ?int $page = null
    ): Paginator
    {
        return $this()->simplePaginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Check all fields.
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
     * Check all additional filters.
     *
     * @param array $input
     */
    private function filtersCheck(array $input): void
    {
        /**
         * @var string $name
         * @var callable $filter [0 => all types handler, 2 => array handler]
         */
        foreach ($this->filters as $name => $filter) {
            if (array_key_exists($name, $input)) {
                $value = $input[$name];

               if (! is_array($value)) {
                   $this->castValueType($name, $value);
                   $filter[0]($value);

                   return;
               }

               if ($filter[1]) {
                   $filter[1]($value);

                   return;
               }

               $this->defaultArrayFilterCall($name, $value);
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

        $isArrayValue = is_array($value);

        if (! $isArrayValue) {
            $this->castValueType($key, $value);
        }

        $filterPostfix = $this->generateFilterPostfix($isArrayValue);

        if (method_exists($this->filter, $key . $filterPostfix)) {
            call_user_func([$this->filter, $key . $filterPostfix], $value);
        } else {
            $isArrayValue ? $this->defaultArrayFilterCall($key, $value)
                : $this->defaultFilterCall($key, $value);
        }
    }

    /**
     * Default filter.
     *
     * @param string $field
     * @param mixed $value
     * @return void
     */
    protected function defaultFilterCall(string $field, $value): void
    {
        $this()->where($field, $value);
    }

    /**
     * Default filter for array values.
     *
     * @param string $field
     * @param array $values
     */
    protected function defaultArrayFilterCall(string $field, array $values): void
    {
        $this()->whereIn($field, $values);
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
     * Generate postfix for filter method.
     *
     * @param bool $isArrayValue
     * @return string
     */
    private function generateFilterPostfix(bool $isArrayValue): string
    {
        $name = 'Filter';

        return ($isArrayValue) ? 'Array' . $name : $name;
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
     * @param string $name
     * @param callable $handler
     * @param callable|null $arrayHandler
     */
    public function addFilter(string $name, callable $handler, ?callable $arrayHandler = null)
    {
        $this->filters[$name] = [$handler, $arrayHandler];
    }

    /**
     * @return Builder
     */
    public function __invoke(): Builder
    {
        return $this->builder;
    }
}