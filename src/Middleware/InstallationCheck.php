<?php
namespace VertoAD\Core\Middleware;

class InstallationCheck {
    public function handle($request, $next) {
        $installLockFile = dirname(dirname(__DIR__)) . '/install.lock';
        
        // 如果系统未安装且当前不是安装页面，重定向到安装页面
        if (!file_exists($installLockFile) && !in_array($_SERVER['REQUEST_URI'], ['/', '/install'])) {
            header('Location: /');
            exit;
        }
        
        // 如果系统已安装且当前是安装页面，重定向到首页
        if (file_exists($installLockFile) && in_array($_SERVER['REQUEST_URI'], ['/', '/install'])) {
            header('Location: /admin');
            exit;
        }
        
        return $next($request);
    }
} 