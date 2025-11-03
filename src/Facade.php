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
     * 入队列
     * @param string|int       $key
     * @param array|string|int $value
     * @param int              $ttl
     * @param bool             $force
     * @return bool
     */
    public function lPush(string|int $key, array|string|int $value, int $ttl = 0, bool $force = false): bool {
        $val = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        return (bool) ($ttl > 0
            ? $this->execLua("lPush", [(string) $key, (string) $val, $ttl, $force ? 1 : 0], 1)
            : $this->client()->lPush((string) $key, (string) $val)
        );
    }

    /**
     * 入队列
     * @param string|int       $key
     * @param array|string|int $value
     * @param int              $ttl
     * @param bool             $force
     * @return bool
     */
    public function rPush(string|int $key, array|string|int $value, int $ttl = 0, bool $force = false): bool {
        $val = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        return (bool) ($ttl > 0
            ? $this->execLua("rPush", [(string) $key, (string) $val, $ttl, $force ? 1 : 0], 1)
            : $this->client()->rPush((string) $key, (string) $val)
        );
    }

    /**
     * @param string|int       $key
     * @param array|string|int $value
     * @param int              $ttl
     * @param bool             $force
     * @return bool
     */
    public function sAdd(string|int $key, array|string|int $value, int $ttl = 0, bool $force = false): bool {
        $val = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        return (bool) ($ttl > 0
            ? $this->execLua("sAdd", [(string) $key, (string) $val, $ttl, $force ? 1 : 0], 1)
            : $this->client()->sAdd((string) $key, (string) $val)
        );
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

    /**
     * 删除指定 key 或者 field
     * @param string|int        $key
     * @param array|string|null $field null删除整个key，string只删除单个field, array批量删除
     * @return int 返回删除数量（字段数或 1 或 0）
     */
    public function hDel(string|int $key, array|string|null $field): int {
        if (is_array($field)) {
            $i = 0;
            foreach ($field as $val) {
                $i += (int) $this->client()->hDel($key, $val);
            }
            return $i;
        }
        if ($field === null) {
            return (int) $this->client()->del($key);
        }
        return (int) $this->client()->hDel($key, $field);
    }

    /**
     * 删除全部指定前缀:key
     * @param string|int|null $keyPrefix key前缀, null清空全部redis
     * @return int
     */
    public function delete(string|int|null $keyPrefix): int {
        if (isset($keyPrefix)) {
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
        return $this->client()->flushDB() === true ? 1 : 0;
    }
}