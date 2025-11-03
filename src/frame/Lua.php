<?php

namespace AlonePhp\Redis;

use Redis;
use Throwable;

class Lua {
    // Redis
    protected mixed $redis = null;
    // LUA文件脚本
    protected static array $lua = [];
    // LUA脚本信息
    protected array $sha = [];

    /**
     * 获取原生redis对像
     * @return Redis
     */
    public function client(): Redis {
        return ($this->redis instanceof Redis) ? $this->redis : $this->redis->client();
    }

    /**
     * 获取Lua
     * @param string      $fileName 文件名
     * @param string|null $lua      默认lua
     * @return string
     */
    protected function getLua(string $fileName, string|null $lua): string {
        if (empty($lua)) {
            if (empty(static::$lua[$fileName])) {
                static::$lua[$fileName] = @file_get_contents(__DIR__ . "/../../files/$fileName.lua");
            }
        } else {
            static::$lua[$fileName] = $lua;
        }
        return static::$lua[$fileName];
    }

    /**
     * 执行脚本
     * @param string      $fileName 文件名
     * @param string|null $lua      默认lua
     * @param array       $params   参数
     * @param int         $keyCount
     * @return mixed
     * @throws Throwable
     */
    protected function execLua(string $fileName, string|null $lua, array $params, int $keyCount): mixed {
        $script = $this->getLua($fileName, $lua);
        $this->sha[$fileName] = !empty($sha = $this->sha[$fileName] ?? null) ? $sha : $this->client()->script('load', $script);
        try {
            return $this->client()->evalSha($this->sha[$fileName], $params, $keyCount);
        } catch (Throwable $e) {
            unset($this->sha[$fileName]);
            return $this->client()->eval($script, $params, $keyCount);
        }
    }
}