<?php
namespace Controllers;

class BaseController {
    
    protected function render($view, $data = []) {
        extract($data);
        $viewFile = BASE_PATH . "/src/Views/$view.php";
        
        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            echo "View not found: $view";
        }
    }
    
    protected function redirect($url) {
        header("Location: $url");
        exit;
    }
    
    protected function json($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
?>