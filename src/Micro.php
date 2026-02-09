<?php

namespace Dynart\Micro;

use ReflectionClass;
use ReflectionException;

/**
 * Micro PHP Dependency Injection
 */
class Micro {

    protected static ?App $app = null;
    protected static array $classes = [];
    protected static array $instances = [];

    /**
     * Sets the application instance and runs it
     *
     * First it sets the instance, then calls the `fullInit()` and `fullProcess()` methods of the `$app`.
     *
     * @throws MicroException if the instance was set before
     */
    public static function run(App $app): void {
        if (self::$app) {
            throw new MicroException("App was instantiated before!");
        }
        self::$app = $app;
        $app->fullInit();
        $app->fullProcess();
    }

    public static function app(): ?App {
        return self::$app;
    }

    /**
     * Adds a class for an interface
     *
     * For example:
     *
     * <pre>
     * Micro::add(ConfigInterface::class, Config::class);
     * </pre>
     *
     * or
     *
     * <pre>
     * Micro::add(Config::class);
     * </pre>
     */
    public static function add(string $interface, ?string $class = null): void {
        if ($class != null && !(is_subclass_of($class, $interface))) {
            throw new MicroException("$class does not implement $interface");
        }
        self::$classes[$interface] = $class;
    }

    public static function hasInterface(string $interface): bool {
        return array_key_exists($interface, self::$classes);
    }

    /**
     * Returns with the class for the given interface
     * @throws MicroException If the interface wasn't added
     */
    public static function getClass(string $interface): string {
        if (!self::hasInterface($interface)) {
            throw new MicroException("$interface was not added");
        }
        return self::$classes[$interface] ?? $interface;
    }

    /**
     * Creates the singleton instance for the given interface, stores it in `$instances`, then returns with it
     *
     * It returns instantly if the instance was stored before.
     *
     * @throws MicroException
     */
    public static function get(string $interface, array $parameters = [], array $dependencyStack = []): object {
        if (array_key_exists($interface, self::$instances)) {
            return self::$instances[$interface];
        }
        $result = self::create(self::getClass($interface), $parameters, $dependencyStack);
        self::$instances[$interface] = $result;
        return $result;
    }

    public static function interfaces(): array {
        return array_keys(self::$classes);
    }

    /**
     * Creates an instance for the given class
     *
     * If the class has a `postConstruct()` method it will be called after creation. It can be used for lazy injection.
     *
     * @throws MicroException
     */
    public static function create(string $class, array $parameters = [], array $dependencyStack = []): object {
        if (in_array($class, $dependencyStack)) {
            throw new MicroException("Circular dependency: ".join(" <- ", $dependencyStack));
        }
        $dependencyStack[] = $class;
        try {
            $reflectionClass = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new MicroException("Couldn't create reflection class for `$class`");
        }
        $dependencies = self::createDependencies($class, $reflectionClass, $dependencyStack);
        try {
            $result = $reflectionClass->newInstanceArgs(array_merge($dependencies, $parameters));
        } catch (ReflectionException $e) {
            throw new MicroException("Couldn't create class `$class`:\nMessage: ".$e->getMessage()."\n".$e->getTraceAsString());
        }
        if (method_exists($result, 'postConstruct')) {
            $result->postConstruct();
        }
        return $result;
    }

    private static function createDependencies(string $class, ReflectionClass $reflectionClass, array $dependencyStack = []): array {
        $result = [];
        $constructor = $reflectionClass->getConstructor();
        if (!$constructor) {
            return $result;
        }
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type || $type->isBuiltin()) {
                continue;
            }
            $interface = $type->getName();
            if (self::hasInterface($interface)) {
                $result[] = self::get($interface, [], $dependencyStack);
            } else {
                throw new MicroException("Non existing dependency `$interface` for `$class`");
            }
        }
        return $result;
    }

    public static function getCallable(mixed $callable): mixed {
        return self::isMicroCallable($callable) ? [Micro::get($callable[0]), $callable[1]] : $callable;
    }

    /**
     * Returns true if the `$callable` is a Micro Framework callable
     *
     * Micro Framework callable means: an array with two strings.
     * The first one is the class name, the second is the method name.
     */
    public static function isMicroCallable(mixed $callable): bool {
        return is_array($callable)
            && count($callable) == 2
            && is_string($callable[0])
            && is_string($callable[1]);
    }
}
