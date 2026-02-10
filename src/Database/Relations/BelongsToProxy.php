<?php

declare(strict_types=1);

namespace Plugs\Database\Relations;

use Plugs\Base\Model\PlugModel;
use Plugs\Database\QueryBuilder;

class BelongsToProxy
{
    protected $parent;
    protected $builder;
    protected $foreignKey;
    protected $ownerKey;

    public function __construct(PlugModel $parent, QueryBuilder $builder, string $foreignKey, string $ownerKey)
    {
        $this->parent = $parent;
        $this->builder = $builder;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getOwnerKey(): string
    {
        return $this->ownerKey;
    }

    public function getBuilder(): QueryBuilder
    {
        return $this->builder;
    }

    public function getRelated(): string
    {
        return $this->builder->getModel();
    }

    public function first()
    {
        return $this->builder->first();
    }

    public function associate(PlugModel $model)
    {
        $this->parent->setAttribute($this->foreignKey, $model->getAttribute($this->ownerKey));

        return $this->parent;
    }

    public function dissociate()
    {
        $this->parent->setAttribute($this->foreignKey, null);

        return $this->parent;
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
