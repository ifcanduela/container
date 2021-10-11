<?php

namespace ifcanduela\container;

abstract class AbstractValue
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }

    public static function wrap($value)
    {
        return new static($value);
    }
}
