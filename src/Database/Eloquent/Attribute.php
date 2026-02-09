<?php

declare(strict_types=1);

namespace Plugs\Database\Eloquent;

use Closure;

class Attribute
{
    /**
     * The callback that gets the attribute's value.
     *
     * @var \Closure
     */
    public $get;

    /**
     * The callback that sets the attribute's value.
     *
     * @var \Closure
     */
    public $set;

    /**
     * Create a new attribute accessor/mutator.
     *
     * @param  \Closure|null  $get
     * @param  \Closure|null  $set
     * @return void
     */
    public function __construct(Closure $get = null, Closure $set = null)
    {
        $this->get = $get;
        $this->set = $set;
    }

    /**
     * Create a new attribute accessor.
     *
     * @param  \Closure  $get
     * @return static
     */
    public static function get(Closure $get)
    {
        return new static($get);
    }

    /**
     * Create a new attribute mutator.
     *
     * @param  \Closure  $set
     * @return static
     */
    public static function set(Closure $set)
    {
        return new static(null, $set);
    }

    /**
     * Create a new attribute accessor and mutator.
     *
     * @param  \Closure|null  $get
     * @param  \Closure|null  $set
     * @return static
     */
    public static function make(Closure $get = null, Closure $set = null)
    {
        return new static($get, $set);
    }
}
