<?php

declare(strict_types=1);

namespace Plugs\Database\Relations;

use Plugs\Base\Model\PlugModel;
use Plugs\Database\QueryBuilder;

class HasManyThroughProxy
{
    protected $parent;
    protected $builder;
    protected $firstKey;
    protected $secondKey;

    public function __construct(PlugModel $parent, QueryBuilder $builder, string $firstKey, string $secondKey)
    {
        $this->parent = $parent;
        $this->builder = $builder;
        $this->firstKey = $firstKey;
        $this->secondKey = $secondKey;
    }

    public function get()
    {
        return $this->builder->get();
    }

    public function getBuilder(): QueryBuilder
    {
        return $this->builder;
    }

    public function getRelated(): string
    {
        return $this->builder->getModel();
    }

    public function __call($method, $parameters)
    {
        $result = call_user_func_array([$this->builder, $method], $parameters);

        if ($result instanceof QueryBuilder) {
            return $this;
        }

        return $result;
    }
}
