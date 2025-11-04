<?php

namespace AlonePhp\Redis;

use Redis;
use Throwable;

/**
 * Redis客户端
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
     * 获取原生redis对像
     * @return Redis
     */
    public function client(): Redis {
        return ($this->redis instanceof Redis) ? $this->redis : $this->redis->client();
    }

    /**
     * 选择数据库
     * @param int $db
     * @return $this
     */
    public function select(int $db = 0): static {
        $this->client()->select($db);
        return $this;
    }

    /**
     * 删除全部指定前缀:key
     * @param string|int|null $keyPrefix key前缀, null清空全部redis
     * @return int
     */
    public function delete(string|int|null $keyPrefix): int {
        if ($keyPrefix === null) {
            return $this->client()->flushDB() === true ? 1 : 0;
        }
        $count = 0;
        $items = $this->client()->keys(trim($keyPrefix, ":") . ":*");
        if (!empty($items)) {
            foreach ($items as $item) {
                ++$count;
                $this->client()->del($item);
            }
        }
        return $count;
    }

    /**
     * 执行脚本
     * @param string $type   类型 或者 文件名
     * @param array  $params 参数
     * @param int    $keyCount
     * @return mixed
     */
    public function eval(string $type, array $params, int $keyCount): mixed {
        static::$lua[$type] = !empty($lua = (static::$lua[$type] ?? null)) ? $lua : @file_get_contents(__DIR__ . "/../script/$type.lua");
        $this->sha[$type] = !empty($sha = ($this->sha[$type] ?? null)) ? $sha : $this->client()->script('load', static::$lua[$type]);
        try {
            return $this->client()->evalSha($this->sha[$type], $params, $keyCount);
        } catch (Throwable $e) {
            unset($this->sha[$type]);
            return $this->client()->eval(static::$lua[$type], $params, $keyCount);
        }
    }
}