<?php

namespace AlonePhp\Redis;

use Throwable;

trait Lua {
    // LUA文件脚本
    protected static array $luaFile = [];
    // 当前脚本信息
    protected array $lua = [];
    // LUA脚本信息
    protected array $script = [];

    /**
     * 获取脚本代码
     * @param string $type 类型 balance transfer
     * @return string
     */
    protected function getLua(string $type): string {
        if (empty($this->lua[$type] ?? null)) {
            $this->lua[$type] = $this->config($type . "Lua");
            if (empty($this->lua[$type])) {
                if (empty(static::$luaFile[$type])) {
                    static::$luaFile[$type] = @file_get_contents(__DIR__ . "/../../file/$type.lua");
                }
                $this->lua[$type] = static::$luaFile[$type];
            }
        }
        return $this->lua[$type];
    }

    /**
     * 获取脚本加载
     * @param string $type 类型 balance transfer
     * @return mixed
     */
    protected function loadLua(string $type): mixed {
        $lua = $this->getLua($type);
        if (empty($this->script[$type] ?? null)) {
            $this->script[$type] = $this->getRedis()->script('load', $lua);
        }
        return $this->script[$type];
    }

    /**
     * 执行脚本
     * @param string $type   类型 balance transfer
     * @param array  $params 参数
     * @param int    $keyCount
     * @return mixed
     * @throws Throwable
     */
    protected function execLua(string $type, array $params, int $keyCount): mixed {
        try {
            return $this->getRedis()->evalSha($this->loadLua($type), $params, $keyCount);
        } catch (Throwable $e) {
            unset($this->script[$type]);
            return $this->getRedis()->eval($this->lua[$type], $params, $keyCount);
        }
    }
}