<?php

namespace Dynart\Micro;

/**
 * Micro PHP Dependency Injection
 *
 * @package Dynart\Micro
 */
class Micro {

    /**
     * Holds the instance of the application
     * @var App
     */
    protected static $instance;

    /**
     * Stores the classes in [interface => class] format, the class can be null
     * @var array
     */
    protected static $classes = [];

    /**
     * Stores the instances in [interface => instance] format
     * @var array
     */
    protected static $instances = [];

    /**
     * Sets the application instance and runs it
     *
     * First it sets the instance, then calls the `fullInit()` and `fullProcess()` methods of the `$app`.
     *
     * @throws MicroException if the instance was set before
     * @param App $app The application for init and process
     */
    public static function run(App $app): void {
        if (self::$instance) {
            throw new MicroException("App was instantiated before!");
        }
        self::$instance = $app;
        $app->fullInit();
        $app->fullProcess();
    }

    /**
     * Returns the instance of the application
     * @return mixed The instance of the application
     */
    public static function instance() {
        return self::$instance;
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
     *
     * @param string $interface The interface
     * @param null $class The class, it can be null, then the interface itself a class
     */
    public static function add(string $interface, $class = null) {
        if ($class != null && !(is_subclass_of($class, $interface))) {
            throw new MicroException("$class does not implement $interface");
        }
        self::$classes[$interface] = $class;
    }

    /**
     * @param string $interface
     * @return bool Is the interface was added?
     */
    public static function hasInterface(string $interface) {
        return array_key_exists($interface, self::$classes);
    }

    /**
     * Returns with the class for the given interface
     * @throws MicroException If the interface wasn't added
     * @param string $interface The interface
     * @return string The class for the interface
     */
    public static function getClass(string $interface) {
        if (!self::hasInterface($interface)) {
            throw new MicroException("$interface was not added");
        }
        return isset(self::$classes[$interface]) ? self::$classes[$interface] : $interface;
    }

    /**
     * Creates the singleton instance for the given interface, stores it in `$instances`, then returns with it
     *
     * It returns instantly if the instance was stored before.
     *
     * @param string $interface The interface
     * @param array $parameters The parameters for the constructor. Important: only the parameters that are not injected!
     * @param array $dependencyStack
     * @return mixed
     */
    public static function get(string $interface, array $parameters = [], array $dependencyStack = []) {
        if (array_key_exists($interface, self::$instances)) {
            return self::$instances[$interface];
        }
        $result = self::create(self::getClass($interface), $parameters, $dependencyStack);
        self::$instances[$interface] = $result;
        return $result;
    }

    /**
     * Returns with all of the interfaces in an array
     * @return array All of the added interfaces
     */
    public static function interfaces() {
        return array_keys(self::$classes);
    }

    /**
     * Creates an instance for the given interface
     *
     * In the following example, the `Something` class constructor will get the `Config` instance
     * and the 'someParameterValue' in the `$someParameter`.
     *
     * <pre>
     * use Dynart\Micro\Micro;
     * use Dynart\Micro\App;
     * use Dynart\Micro\Config;
     *
     * class Something {
     *   private $someParameter;
     *   public function __construct(Config $config, $someParameter) {
     *     $this->someParameter = $someParameter;
     *   }
     *
     *   public function someParameter() {
     *     return $this->someParameter;
     *   }
     * }
     *
     * class MyApp extends App {
     *   private $something;
     *   public function __construct() {
     *     Micro::add(Config::class);
     *     Micro::add(Something::class);
     *   }
     *
     *   public function init() {
     *     $this->something = Micro::create(Something::class, ['someParameterValue']);
     *   }
     *
     *   public function process() {
     *     echo $this->something->someParameter();
     *   }
     * }
     * </pre>
     *
     * If the class has a `postConstruct()` method it will be called after creation. It can be used for lazy injection.
     *
     * @param string $class The name of the class
     * @param array $parameters Parameters for the constructor. Important: only the parameters that are not injected!
     * @param array $dependencyStack
     * @return mixed
     */
    public static function create(string $class, array $parameters = [], array $dependencyStack = []) {
        if (in_array($class, $dependencyStack)) {
            throw new MicroException("Circular dependency: ".join(" <- ", $dependencyStack));
        }
        $dependencyStack[] = $class;
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new MicroException("Couldn't create reflection class for $class");
        }
        $dependencies = self::createDependencies($class, $reflectionClass, $dependencyStack);
        $result = $reflectionClass->newInstanceArgs(array_merge($dependencies, $parameters));
        if (method_exists($result, 'postConstruct')) {
            $result->postConstruct();
        }
        return $result;
    }

    /**
     * Creates the singleton dependencies for a given class and returns with it as an array
     * @param string $class The class name
     * @param \ReflectionClass $reflectionClass
     * @param array $dependencyStack
     * @return array The created singleton instances
     */
    private static function createDependencies(string $class, \ReflectionClass $reflectionClass, array $dependencyStack = []) {
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


    /**
     * Creates and instance of the callable if needed, then returns with it
     * @param $callable
     * @return mixed
     */
    public static function getCallable($callable) {
        return self::isMicroCallable($callable) ? [Micro::get($callable[0]), $callable[1]] : $callable;
    }

    /**
     * Returns true if the `$callable` is a Micro Framework callable
     *
     * Micro Framework callable means: an array with two strings.
     * The first one is the class name, the second is the method name.
     *
     * Example:
     * <pre>
     * [Something::class, 'theMethodName']
     * </pre>
     *
     * @param $callable mixed The callable for the check
     * @return bool
     */
    public static function isMicroCallable($callable): bool {
        return is_array($callable)
            && count($callable) == 2
            && is_string($callable[0])
            && is_string($callable[1]);
    }
}