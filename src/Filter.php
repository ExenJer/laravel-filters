<?php


namespace ExenJer\LaravelFilters;


use ExenJer\LaravelFilters\Contracts\FilterRule;
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
     * Request fields that need to ignore
     *
     * @var array
     */
    protected $exclude = [];

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
     * Additional filter handlers.
     * [name => [filter, arrayFilter]]
     *
     * @var array
     */
    private $filters = [];

    /**
     * Additional filter handle classes.
     *
     * @var FilterRule[]
     */
    private $filterClasses = [];

    /**
     * List of called fields.
     *
     * @var array
     */
    private $calledFieldList = [];

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
        $this->checks($request);

        $this->callDefaultMethods($request);

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
     * Call all checks methods.
     *
     * @param array $request
     * @return void
     */
    private function checks(array $request): void
    {
        $this->fieldsCheck($request);
        $this->filtersCheck($request);
        $this->filterClassesCheck($request);
    }

    /**
     * Check all fields.
     *
     * @param array $input
     * @return void
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
     * @return void
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
                   array_push($this->calledFieldList, $name);

                   return;
               }

               if ($filter[1]) {
                   $filter[1]($value);
                   array_push($this->calledFieldList, $name);

                   return;
               }
            }
        }
    }

    /**
     * Check all additional filter classes.
     *
     * @param array $input
     * @return void
     */
    private function filterClassesCheck(array $input): void
    {
        /** @var FilterRule $filterClass */
        foreach ($this->filterClasses as $name => $filterClass) {
            if (array_key_exists($name, $input)) {
                $value = $input[$name];
                if (! is_array($value)) {
                    $filterClass->handle($value, $this->builder);
                    array_push($this->calledFieldList, $name);

                    return;
                }

                $filterClass->arrayHandle($value, $this->builder);
                array_push($this->calledFieldList, $name);
            }
        }
    }

    /**
     * Call special method by field.
     *
     * @param string $key Field
     * @param array $input
     * @param string|null $value
     * @return void
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
            array_push($this->calledFieldList, $key);
        }
    }

    /**
     * Call default method if field was not previously called.
     *
     * @param array $input
     * @return void
     */
    private function callDefaultMethods(array $input): void
    {
        foreach ($input as $key => $value) {
            if (! in_array($key, $this->calledFieldList)) {
                (is_array($value)) ? $this->defaultArrayFilterCall($key, $value)
                    : $this->defaultFilterCall($key, $value);
            }
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
        if(!in_array($field, $this->exclude))
            $this()->where($field, $value);
    }

    /**
     * Default filter for array values.
     *
     * @param string $field
     * @param array $values
     * @return void
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
     * @return void
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
     * Add additional filter rule.
     *
     * @param string $name
     * @param callable $handler
     * @param callable|null $arrayHandler
     * @return void
     */
    public function addFilter(string $name, callable $handler, ?callable $arrayHandler = null): void
    {
        $this->filters[$name] = [$handler, $arrayHandler];
    }

    /**
     * Add additional filter class.
     *
     * @param string $name
     * @param FilterRule $filter
     */
    public function addFilterClass(string $name, FilterRule $filter): void
    {
        $this->filterClasses[$name] = $filter;
    }

    /**
     * @return Builder
     */
    public function __invoke(): Builder
    {
        return $this->builder;
    }
}