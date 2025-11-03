<?php

namespace AlonePhp\RedisBalance\tips;
/**
 * - 单帐户余额操作
 * - 状态码说明
 * - 200: 成功
 * - 201: 需要初始化
 * - 202: 初始化中等待
 * - 203: 余额不足
 * - 204: 执行超时
 * - 205: 异常错误
 * - 206: 操作失败
 */
class Balance {
    public int        $code    = 0;    // 状态码
    public string     $msg     = "";   // 提示信息
    public float|int  $amount  = 0;    // 操作额度
    public string     $key     = "";   // 操作key
    public string     $field   = "";   // 操作key
    public float|int  $before  = 0;    // 操作前余额
    public float|int  $balance = 0;    // 操作后余额
    public float|int  $execute = 0;    // 执行时间
    public array|null $error   = null; // 报错详细

    public function __construct(array $res) {
        foreach (array_intersect_key($res, get_object_vars($this)) as $k => $v) {
            $this->$k = $v;
        }
    }

    /**
     * 判断操作余额是否成功
     * @return bool
     */
    public function is(): bool {
        return $this->code == 200 && ($this->amount > 0 ? ($this->balance - $this->before) : ($this->before - $this->balance)) == $this->amount;
    }
}