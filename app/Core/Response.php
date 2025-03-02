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
} 