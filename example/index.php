<?php

// This file should be in your document root folder!

require_once __DIR__ . '/vendor/autoload.php';

use Dynart\Micro\App;
use Dynart\Micro\WebApp;
use Dynart\Micro\View;

class MyController {

    private $view;

    public function __construct(View $view) { // the View parameter will be automatically injected
        $this->view = $view;
    }
    
    public function index() {
        // render index.phtml
        return $this->view->fetch('index');
    }

    public function example($parameter) {
        // the path variable value will be in '$parameter'
        return $this->view->fetch('index', [
            'parameter' => $parameter // give the parameter for the view
        ]); 
    }
}

class MyApp extends WebApp { // inherit from WebApp for an MVC web application

    public function __construct(array $configPaths) {
        parent::__construct($configPaths);

        // register the MyController class for dependency injection
        $this->add(MyController::class);
    }

    public function init() {
        parent::init();

        // add endpoint for home
        $this->router->add('/', [MyController::class, 'index']);

        // add endpoint with path variable
        $this->router->add('/example/?', [MyController::class, 'example']);
    }    
}

App::run(new MyApp(['config.ini.php']));



