<?php


namespace ExenJer\LaravelFilters;


trait DefaultFilterSetUp
{
    /**
     * @param array $request
     * @return Filter
     */
    public function filter(array $request): Filter
    {
        $this->setFilter($this);

        return $this->apply($request);
    }
}