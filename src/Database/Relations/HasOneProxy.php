<?php

declare(strict_types=1);

namespace Plugs\Database\Relations;

use Plugs\Base\Model\PlugModel;
use Plugs\Database\QueryBuilder;

class HasOneProxy
{
    protected $parent;
    protected $builder;
    protected $foreignKey;
    protected $localKey;

    public function __construct(PlugModel $parent, QueryBuilder $builder, string $foreignKey, string $localKey)
    {
        $this->parent = $parent;
        $this->builder = $builder;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    public function first()
    {
        return $this->builder->first();
    }

    public function create(array $attributes = [])
    {
        $attributes[$this->foreignKey] = $this->parent->getAttribute($this->localKey);

        $relatedModel = $this->builder->getModel();

        return $relatedModel::create($attributes);
    }

    public function save(PlugModel $model)
    {
        $model->setAttribute($this->foreignKey, $this->parent->getAttribute($this->localKey));

        return $model->save();
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
