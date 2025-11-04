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
     * @param string|int $key
     * @param mixed      $value
     * @param mixed      $ttlOrOptions int时设置有效时间
     * @param bool       $force        是否每次设置有效时间
     * @return bool
     */
    public function set(string|int $key, mixed $value, mixed $ttlOrOptions = [], bool $force = false): bool {
        $data = (string) (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value);
        if (is_numeric($ttlOrOptions) && $ttlOrOptions > 0) {
            $params = [(string) $key, $data, $ttlOrOptions, (int) $force];
            return (bool) $this->eval("set", $params, 1);
        }
        return (bool) $this->client()->set($key, $data, $ttlOrOptions);
    }

    /**
     * 设置 左侧队列
     * 使用rpop获取
     * 数量使用 lLen
     * @param string|int $key
     * @param mixed      $value
     * @param int        $ttl
     * @param bool       $force
     * @return bool
     */
    public function setLPush(string|int $key, mixed $value, int $ttl = 0, bool $force = false): bool {
        $data = (string) (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value);
        if ($ttl > 0) {
            $params = [(string) $key, $data, $ttl, (int) $force];
            return (bool) $this->eval("lPush", $params, 1);
        }
        return (bool) $this->client()->lpush($key, $data);
    }

    /**
     * 设置 右侧队列
     * 使用lpop获取
     * 数量使用 lLen
     * @param string|int $key
     * @param mixed      $value
     * @param int        $ttl
     * @param bool       $force
     * @return bool
     */
    public function setRPush(string|int $key, mixed $value, int $ttl = 0, bool $force = false): bool {
        $data = (string) (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value);
        if ($ttl > 0) {
            $params = [(string) $key, $data, $ttl, (int) $force];
            return (bool) $this->eval("rPush", $params, 1);
        }
        return (bool) $this->client()->rPush($key, $data);
    }

    /**
     * @param string|int       $key
     * @param string|int       $field 字段
     * @param array|string|int $value 内容
     * @param int              $ttl   有效时间
     * @param bool             $force 存在时是否强制设置时间
     * @return bool
     */
    public function setHSet(string|int $key, string|int $field, array|string|int $value, int $ttl = 0, bool $force = false): bool {
        $data = (string) (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value);
        if ($ttl > 0) {
            $params = [(string) $key, (string) $field, $data, $ttl, (int) $force];
            return (bool) $this->eval("hSet", $params, 1);
        }
        return (bool) $this->client()->hSet((string) $key, (string) $field, $data);
    }

    /**
     * 获取使用 sMembers
     * 判断使用 sIsMember
     * @param string|int       $key
     * @param array|string|int $value
     * @param int              $ttl
     * @param bool             $force
     * @return bool
     */
    public function setSAdd(string|int $key, array|string|int $value, int $ttl = 0, bool $force = false): bool {
        $data = (string) (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value);
        if ($ttl > 0) {
            $params = [(string) $key, $data, $ttl, (int) $force];
            return (bool) $this->eval("sAdd", $params, 1);
        }
        return (bool) $this->client()->sAdd((string) $key, $data);
    }

    /**
     * @param string|int       $key
     * @param array|string|int $value
     * @param int              $ttl
     * @param int              $score
     * @param bool             $force
     * @return bool
     */
    public function setZAdd(string|int $key, array|string|int $value, int $score = 0, int $ttl = 0, bool $force = false): bool {
        $data = (string) (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value);
        $score = $score > 0 ? $score : time();
        if ($ttl > 0) {
            $params = [(string) $key, $score, $data, $ttl, (int) $force];
            return (bool) $this->eval("zAdd", $params, 1);
        }
        return (bool) $this->client()->zAdd((string) $key, $score, $data);
    }

    /**
     * @param string|int $key
     * @param int        $score
     * @param bool       $delete 是否删除
     * @return mixed
     */
    public function getZAdd(string|int $key, int $score = 0, bool $delete = true): mixed {
        $score = $score > 0 ? $score : time();
        if ($delete) {
            return $this->eval("zAdd", [(string) $key, '-inf', $score], 1);
        }
        return $this->client()->zrangebyscore((string) $key, '-inf', $score, ['WITHSCORES' => true]);
    }

    /**
     * @param string|int $key
     * @param int        $index 获取第几个,从1起
     * @return array|null
     */
    public function getZAddIndex(string|int $key, int $index): ?array {
        $items = $this->client()->zRange($key, $index - 1, $index - 1, true);
        return (is_array($items) && !empty($item = key($items))) ? ['key' => $item, 'value' => $items[$item]] : null;
    }

    /**
     * 删除全部指定前缀:key
     * @param string|int|null $prefix key前缀, null清空全部redis
     * @return int
     */
    public function delete(string|int|null $prefix): int {
        if (isset($prefix)) {
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
        return $this->client()->flushDB() === true ? 1 : 0;
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
        return call_user_func_array([$this->client(), $name], $parameter);
    }
}