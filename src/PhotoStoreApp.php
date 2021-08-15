<?php

require_once 'src/App.php';
require_once 'src/View.php';
require_once 'src/Database.php';
require_once 'src/User.php';

use Dynart\Micro\App;

class ShareApp extends App {

    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        $this->route('/', [$this, 'index']);
        $this->route('/login', [$this, 'login']);
        $this->route('/logout', [$this, 'logout']);
        $this->route('/sign-up', [$this, 'signUp']);
    }

    public function index(ShareApp $app) {
        return $app->view()->layout('index');
    }

    public function login(ShareApp $app) {
        $error = '';
        if ($app->requestPost()) {
            $id = $app->user()->findIdByUsernameAndPassword(
                $app->request('username'),
                $app->request('password')
            );
            if ($id) {
                $app->user()->login($id);
                $app->redirect('/');
            }
            $error = 'The username or the password is wrong.';
        }
        return $app->view()->layout('login', [
            'error' => $error
        ]);
    }

    public function logout(ShareApp $app) {
        $app->user()->logout();
        $app->redirect('/');
    }

}