<?php

namespace AlonePhp\Redis;

use AlonePhp\Redis\frame\Lua;

/**
 * Redis客户端
 */
class Facade {
    use Lua;

    /**
     * @param mixed $redis array使用自带的redis,也可以使用redis对像
     */
    public function __construct(mixed $redis = []) {
        $this->redis = is_array($redis) ? (new Client($redis)) : $redis;
    }

    /**
     * 金融类
     * @param array $config 设置
     * @return Banking
     */
    public function banking(array $config = []): Banking {
        return new Banking($this->redis, $config);
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
     * @param string|int $key
     * @param string|int $field 字段
     * @param string|int $value 内容
     * @param int        $ttl   有效时间
     * @param bool       $force 存在时是否强制设置时间
     * @return bool
     */
    public function hSet(string|int $key, string|int $field, string|int $value, int $ttl = 0, bool $force = false): bool {
        return (bool) ($ttl > 0
            ? $this->execLua("hSet", [(string) $key, (string) $field, (string) $value, $ttl, $force ? 1 : 0], 1)
            : $this->client()->hSet((string) $key, (string) $field, (string) $value)
        );
    }
}