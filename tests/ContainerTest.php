<?php

use ifcanduela\container\Container;
use ifcanduela\container\exception\ContainerException;
use ifcanduela\container\exception\NotFoundException;
use ifcanduela\container\FactoryValue;
use ifcanduela\container\RawValue;

use function ifcanduela\container\raw;
use function ifcanduela\container\factory;

use PHPUnit\Framework\TestCase;

class ClassFixture
{
    public int $number;

    public function __construct(?int $number)
    {
        if ($number === null) {
            $number = random_int(1, 10);
        }

        $this->number = $number;
    }
}

class ContainerTest extends TestCase
{
    public function testCreateContainer()
    {
        $c = new Container();

        $this->assertInstanceOf(Container::class, $c);

        $c->set("alpha", "beta");

        $this->assertEquals("beta", $c->get("alpha"));
        $this->assertEquals("beta", $c["alpha"]);
    }

    public function testKeys()
    {
        $c = new Container(["alpha" => 1, "beta" => 2]);
        $c->factory("delta", function () {
            return new ClassFixture(null);
        });

        $this->assertEquals(["alpha", "beta", "delta"], $c->keys());
    }

    public function testInvalidNumericKeys()
    {
        try {
            $c = new Container(["a", "b", "c"]);
        } catch (ContainerException $e) {
            $this->assertEquals("Invalid key `0`", $e->getMessage());
        }

        try {
            $c = new Container();
            $c->merge(["alpha", "beta"]);
        } catch (ContainerException $e) {
            $this->assertFalse(isset($c["0"]));
            $this->assertEquals("Invalid key `0`", $e->getMessage());
        }

        try {
            $c = new Container();
            $c->set("123456", "numbers");
        } catch (ContainerException $e) {
            $this->assertFalse($c->has("123456"));
            $this->assertEquals("Invalid key `123456`", $e->getMessage());
        }

        try {
            $c = new Container();
            $c["1.5"] = function () {};
        } catch (ContainerException $e) {
            $this->assertFalse($c->has("1.5"));
            $this->assertEquals("Invalid key `1.5`", $e->getMessage());
        }
    }

    public function testDefineValue()
    {
        $c = new Container();
        $c["alpha"] = "beta";
        $c->set("gamma", function (Container $c) {
            return $c["alpha"] . "-2";
        });

        $this->assertTrue($c->has("alpha"));
        $this->assertTrue($c->has("gamma"));

        $this->assertEquals("beta", $c->get("alpha"));
        $this->assertEquals("beta-2", $c["gamma"]);

        try {
            $c->get("notfound");
        } catch (NotFoundException $e) {
            $this->assertEquals("No value found for key `notfound`", $e->getMessage());
        }

        $shouldBeBeta = isset($c["alpha"]) ? $c["alpha"] : "delta";
        $shouldBeEpsilon = $c["delta"] ?? "epsilon";

        $this->assertEquals("beta", $shouldBeBeta);
        $this->assertEquals("epsilon", $shouldBeEpsilon);
    }

    public function testDefineCallableValue()
    {
        $c = new Container();

        $c->set("cb1", function () {
            return new ClassFixture(10);
        });

        $c->raw("cb2", function () {
            return "always callable";
        });

        $c->set("cb1", function () {
            return new ClassFixture(20);
        });

        $this->assertEquals(20, $c->get("cb1")->number);
        $this->assertInstanceOf(Closure::class, $c->get("cb2"));
        $this->assertEquals("always callable", $c->get("cb2")());
        $this->assertEquals(20, $c->get("cb1")->number);

        try {
            $c->set("cb1", "exception");
        } catch (ContainerException $e) {
            $this->assertEquals("Cannot overwrite key `cb1` because it has been resolved previously", $e->getMessage());
        }
    }

