<?php


namespace AlonePhp\Redis;

use Redis;

/**
 * Redis客户端
 */
class Facade {
    // Redis
    protected mixed $redis = null;

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
    public function getRedis(): Redis {
        if ($this->redis instanceof Redis) {
            return $this->redis;
        }
        return $this->redis->client();
    }

    /**
     * 选择数据库
     * @param int $db
     * @return $this
     */
    public function select(int $db = 0): static {
        $this->getRedis()->select($db);
        return $this;
    }

    /**
     * 设置 缓存
     * @param int|string $key
     * @param int|string $name
     * @param mixed      $val
     * @param int        $time
     * @return mixed
     */
    public function hSet(int|string $key, int|string $name, mixed $val, int $time = 0): mixed {
        $res = $this->getRedis()->hSet((string) $key, (string) $name, $val);
        return $res;
    }
}