<?php
namespace App\Core;

class Response {
    public function renderView($view, $data = []) {
        extract($data);
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';
        
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "Error: View not found: " . $view;
        }
    }
    
    // Add compatibility method for view() that delegates to renderView()
    public function view($view, $data = []) {
        return $this->renderView($view, $data);
    }
    
    public function redirect($url) {
        header("Location: {$url}");
        exit;
    }
    
    public function json($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
} 