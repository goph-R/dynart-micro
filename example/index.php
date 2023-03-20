<?php

// This file should be in your document root folder!

require_once __DIR__ . '/vendor/autoload.php'; 

use Dynart\Micro\App;
use Dynart\Micro\WebApp;
use Dynart\Micro\View;

class MyController {

    private $view;

    public function __construct(View $view) {
        $this->view = $view;
    }
    
    public function index() {
        return $this->view->layout('index');
    }

    public function example($parameter) {
        return $this->view->layout('index', [
            'parameter' => $parameter
        ]); 
    }
}

class MyApp extends WebApp {

    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        $this->add(MyController::class);
    }

    public function init() {
        parent::init();
        $myController = $this->get(MyController::class);
        $this->router->add('/', [$myController, 'index']);
        $this->router->add('/example/?', [$myController, 'example']);
    }    
}

App::run(new MyApp(['config.ini.php']));



