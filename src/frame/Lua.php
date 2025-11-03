<?php

namespace AlonePhp\Redis\frame;

use Redis;
use Throwable;

trait Lua {
    // Redis
    protected mixed $redis = null;
    // LUA脚本代码
    protected array $script = [];
    // LUA脚本信息
    protected array $sha = [];
    // LUA脚本信息
    protected static array $lua = [];

    /**
     * 获取原生redis对像
     * @return Redis
     */
    public function client(): Redis {
        return ($this->redis instanceof Redis) ? $this->redis : $this->redis->client();
    }

    /**
     * 设置Lua
     * @param string|int $type 类型
     * @param string     $lua  脚本代码
     * @return $this
     */
    public function setLua(string|int $type, string $lua): static {
        $this->script[$type] = $lua;
        return $this;
    }

    /**
     * 获取Lua
     * @param string $type 类型 或者 文件名
     * @return string
     */
    public function getLua(string $type): string {
        if (!empty($this->script[$type] ?? null)) {
            static::$lua[$type] = $this->script[$type];
        } elseif (empty(static::$lua[$type] ?? null)) {
            static::$lua[$type] = @file_get_contents(__DIR__ . "/../../script/$type.lua");
        }
        return static::$lua[$type];
    }

    /**
     * 执行脚本
     * @param string $type   类型 或者 文件名
     * @param array  $params 参数
     * @param int    $keyCount
     * @return mixed
     */
    public function execLua(string $type, array $params, int $keyCount): mixed {
        $script = $this->getLua($type);
        if (empty($this->sha[$type] ?? null)) {
            $this->sha[$type] = $this->client()->script('load', $script);
        }
        try {
            return $this->client()->evalSha($this->sha[$type], $params, $keyCount);
        } catch (Throwable $e) {
            unset($this->sha[$type]);
            return $this->client()->eval($script, $params, $keyCount);
        }
    }
}