    public function testDefineReturnableCallableValue()
    {
        $c = new Container();
        $c->raw("callme", function () {
            return "You called me?";
        });

        $callable = $c->get("callme");

        $this->assertInstanceOf(Closure::class, $callable);
        $this->assertTrue($c->has("callme"));
        $this->assertEquals("You called me?", $callable());

        $c->set("callme", "You called?");
        $this->assertEquals("You called?", $c->get("callme"));
    }

    public function testDefineFactory()
    {
        $c = new Container();
        $number = 1;

        $c->factory(ClassFixture::class, function (Container $c) use (&$number) {
            return new ClassFixture($number++);
        });

        $cf1 = $c->get(ClassFixture::class);
        $cf2 = $c->get(ClassFixture::class);

        $this->assertNotEquals($cf1->number, $cf2->number);
        $this->assertEquals(1, $cf1->number);
        $this->assertEquals(2, $cf2->number);
    }

    public function testCannotUnsetKeys()
    {
        $c = new Container(["alpha" => "beta"]);

        try {
            unset($c["alpha"]);
        } catch (ContainerException $e) {
            $this->assertEquals("Cannot unset keys on this container", $e->getMessage());
        }
    }

    public function testAliases()
    {
        $c = new Container(["alpha" => "beta"]);

        try {
            $c->alias(0, "alpha");
        } catch (ContainerException $e) {
            $this->assertEquals("Invalid alias `0`", $e->getMessage());
        }

        try {
            $c->alias("gamma", "beta");
        } catch (NotFoundException $e) {
            $this->assertEquals("Cannot alias non-existing key `beta`", $e->getMessage());
        }

        try {
            $c->alias("alpha", "alpha");
        } catch (ContainerException $e) {
            $this->assertEquals("Existing key `alpha` cannot be used as alias", $e->getMessage());
        }

        $c->alias("gamma", "alpha");
        $beta = $c->get("gamma");
        $this->assertEquals("beta", $beta);
    }

    public function testFactoryHelpers()
    {
        $c = new Container();

        $int = random_int(1, 100);

        $c["f"] = factory(function () use (&$int) {
            return $int++;
        });

        $n1 = $c["f"];
        $n2 = $c["f"];

        $this->assertEquals($n1 + 1, $n2);

        $c["f"] = FactoryValue::wrap(function () use (&$int) {
            return $int++;
        });

        $n1 = $c["f"];
        $n2 = $c["f"];

        $this->assertEquals($n1 + 1, $n2);

        $c["f"] = new FactoryValue(function () use (&$int) {
            return $int++;
        });

        $n1 = $c["f"];
        $n2 = $c["f"];

        $this->assertEquals($n1 + 1, $n2);
    }

    public function testRawHelpers()
    {
        $c = new Container();

        $c["r"] = raw(function ($a, $b) {
            return max($a, $b);
        });

        $this->assertEquals(2, $c["r"](1, 2));
        $this->assertEquals(10, $c["r"](10, 2));

        $c["r"] = RawValue::wrap(function ($a, $b) {
            return max($a, $b);
        });

        $this->assertEquals(2, $c["r"](1, 2));
        $this->assertEquals(10, $c["r"](10, 2));

        $c["r"] = new RawValue(function ($a, $b) {
            return max($a, $b);
        });

        $this->assertEquals(2, $c["r"](1, 2));
        $this->assertEquals(10, $c["r"](10, 2));
    }

    public function testRawAndFactoryValueClasses()
    {
        $r = new RawValue(function () {});

        $this->assertInstanceOf(RawValue::class, $r);
        $this->assertInstanceOf(Closure::class, $r->getValue());

        $r = RawValue::wrap(function () {});

        $this->assertInstanceOf(RawValue::class, $r);
        $this->assertInstanceOf(Closure::class, $r->getValue());

        $r = new FactoryValue(function () {});

        $this->assertInstanceOf(FactoryValue::class, $r);
        $this->assertInstanceOf(Closure::class, $r->getValue());

        $r = FactoryValue::wrap(function () {});

        $this->assertInstanceOf(FactoryValue::class, $r);
        $this->assertInstanceOf(Closure::class, $r->getValue());
    }
}
