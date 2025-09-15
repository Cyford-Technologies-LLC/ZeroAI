<?php
namespace Core;

class Router {
    private $routes = [];
    
    public function get($path, $handler) {
        $this->routes['GET'][$path] = $handler;
    }
    
    public function post($path, $handler) {
        $this->routes['POST'][$path] = $handler;
    }
    
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        if (isset($this->routes[$method][$path])) {
            $handler = $this->routes[$method][$path];
            $this->callHandler($handler);
        } else {
            http_response_code(404);
            echo "404 Not Found: $path";
        }
    }
    
    private function callHandler($handler) {
        list($controller, $method) = explode('@', $handler);
        $controllerClass = "Controllers\\$controller";
        
        if (class_exists($controllerClass)) {
            $instance = new $controllerClass();
            if (method_exists($instance, $method)) {
                $instance->$method();
            }
        }
    }
}
?>
