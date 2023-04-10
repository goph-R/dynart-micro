<?php

namespace Dynart\Micro;

/**
 * Micro PHP Application with dependency injection
 *
 * @package Dynart\Micro
 */
abstract class App {

    const CONFIG_BASE_URL = 'app.base_url';
    const CONFIG_ROOT_PATH = 'app.root_path';
    const CONFIG_USE_ANNOTATIONS = 'app.use_annotations';

    private static $instance;

    /**
     * Returns the instance of the application
     * @return mixed The singleton instance of the application
     */
    public static function instance() {
        return self::$instance;
    }

    /**
     * Runs the application and sets the instance
     *
     * Calls the init() and process() methods of the $app
     *
     * @throws AppException if the instance was set before
     * @param App $app The application for init and process
     */
    public static function run(App $app): void {
        if (self::$instance) {
            throw new AppException("App was instantiated before!");
        }
        self::$instance = $app;
        $app->init();
        $app->process();
    }

    /**
     * Stores the classes in [interface => class] format, the class can be null
     * @var array
     */
    protected $classes = [];

    /**
     * Stores the instances in [interface => instance] format
     * @var array
     */
    protected $instances = [];

    /**
     * Abstract function for initialize the application
     * @return mixed
     */
    abstract public function init();

    /**
     * Abstract function for processing the application
     * @return mixed
     */
    abstract public function process();

    /**
     * Finishes the application
     * @param string $content Content for the output
     */
    public function finish(string $content = '') {
        exit($content);
    }

    /**
     * Adds a class for an interface
     *
     * For example:
     *
     * <pre>
     * $app->add(ConfigInterface::class, Config::class);
     * </pre>
     *
     * or
     *
     * <pre>
     * $app->add(Config::class);
     * </pre>
     *
     * @param string $interface The interface
     * @param null $class The class, it can be null, then the interface itself a class
     */
    public function add(string $interface, $class = null) {
        if ($class != null && !($class instanceof $interface)) {
            throw new AppException("$class does not implement $interface");
        }
        $this->classes[$interface] = $class;
    }

    /**
     * @param string $interface
     * @return bool Is the interface was set?
     */
    public function hasInterface(string $interface) {
        return array_key_exists($interface, $this->classes);
    }

    /**
     * Returns with the class for the given interface
     * @throws AppException If the interface wasn't added
     * @param string $interface The interface
     * @return string The class for the interface
     */
    public function getClass(string $interface) {
        if (!$this->hasInterface($interface)) {
            throw new AppException("$interface was not added");
        }
        return isset($this->classes[$interface]) ? $this->classes[$interface] : $interface;
    }

    /**
     * Creates the singleton instance for the given interface, stores it in `$instances`, then returns with it
     *
     * It will return instantly if the instance was stored before.
     *
     * @param string $interface The interface
     * @param array $parameters The parameters for the constructor. Important: only the parameters that are not injected
     * @return mixed
     */
    public function get(string $interface, array $parameters = []) {
        if (array_key_exists($interface, $this->instances)) {
            return $this->instances[$interface];
        }
        $result = $this->create($this->getClass($interface), $parameters);
        $this->instances[$interface] = $result;
        return $result;
    }

    /**
     * Creates an instance for the given interface
     *
     * In the following example, the `Something` class constructor will get the `Config` instance
     * and the 'someParameterValue' in the `$someParameter`.
     *
     * <pre>
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
     *     $this->add(Config::class);
     *     $this->add(Something::class);
     *   }
     *
     *   public function init() {
     *     $this->something = $this->create(Something::class, ['someParameterValue']);
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
     * @param array $parameters Parameters for the constructor. Important: only the parameters that are not injected
     * @return mixed
     */
    public function create(string $class, array $parameters = []) {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new AppException("Couldn't create reflection class for $class");
        }
        $dependencies = $this->createDependencies($reflectionClass);
        $result = $reflectionClass->newInstanceArgs(array_merge($dependencies, $parameters));
        if (method_exists($result, 'postConstruct')) {
            $result->postConstruct();
        }
        return $result;
    }

    /**
     * Creates the singleton dependencies for a given class and returns with it as an array
     * @param \ReflectionClass $reflectionClass
     * @return array The created singleton instances
     */
    private function createDependencies(\ReflectionClass $reflectionClass) {
        $result = [];
        $constructor = $reflectionClass->getConstructor();
        if (!$constructor) {
            return $result;
        }
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type) {
                continue;
            }
            $interface = $type->getName();
            if ($this->hasInterface($interface)) {
                $result[] = $this->get($interface);
            }
        }
        return $result;
    }

}