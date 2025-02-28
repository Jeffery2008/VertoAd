<?php
namespace VertoAD\Core\Config;

class Config {
    private static $config = [];
    
    /**
     * 获取配置值
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        if (isset(self::$config[$key])) {
            return self::$config[$key];
        }
        
        // 尝试从配置文件加载
        $configFile = dirname(dirname(dirname(__DIR__))) . '/config/' . explode('.', $key)[0] . '.php';
        if (file_exists($configFile)) {
            self::$config = array_merge(self::$config, require $configFile);
            return self::$config[$key] ?? $default;
        }
        
        return $default;
    }
    
    /**
     * 设置配置值
     * @param string $key 配置键名
     * @param mixed $value 配置值
     */
    public static function set(string $key, $value): void {
        self::$config[$key] = $value;
    }
    
    /**
     * 检查配置是否存在
     * @param string $key 配置键名
     * @return bool
     */
    public static function has(string $key): bool {
        return isset(self::$config[$key]);
    }
    
    /**
     * 加载配置文件
     * @param string $file 配置文件路径
     * @return bool
     */
    public static function load(string $file): bool {
        if (file_exists($file)) {
            $config = require $file;
            if (is_array($config)) {
                self::$config = array_merge(self::$config, $config);
                return true;
            }
        }
        return false;
    }
} 