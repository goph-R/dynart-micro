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
    const CONFIG_ENVIRONMENT = 'app.environment';
    const DEFAULT_ENVIRONMENT = 'prod';

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
     * Calls the `fullInit()` and `fullProcess()` methods of the $app
     *
     * @throws AppException if the instance was set before
     * @param App $app The application for init and process
     */
    public static function run(App $app): void {
        if (self::$instance) {
            throw new AppException("App was instantiated before!");
        }
        self::$instance = $app;
        $app->fullInit();
        $app->fullProcess();
    }

    /**
     * Stores the classes in [interface => class] format, the class can be null
     * @var array
     */
    protected $classes = [];

    /**
     * Stores the middleware class names in a list
     * @var Middleware[]
     */
    protected $middlewares = [];

    /**
     * Stores the instances in [interface => instance] format
     * @var array
     */
    protected $instances = [];

    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var string[] */
    protected $configPaths;

    public function __construct(array $configPaths) {
        $this->configPaths = $configPaths;
        $this->add(Config::class);
        $this->add(Logger::class);
    }

    /**
     * Creates the `Config`, loads the configs, creates the `Logger`, calls the `init()` method
     * then runs all of the middlewares. If an exception happens, handles it with the `handleException()` method.
     */
    public function fullInit() {
        try {
            $this->config = $this->get(Config::class);
            $this->loadConfigs();
            $this->logger = $this->get(Logger::class);
            $this->init();
            $this->runMiddlewares();
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Calls the `process()` method within a try/catch, handles exception with the `handleException()` method
     */
    public function fullProcess() {
        try {
            $this->process();
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

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
     * Adds a middleware
     * @param string $interface
     */
    public function addMiddleware(string $interface) {
        $this->add($interface);
        $this->middlewares[] = $interface;
    }

    /**
     * Runs all of the added middlewares
     */
    protected function runMiddlewares() {
        foreach ($this->middlewares as $m) {
            $this->get($m)->run();
        }
    }

    /**
     * Finishes the application
     * @param string|int $content Content for the output
     */
    public function finish($content = 0) {
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
        if ($class != null && !(is_subclass_of($class, $interface))) {
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
     * @param array $dependencyStack
     * @return mixed
     */
    public function get(string $interface, array $parameters = [], array $dependencyStack = []) {
        if (array_key_exists($interface, $this->instances)) {
            return $this->instances[$interface];
        }
        $result = $this->create($this->getClass($interface), $parameters, $dependencyStack);
        $this->instances[$interface] = $result;
        return $result;
    }

    /**
     * Returns with all of the interfaces in an array
     * @return array All of the added interfaces
     */
    public function interfaces() {
        return array_keys($this->classes);
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
     * @param array $dependencyStack
     * @return mixed
     */
    public function create(string $class, array $parameters = [], array $dependencyStack = []) {
        if (in_array($class, $dependencyStack)) {
            throw new AppException("Circular dependency: ".join(" <- ", $dependencyStack));
        }
        $dependencyStack[] = $class;
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new AppException("Couldn't create reflection class for $class");
        }
        $dependencies = $this->createDependencies($class, $reflectionClass, $dependencyStack);
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
    protected function createDependencies(string $class, \ReflectionClass $reflectionClass, array $dependencyStack = []) {
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
            if ($this->hasInterface($interface)) {
                $result[] = $this->get($interface, [], $dependencyStack);
            } else {
                throw new AppException("Non existing dependency `$interface` for `$class`");
            }
        }
        return $result;
    }

    /**
     * Loads all of the configs by the `$configPaths`
     */
    protected function loadConfigs() {
        foreach ($this->configPaths as $path) {
            $this->config->load($path);
        }
    }

    /**
     * Handles the exception
     *
     * Writes out the type, the line, the exception message and the stacktrace.
     * If it's a CLI call finishes the application.
     *
     * @param \Exception $e The exception
     */
    protected function handleException(\Exception $e) {
        $type = get_class($e);
        $file = $e->getFile();
        $line = $e->getLine();
        $message = $e->getMessage();
        $trace = $e->getTraceAsString();
        $text = "`$type` in $file on line $line with message: $message\n$trace";
        if (!$this->config) {
            throw new AppException("Couldn't instantiate Config::class");
        }
        if (!$this->logger) {
            throw new AppException("Couldn't instantiate Logger::class");
        }
        $this->logger->error($text);
    }
}