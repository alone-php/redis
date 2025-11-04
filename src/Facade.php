<?php

namespace AlonePhp\Redis;

use Redis;
use Throwable;

/**
 * Redis客户端
 * @mixin Redis
 */
class Facade {
    // LUA脚本信息
    protected array $sha = [];
    // Redis
    protected mixed $redis = null;
    // LUA脚本信息
    protected static array $lua = [];

    /**
     * @param mixed $redis array使用自带的redis,也可以使用redis对像
     */
    public function __construct(mixed $redis = []) {
        $this->redis = is_array($redis) ? (new Client($redis)) : $redis;
    }

    /**
     * 金融类
     * @return Banking
     */
    public function banking(): Banking {
        return new Banking($this->redis);
    }

    /**
     * 金融类
     * @return Bank
     */
    public function bank(): Bank {
        return new Bank($this->redis);
    }

    /**
     * 原生redis使用
     * @return Redis
     */
    public function client(): Redis {
        return ($this->redis instanceof Redis) ? $this->redis : $this->redis->client();
    }

    /**
     * 执行脚本
     * @param string $type   类型 或者 文件名
     * @param array  $params 参数
     * @param int    $keyCount
     * @return mixed
     */
    public function eval(string $type, array $params, int $keyCount): mixed {
        static::$lua[$type] = !empty($lua = (static::$lua[$type] ?? null)) ? $lua : @file_get_contents(__DIR__ . "/../script/facade/$type.lua");
        $this->sha[$type] = !empty($sha = ($this->sha[$type] ?? null)) ? $sha : $this->client()->script('load', static::$lua[$type]);
        try {
            return $this->client()->evalSha($this->sha[$type], $params, $keyCount);
        } catch (Throwable $e) {
            unset($this->sha[$type]);
            return $this->client()->eval(static::$lua[$type], $params, $keyCount);
        }
    }

    /**
     * @param string $name
     * @param array  $parameter
     * @return mixed
     */
    public function __call(string $name, array $parameter): mixed {
        switch ($name) {
            case "hSet":
                [$key, $field, $value, $ttl, $force] = array_pad($parameter, 5, 0);
                $params = [
                    (string) $key,
                    (string) $field,
                    (string) (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value),
                    (int) $ttl,
                    (int) $force
                ];
                return (bool) $this->eval($name, $params, 1);
            case "zAdd":
                [$key, $value, $score, , $ttl, $force] = array_pad($parameter, 3, 0);
                $params = [
                    (string) $key,
                    is_numeric($score) ? ($score > 0 ? $score : time()) : time(),
                    (string) (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value),
                    (int) $ttl,
                    (int) $force
                ];
                return (bool) $this->eval($name, $params, 1);
            case "zGet":
                [$key, $score] = array_pad($parameter, 2, 0);
                $params = [
                    (string) $key,
                    "-inf",
                    is_numeric($score) ? ($score > 0 ? $score : time()) : time()
                ];
                return $this->eval($name, $params, 1);
            case "delete":
                $prefix = $parameter[0] ?? null;
                if ($prefix === null) {
                    return $this->client()->flushDB() === true ? 1 : 0;
                }
                $count = 0;
                $items = $this->client()->keys(trim($prefix, ":") . ":*");
                if (!empty($items)) {
                    foreach ($items as $item) {
                        ++$count;
                        $this->client()->del($item);
                    }
                }
                return $count;
        }
        return call_user_func_array([$this->client(), $name], $parameter);
    }
}