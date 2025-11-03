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
     * 设置
     * @param string|int       $key
     * @param string|int       $field 字段
     * @param array|string|int $value 内容
     * @param int              $ttl   有效时间
     * @param bool             $force 存在时是否强制设置时间
     * @return bool
     */
    public function hSet(string|int $key, string|int $field, array|string|int $value, int $ttl = 0, bool $force = false): bool {
        $val = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        return (bool) ($ttl > 0
            ? $this->execLua("hSet", [(string) $key, (string) $field, (string) $val, $ttl, $force ? 1 : 0], 1)
            : $this->client()->hSet((string) $key, (string) $field, (string) $val)
        );
    }

    /**
     * 获取
     * @param string|int            $key
     * @param array|string|int|null $fields
     * @return array|null
     */
    public function hGet(string|int $key, array|string|int|null $fields = null): ?array {
        $field = $fields ? (is_array($fields) ? $fields : [$fields]) : null;
        $items = $field ? $this->client()->hMGet((string) $key, $field) : $this->client()->hGetAll((string) $key);
        foreach ($items as $field => $value) {
            if ($value[0] === '{' || $value[0] === '[') {
                $items[$field] = json_decode($value, true);
            }
        }
        return $items ?: null;
    }
}