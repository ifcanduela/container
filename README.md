# Container

Extremely simple key/value container inspired by Pimple.

## Installation

Use [Composer](https://getcomposer.org):

```sh
composer require ifcanduela/container
```

## Usage

Create an `ifcanduela\container\Container` instance to get started. Passing
values in an associative array is optional, and can be done later using
`merge()`:

```php
$container = new ifcanduela\container\Container([
    "alpha" => "a",
    "beta" => "b",
]);

$container->merge([
    "gamma" => "g",
    "delta" => "d",
]);
```

### Simple values

Register values using `set()` and retrieve them using `get()`:

```php
$container->set("db_username", "root@localhost");

echo $container->get("db_username");
```

The Container class implements `ArrayAccess` so setting and getting values can
be done using index notation:

```php
$container["db_username"] = "root@localhost";

echo $container["db_username"];
```

### Closures

Closures can be added in two ways: if your intention is to access the closure
itself, use the `raw()` method, o wrap it in a call to the `raw()` function:

```php
use function ifcanduela\container\raw;

$container->raw("rand", function (int $max) {
    return random_int(0, $max);
});

// using the helper
$container->set("rand", raw(function (...) {...}));

// using array index notation
$container["rand"] = raw(function (...) {...});

$rand = $container->get("rand");

echo $rand(100);
```

If you want to use the closure to build a value, simply use `set()`. These
closures will only run when `get()` is used, will receive the container itself,
and will only be executed once (the same result is returned on every call to
`get()`).

```php
$container->set("logger", function (Container $c) {
    return new Logger($c->get("log_path"));
});

// using array index notation
$container["logger"] = function (...) {...};

$logger = $container->get("logger");

$logger->log(Logger::INFO, "I'm the logger");
```

### Factories

If the closure must be called every time the value is read, for example to build
multiple instances of an object, define it using `factory()` or wrap it
with the `factory()` function:

```php
use function ifcanduela\container\factory;

$container->factory("rand", function (Container $c) {
    return random_int(0, $c->get("max_random_number"));
});

// using the helper
$container->set("rand", factory(function (...) {...}));

// using array index notation
$container["rand"] = factory(function (...) {...});

echo $container->get("rand"); // => 24
echo $container->get("rand"); // => 71
echo $container->get("rand"); // => 13
```

### Checking if a value is defined

Use the `has()` method, or `isset()` when using array index notation:

```php
$container = new \ifcanduela\container\Container([
    "a" => 1,
    "b" => 2,
    "c" => 3,
    "e" => 5,
]);

var_dump($container->has("a")); // => true
var_dump($container->has("d")); // => false
var_dump(isset($container["d"])); // => false
```

## License

[MIT](LICENSE).
