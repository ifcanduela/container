<?php

namespace ifcanduela\container;

function raw($value): RawValue
{
    return new RawValue($value);
}

function factory(callable $factory): FactoryValue
{
    return new FactoryValue($factory);
}
