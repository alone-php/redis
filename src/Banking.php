<?php

namespace AlonePhp\Redis;

use Redis;
use Throwable;
use AlonePhp\RedisBalance\tips\Balance;
use AlonePhp\RedisBalance\tips\Transfer;

/**
 * 金融余额操作
 */
class Banking {
    use Lua;

    // Redis
    protected mixed $redis = null;
    // 程序配置
    protected array $config = [
        // 锁 的key前缀
        'prefix'      => 'alone_php:redis_lua_lock',
        // redis默认值
        "default"     => -1,
        // 每次等待时间(微秒)
        'wait'        => 5000,
        // 超时时间(秒)
        'timeout'     => 5,
        // 精度倍数
        'decimals'    => 1000000,
        // 错误报告设置, 为空不设置
        'error'       => E_ALL & ~E_WARNING,
        // call方法脚本内容
        'balanceLua'  => null,
        // tran方法脚本内容
        'transferLua' => null
    ];
}