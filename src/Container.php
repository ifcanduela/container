<?php

namespace ifcanduela\container;

use ArrayAccess;
use Closure;
use ifcanduela\container\exception\ContainerException;
use ifcanduela\container\exception\NotFoundException;

class Container implements ArrayAccess
{
    protected array $ids = [];

    protected array $values = [];

    protected array $factories = [];

    protected array $resolvedCallables = [];

    protected array $rawCallables = [];

    protected array $aliases = [];

    public function __construct(array $items = [])
    {
        $this->merge($items);
    }

    public function merge(iterable $items): void
    {
        foreach ($items as $id => $value) {
            $this->set($id, $value);
        }
    }

    public function keys(): array
    {
        return array_keys($this->ids);
    }

    public function set(string $id, $value): void
    {
        if (is_numeric($id)) {
            throw new ContainerException("Invalid key `{$id}`");
        }

        if ($this->isResolvedCallableValue($id)) {
            throw new ContainerException("Cannot overwrite key `{$id}` because it has been resolved previously");
        }

        if ($value instanceof FactoryValue) {
            $this->factories[$id] = true;
        } elseif ($value instanceof RawValue) {
            $this->rawCallables[$id] = true;
        }

        $this->values[$id] = $value;
        $this->ids[$id] = true;
    }

    public function raw(string $id, Closure $closure): void
    {
        $this->set($id, new RawValue($closure));
    }

    public function factory(string $id, Closure $factory): void
    {
        $this->set($id, new FactoryValue($factory));
    }

    public function get(string $id)
    {
        if ($this->isAlias($id)) {
            $id = $this->aliases[$id];
        }

        if (!$this->has($id)) {
            throw new NotFoundException("No value found for key `{$id}`");
        }

        if ($this->isResolvedCallableValue($id)) {
            return $this->resolvedCallables[$id];
        }

        $value = $this->values[$id];

        if ($value instanceof FactoryValue) {
            return  $value->getValue()($this);
        }

        if ($value instanceof RawValue) {
            return $value->getValue();
        }

        if ($value instanceof Closure) {
            $value = $value($this);
            $this->resolvedCallables[$id] = $value;
        }

        return $value;
    }

    public function has(string $id): bool
    {
        return isset($this->ids[$id]);
    }

    public function alias(string $alias, string $id): void
    {
        if (is_numeric($alias)) {
            throw new ContainerException("Invalid alias `{$alias}`");
        }

        if (!$this->has($id)) {
            throw new NotFoundException("Cannot alias non-existing key `{$id}`");
        }

        if ($this->has($alias)) {
            throw new ContainerException("Existing key `{$alias}` cannot be used as alias");
        }

        $this->ids[$alias] = true;
        $this->aliases[$alias] = $id;
    }

    protected function isAlias(string $alias): bool
    {
        return isset($this->aliases[$alias]);
    }

    protected function isResolvedCallableValue(string $id): bool
    {
        return isset($this->resolvedCallables[$id]);
    }

    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        throw new ContainerException("Cannot unset keys on this container");
    }
}
