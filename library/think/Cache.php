<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

class Cache
{
    protected static $instance = [];
    public static $readTimes   = 0;
    public static $writeTimes  = 0;

    /**
     * 操作句柄
     * @var object
     * @access protected
     */
    protected static $handler = null;

    /**
     * 连接缓存
     * @access public
     * @param array $options  配置数组
     * @return object
     */
    public static function connect(array $options = [])
    {
        $md5 = md5(serialize($options));
        if (!isset(self::$instance[$md5])) {
            $type  = !empty($options['type']) ? $options['type'] : 'File';
            $class = (!empty($options['namespace']) ? $options['namespace'] : '\\think\\cache\\driver\\') . ucwords($type);
            unset($options['type']);
            self::$instance[$md5] = new $class($options);
        }
        self::$handler = self::$instance[$md5];
        return self::$handler;
    }

    public static function __callStatic($method, $params)
    {
        if (is_null(self::$handler)) {
            // 自动初始化缓存
            self::connect(Config::get('cache'));
        }
        return call_user_func_array([self::$handler, $method], $params);
    }
}
