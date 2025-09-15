<?php
namespace Core;

class Application {
    private $router;
    private $database;
    
    public function __construct() {
        $this->database = new Database();
        $this->router = new Router();
        $this->setupRoutes();
    }
    
    private function setupRoutes() {
        $this->router->get('/', 'AdminController@login');
        $this->router->get('/admin', 'AdminController@login');
        $this->router->post('/admin', 'AdminController@authenticate');
        $this->router->get('/admin/dashboard', 'AdminController@dashboard');
        $this->router->get('/admin/users', 'UserController@index');
        $this->router->post('/admin/users', 'UserController@create');
        $this->router->post('/admin/users/delete', 'UserController@delete');
        $this->router->get('/admin/agents', 'AgentController@index');
        $this->router->post('/admin/agents', 'AgentController@create');
        $this->router->get('/web', 'WebController@login');
        $this->router->post('/web', 'WebController@authenticate');
        $this->router->get('/web/frontend', 'WebController@frontend');
        $this->router->get('/web/logout', 'WebController@logout');
    }
    
    public function run() {
        $this->router->dispatch();
    }
    
    public function getDatabase() {
        return $this->database;
    }
}
?>
