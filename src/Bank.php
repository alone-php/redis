<?php

namespace AlonePhp\Redis;

use Redis;
use Throwable;
use AlonePhp\Redis\tips\bank\Balance;
use AlonePhp\Redis\tips\bank\Transfer;

/**
 * 金融余额操作
 * @mixin Redis
 */
class Bank {
    // Redis
    protected mixed $redis = null;
    // 配置
    protected array $config = [
        // redis默认值
        "default"  => -1,
        // 每次等待时间(微秒)
        'wait'     => 5000,
        // 超时时间(秒)
        'timeout'  => 5,
        // 精度倍数
        'decimals' => 1000000,
        // 选择数据库
        'database' => 0,
        // key有效时间
        'ttl'      => 86400
    ];

    /**
     * @param mixed $redis array使用自带的redis,也可以使用redis对像
     */
    public function __construct(mixed $redis = []) {
        $this->redis = is_array($redis) ? (new Client($redis)) : $redis;
        $this->client()->select($this->config('database', 0));
    }

    /**
     * 单帐户余额操作
     * @param string|int $key
     * @param float|int  $amount 正数增加，负数扣除，0表示查询
     * @param callable   $call   字段不存在时执行(从mysql中获取),高并发不存在时只能执行一次
     * @return Balance
     */
    public function balance(string|int $key, float|int $amount, callable $call): Balance {
        $startTime = microtime(true);
        $decimals = $this->config('decimals', 1000000);
        $default = $this->config('default', -1);
        $timeout = $this->config('timeout', 5);
        $wait = $this->config('wait', 5000);
        $ttl = $this->config('ttl', 0);
        try {
            $balance = $amount * $decimals;
            $lua = static::balanceLua();
            $sha = $this->client()->script('load', $lua);
            while (microtime(true) - $startTime < $timeout) {
                $param = [(string) $key, (int) $balance, $default, $ttl];
                $result = $this->loadLua($lua, $sha, $param, 1);
                [$code, $before, $after] = array_pad($result, 3, 0);
                switch ($code) {
                    case 200:
                    case 201:
                    case 202:
                        return new Balance([
                            'code'    => $code,
                            'amount'  => $amount,
                            'key'     => $key,
                            'before'  => $before / $decimals,
                            'after'   => $after / $decimals,
                            'execute' => microtime(true) - $startTime
                        ]);
                    case 1:
                        $this->setAmount($key, $call());
                        continue 2;
                    case 4:
                        usleep($wait);
                        continue 2;
                    default:
                        usleep($wait);
                        break;
                }
            }
            return new Balance([
                'code'    => 203,
                'amount'  => $amount,
                'key'     => $key,
                'execute' => microtime(true) - $startTime
            ]);
        } catch (Throwable $e) {
            return new Balance([
                'code'    => 204,
                'amount'  => $amount,
                'key'     => $key,
                'execute' => microtime(true) - $startTime,
                'error'   => [
                    'code' => $e->getCode(),
                    'msg'  => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ]);
        }
    }

    /**
     * 转帐操作 - 双帐户
     * @param string|int $outKey  转出key
     * @param string|int $inKey   转入key
     * @param float|int  $amount  转帐额度 0表示查询转出和转入
     * @param callable   $outCall 转出包.字段不存在时执行(从mysql中获取),高并发不存在时只能执行一次
     * @param callable   $inCall  转入包.字段不存在时执行(从mysql中获取),高并发不存在时只能执行一次
     * @return Transfer
     */
    public function transfer(string|int $outKey, string|int $inKey, float|int $amount, callable $outCall, callable $inCall): Transfer {
        $startTime = microtime(true);
        $ttl = $this->config('ttl', 0);
        $wait = $this->config('wait', 5000);
        $timeout = $this->config('timeout', 5);
        $default = $this->config('default', -1);
        $decimals = $this->config('decimals', 1000000);
        try {
            $amount = abs($amount);
            $balance = $amount * $decimals;
            $lua = static::transferLua();
            $sha = $this->client()->script('load', $lua);
            while (microtime(true) - $startTime < ($timeout)) {
                $param = [(string) $outKey, (string) $inKey, (int) $balance, $default, $ttl];
                $result = $this->loadLua($lua, $sha, $param, 2);
                [$code, $outBefore, $inBefore, $outAfter, $inAfter] = array_pad($result, 5, 0);
                switch ($code) {
                    case 200:
                    case 201:
                    case 202:
                    case 205:
                    case 206:
                        return new Transfer([
                            'code'      => $code,
                            'amount'    => $amount,
                            'outKey'    => $outKey,
                            'outBefore' => $outBefore / $decimals,
                            'outAfter'  => $outAfter / $decimals,
                            'inKey'     => $inKey,
                            'inBefore'  => $inBefore / $decimals,
                            'inAfter'   => $inAfter / $decimals,
                            'execute'   => (microtime(true) - $startTime),
                        ]);
                    case 1:
                        $this->setAmount($outKey, $outCall());
                        continue 2;
                    case 2:
                        $this->setAmount($inKey, $inCall());
                        continue 2;
                    case 4:
                        usleep($wait);
                        continue 2;
                    default:
                        usleep($wait);
                        break;
                }
            }
            return new Transfer([
                'code'    => 203,
                'amount'  => $amount,
                'outKey'  => $outKey,
                'inKey'   => $inKey,
                'execute' => (microtime(true) - $startTime),
            ]);
        } catch (Throwable $e) {
            return new Transfer([
                'code'    => 204,
                'amount'  => $amount,
                'outKey'  => $outKey,
                'inKey'   => $inKey,
                'execute' => (microtime(true) - $startTime),
                'error'   => [
                    'code' => $e->getCode(),
                    'msg'  => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ]);
        }
    }

    /**
     * 强制设置（慎用）
     * @param string|int $key
     * @param float|int  $amount 金额（正常小数值）
     * @return bool
     */
    public function setAmount(string|int $key, float|int $amount): bool {
        return (bool) $this->client()->set($key, (int) ($amount * $this->config('decimals', 1000000)));
    }

    /**
     * 批量获取余额
     * @param array|string|int $keys 单个key 或 多个 key
     * @return array ['key' => 金额]
     */
    public function getAmount(array|string|int $keys): array {
        $result = [];
        $keys = is_array($keys) ? $keys : [$keys];
        $values = $this->client()->mGet($keys);
        foreach ($keys as $i => $key) {
            $result[$key] = ((float) ($values[$i])) / $this->config['decimals'];
        }
        return $result;
    }

    /**
     * 删除指定 key（支持单 key 或多个 key）
     * @param string|int|array $key
     * @return int 返回删除数量
     */
    public function delAmount(string|int|array $key): int {
        $keys = is_array($key) ? $key : [$key];
        return (int) $this->client()->del(...$keys);
    }

    /**
     * 获取原生redis对像
     * @return Redis
     */
    public function client(): Redis {
        return ($this->redis instanceof Redis) ? $this->redis : $this->redis->client();
    }

    /**
     * 设置有效时间
     * @param int $ttl
     * @return $this
     */
    public function setTtl(int $ttl = 86400): static {
        $this->config["ttl"] = abs($ttl);
        return $this;
    }

    /**
     * 设置默认值
     * @param int $default
     * @return $this
     */
    public function setDefault(int $default = -1): static {
        $this->config["default"] = -abs($default);
        return $this;
    }

    /**
     * 每次等待时间(微秒)
     * @param int $wait
     * @return $this
     */
    public function setWait(int $wait = 5000): static {
        $this->config["wait"] = abs($wait);
        return $this;
    }

    /**
     * 超时时间(秒)
     * @param int $timeout
     * @return $this
     */
    public function setTimeout(int $timeout = 5): static {
        $this->config["timeout"] = abs($timeout);
        return $this;
    }

    /**
     * 设置精度倍数
     * @param int $decimals
     * @return $this
     */
    public function setDecimals(int $decimals = 1000000): static {
        $this->config["decimals"] = abs($decimals);
        return $this;
    }

    /**
     * 执行脚本
     * @param mixed $lua
     * @param mixed $sha
     * @param array $params 参数
     * @param int   $keyCount
     * @return mixed
     */
    protected function loadLua(mixed $lua, mixed $sha, array $params, int $keyCount): mixed {
        try {
            return $this->client()->evalSha($sha, $params, $keyCount);
        } catch (Throwable $e) {
            return $this->client()->eval($lua, $params, $keyCount);
        }
    }

    /**
     * 获取配置
     * @param string|int|null $key
     * @param mixed           $default
     * @return mixed
     */
    protected function config(string|int|null $key = null, mixed $default = null): mixed {
        return isset($key) ? ($this->config[$key] ?? $default) : $this->config;
    }

    public static function balanceLua(): string {
        return <<<LUA
local key = KEYS[1]
local amount = tonumber(ARGV[1])
local initValue = ARGV[2]
local ttl = tonumber(ARGV[3])
if redis.call("SETNX", key, initValue) == 1 then
    return { 1 }
end
local result = redis.call("GET", key)
if result == initValue then
    return { 4 }
end
if result == false then
    return { 1 }
end
local before = tonumber(result)
if amount == 0 then
    return { 200, before, before }
end
if amount < 0 and before + amount < 0 then
    return { 202, before, before }
end
local balance = redis.call("INCRBY", key, amount)
if balance == false then
    return { 201 }
end
if ttl > 0 then
    redis.call("EXPIRE", key, ttl)
end
return { 200, before, tonumber(balance) }
LUA;
    }

    public static function transferLua(): string {
        return <<<LUA
local outKey = KEYS[1]
local inKey = KEYS[2]
local amount = tonumber(ARGV[1])
local initValue = ARGV[2]
local ttl = tonumber(ARGV[3])
if redis.call("SETNX", outKey, initValue) == 1 then
    return { 1 }
end
local outResult = redis.call("GET", outKey)
if outResult == initValue then
    return { 4 }
end
if outResult == false then
    return { 1 }
end
if redis.call("SETNX", inKey, initValue) == 1 then
    return { 2 }
end
local inResult = redis.call("GET", inKey)
if inResult == initValue then
    return { 4 }
end
if inResult == false then
    return { 2 }
end
local outBefore = tonumber(outResult)
local inBefore = tonumber(inResult)
if amount == 0 then
    return { 200, outBefore, inBefore, outBefore, inBefore }
end
if outBefore - amount < 0 then
    return { 202, outBefore, inBefore, outBefore, inBefore }
end
local outBalance = redis.call("INCRBY", outKey, -amount)
if outBalance == false then
    return { 201, outBefore, inBefore, outBefore, inBefore }
end
if ttl > 0 then
    redis.call("EXPIRE", outKey, ttl)
end
local inBalance = redis.call("INCRBY", inKey, amount)
if inBalance == false then
    local outRoll = redis.call("INCRBY", outKey, amount)
    if outRoll == false then
        return { 205, outBefore, inBefore, tonumber(outBalance), inBefore }
    end
    return { 206, outBefore, inBefore, tonumber(outRoll), inBefore }
end
if ttl > 0 then
    redis.call("EXPIRE", inKey, ttl)
end
return { 200, outBefore, inBefore, tonumber(outBalance), tonumber(inBalance) }
LUA;
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