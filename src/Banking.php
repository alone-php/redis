<?php

namespace AlonePhp\Redis;

use Throwable;
use AlonePhp\RedisBalance\tips\Balance;
use AlonePhp\RedisBalance\tips\Transfer;

/**
 * 金融余额操作
 */
class Banking extends Lua {
    // 程序配置
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
     * @param mixed $redis  array使用自带的redis,也可以使用redis对像
     * @param array $config 程序配置
     */
    public function __construct(mixed $redis = [], array $config = []) {
        $this->redis = is_array($redis) ? (new Client($redis)) : $redis;
        $this->config = array_merge($this->config, $config);
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
            $digital = $amount * $decimals;
            while (microtime(true) - $startTime < $timeout) {
                $param = [(string) $key, (string) $field, (int) $digital, $default];
                $result = $this->execLua('balance', $param, 1);
                [$code, $msg, $before, $balance] = array_pad($result, 4, 0);
                switch ($code) {
                    case 200:
                    case 203:
                    case 206:
                        return new Balance([
                            'code'    => $code,
                            'msg'     => $msg,
                            'amount'  => $amount,
                            'key'     => $key,
                            'field'   => $field,
                            'before'  => $before / $decimals,
                            'balance' => $balance / $decimals,
                            'execute' => microtime(true) - $startTime
                        ]);
                    case 201:
                        $this->set($key, $field, $call());
                        continue 2;
                    case 202:
                        usleep($wait);
                        continue 2;
                    default:
                        usleep($wait);
                        break;
                }
            }
            return new Balance([
                'code'    => 204,
                'msg'     => 'Execution timeout',
                'amount'  => $amount,
                'key'     => $key,
                'field'   => $field,
                'execute' => microtime(true) - $startTime
            ]);
        } catch (Throwable $e) {
            return new Balance([
                'code'    => 205,
                'msg'     => $e->getMessage(),
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
            $digital = $amount * $decimals;
            while (microtime(true) - $startTime < $timeout) {
                $param = [(string) $outKey, (string) $inKey, (string) $outField, (string) $inField, (int) $digital, $default];
                $result = $this->execLua('transfer', $param, 2);
                [$code, $msg, $outBefore, $inBefore, $outBalance, $inBalance] = array_pad($result, 6, 0);
                switch ($code) {
                    case 200:
                    case 203:
                    case 207:
                    case 208:
                        return new Transfer([
                            'code'       => $code,
                            'msg'        => $msg,
                            'amount'     => $amount,
                            'outKey'     => $outKey,
                            'outField'   => $outField,
                            'outBefore'  => $outBefore / $decimals,
                            'outBalance' => $outBalance / $decimals,
                            'inKey'      => $inKey,
                            'inField'    => $inField,
                            'inBefore'   => $inBefore / $decimals,
                            'inBalance'  => $inBalance / $decimals,
                            'execute'    => (microtime(true) - $startTime),
                        ]);
                    case 201:
                        if ($msg === 'out') {
                            $this->set($outKey, $outField, $outCall());
                        } else {
                            $this->set($inKey, $inField, $inCall());
                        }
                        continue 2;
                    case 202:
                        usleep($wait);
                        continue 2;
                    default:
                        usleep($wait);
                        break;
                }
            }
            return new Transfer([
                'code'     => 204,
                'msg'      => 'Execution timeout',
                'amount'   => $amount,
                'outKey'   => $outKey,
                'outField' => $outField,
                'inKey'    => $inKey,
                'inField'  => $inField,
                'execute'  => (microtime(true) - $startTime),
            ]);
        } catch (Throwable $e) {
            return new Transfer([
                'code'     => 205,
                'msg'      => $e->getMessage(),
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
        if ($field === null) {
            return (int) $this->client()->del($key);
        }
        if (is_array($field)) {
            $i = 0;
            foreach ($field as $val) {
                $i += (int) $this->client()->hDel($key, $val);
            }
            return $i;
        }
        return (int) $this->client()->hDel($key, $field);
    }

    /**
     * 删除全部指定前缀:key
     * @param string|null $keyPrefix key前缀, null清空全部redis
     * @return int
     */
    public function delete(string|null $keyPrefix): int {
        if ($keyPrefix === null) {
            return $this->client()->flushDB() === true ? 1 : 0;
        }
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

    /**
     * 获取配置
     * @param string|int|null $key
     * @param mixed           $default
     * @return mixed
     */
    public function config(string|int|null $key = null, mixed $default = null): mixed {
        return isset($key) ? ($this->config[$key] ?? $default) : $this->config;
    }
}