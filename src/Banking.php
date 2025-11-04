<?php

namespace AlonePhp\Redis;

use Redis;
use Throwable;
use AlonePhp\Redis\banking\Balance;
use AlonePhp\Redis\banking\Transfer;

/**
 * 金融余额操作
 */
class Banking {
    // Redis
    protected mixed $redis = null;
    // LUA脚本信息
    protected array $sha = [];
    // LUA脚本信息
    protected static array $lua = [];
    // 配置
    protected array $config = [
        // redis默认值
        "default"  => -1,
        // 每次等待时间(微秒)
        'wait'     => 5000,
        // 超时时间(秒)
        'timeout'  => 5,
        // 精度倍数
        'decimals' => 1000000
    ];

    /**
     * @param mixed $redis array使用自带的redis,也可以使用redis对像
     */
    public function __construct(mixed $redis = []) {
        $this->redis = is_array($redis) ? (new Client($redis)) : $redis;
    }

    /**
     * 单帐户余额操作
     * @param string|int $key
     * @param string|int $field  字段
     * @param float|int  $amount 正数增加，负数扣除，0表示查询
     * @param callable   $call   字段不存在时执行(从mysql中获取),高并发不存在时只能执行一次
     * @return Balance
     */
    public function balance(string|int $key, string|int $field, float|int $amount, callable $call): Balance {
        $startTime = microtime(true);
        $decimals = $this->config('decimals', 1000000);
        $timeout = $this->config('timeout', 5);
        $default = $this->config('default', -1);
        $wait = $this->config('wait', 5000);
        try {
            $balance = $amount * $decimals;
            while (microtime(true) - $startTime < $timeout) {
                $param = [(string) $key, (string) $field, (int) $balance, $default];
                $result = $this->eval('balance', $param, 1);
                [$code, $before, $after] = array_pad($result, 3, 0);
                switch ($code) {
                    case 200:
                    case 201:
                    case 202:
                        return new Balance([
                            'code'    => $code,
                            'amount'  => $amount,
                            'key'     => $key,
                            'field'   => $field,
                            'before'  => $before / $decimals,
                            'after'   => $after / $decimals,
                            'execute' => microtime(true) - $startTime
                        ]);
                    case 1:
                        $this->set($key, $field, $call());
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
                'field'   => $field,
                'execute' => microtime(true) - $startTime
            ]);
        } catch (Throwable $e) {
            return new Balance([
                'code'    => 204,
                'amount'  => $amount,
                'key'     => $key,
                'field'   => $field,
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
     * @param string|int $outKey   转出key
     * @param string|int $outField 转出字段
     * @param string|int $inKey    转入key
     * @param string|int $inField  转入字段
     * @param float|int  $amount   转帐额度 0表示查询转出和转入
     * @param callable   $outCall  转出包.字段不存在时执行(从mysql中获取),高并发不存在时只能执行一次
     * @param callable   $inCall   转入包.字段不存在时执行(从mysql中获取),高并发不存在时只能执行一次
     * @return Transfer
     */
    public function transfer(string|int $outKey, string|int $outField, string|int $inKey, string|int $inField, float|int $amount, callable $outCall, callable $inCall): Transfer {
        $startTime = microtime(true);
        $decimals = $this->config('decimals', 1000000);
        $timeout = $this->config('timeout', 5);
        $default = $this->config('default', -1);
        $wait = $this->config('wait', 5000);
        try {
            $amount = abs($amount);
            $balance = $amount * $decimals;
            while (microtime(true) - $startTime < ($timeout)) {
                $param = [(string) $outKey, (string) $inKey, (string) $outField, (string) $inField, (int) $balance, $default];
                $result = $this->eval('transfer', $param, 2);
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
                            'outField'  => $outField,
                            'outBefore' => $outBefore / $decimals,
                            'outAfter'  => $outAfter / $decimals,
                            'inKey'     => $inKey,
                            'inField'   => $inField,
                            'inBefore'  => $inBefore / $decimals,
                            'inAfter'   => $inAfter / $decimals,
                            'execute'   => (microtime(true) - $startTime),
                        ]);
                    case 1:
                        $this->set($outKey, $outField, $outCall());
                        continue 2;
                    case 2:
                        $this->set($inKey, $inField, $inCall());
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
                'code'     => 203,
                'amount'   => $amount,
                'outKey'   => $outKey,
                'outField' => $outField,
                'inKey'    => $inKey,
                'inField'  => $inField,
                'execute'  => (microtime(true) - $startTime),
            ]);
        } catch (Throwable $e) {
            return new Transfer([
                'code'     => 204,
                'amount'   => $amount,
                'outKey'   => $outKey,
                'outField' => $outField,
                'inKey'    => $inKey,
                'inField'  => $inField,
                'execute'  => (microtime(true) - $startTime),
                'error'    => [
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
     * @param string|int $field
     * @param float|int  $amount 金额（正常小数值）
     * @return bool
     */
    public function set(string|int $key, string|int $field, float|int $amount): bool {
        return (bool) $this->client()->hSet($key, $field, (int) ($amount * $this->config('decimals', 1000000)));
    }

    /**
     * 批量获取余额
     * @param string|int $key
     * @param array      $fields 为空获取 key 的全部字段
     * @return array 二维数组 ['field' => 金额]
     */
    public function get(string|int $key, array $fields = []): array {
        $item = [];
        $items = !empty($fields) ? $this->client()->hMGet($key, $fields) : $this->client()->hGetAll($key);
        foreach ($items as $k => $v) {
            $item[$k] = ((float) $v) / $this->config['decimals'];
        }
        return $item;
    }

    /**
     * 删除指定 key 或者 field
     * @param string|int        $key
     * @param array|string|null $field null删除整个key，string只删除单个field, array批量删除
     * @return int 返回删除数量（字段数或 1 或 0）
     */
    public function del(string|int $key, array|string|null $field): int {
        if (is_array($field)) {
            $i = 0;
            foreach ($field as $val) {
                $i += (int) $this->client()->hDel($key, $val);
            }
            return $i;
        }
        return (int) ($field === null ? $this->client()->del($key) : $this->client()->hDel($key, $field));
    }

    /**
     * 设置默认值
     * @param int $default
     * @return $this
     */
    public function default(int $default = -1): static {
        $this->config["default"] = -abs($default);
        return $this;
    }

    /**
     * 每次等待时间(微秒)
     * @param int $wait
     * @return $this
     */
    public function wait(int $wait = 5000): static {
        $this->config["wait"] = abs($wait);
        return $this;
    }

    /**
     * 超时时间(秒)
     * @param int $timeout
     * @return $this
     */
    public function timeout(int $timeout = 5): static {
        $this->config["timeout"] = abs($timeout);
        return $this;
    }

    /**
     * 设置精度倍数
     * @param int $decimals
     * @return $this
     */
    public function decimals(int $decimals = 1000000): static {
        $this->config["decimals"] = abs($decimals);
        return $this;
    }

    /**
     * 获取原生redis对像
     * @return Redis
     */
    public function client(): Redis {
        return ($this->redis instanceof Redis) ? $this->redis : $this->redis->client();
    }

    /**
     * 执行脚本
     * @param string $type   类型 或者 文件名
     * @param array  $params 参数
     * @param int    $keyCount
     * @return mixed
     */
    protected function eval(string $type, array $params, int $keyCount): mixed {
        static::$lua[$type] = !empty($lua = (static::$lua[$type] ?? null)) ? $lua : @file_get_contents(__DIR__ . "/banking/script/$type.lua");
        $this->sha[$type] = !empty($sha = ($this->sha[$type] ?? null)) ? $sha : $this->client()->script('load', static::$lua[$type]);
        try {
            return $this->client()->evalSha($this->sha[$type], $params, $keyCount);
        } catch (Throwable $e) {
            unset($this->sha[$type]);
            return $this->client()->eval(static::$lua[$type], $params, $keyCount);
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
}