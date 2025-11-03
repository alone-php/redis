<?php

namespace AlonePhp\Redis\banking;
/**
 * 单帐户余额操作
 */
class Balance {
    use Tips;

    public float|int $amount  = 0;  // 操作额度
    public string    $key     = ""; // 操作key
    public string    $field   = ""; // 操作字段
    public float|int $before  = 0;  // 操作前余额
    public float|int $after   = 0;  // 操作后余额
    public float|int $execute = 0;  // 执行时间

    public static array $tips = [
        200 => "成功",
        201 => "操作失败",
        202 => "余额不足",
        203 => "执行超时",
        204 => "异常错误"
    ];

    /**
     * 判断操作余额是否成功
     * @return bool
     */
    public function isSuccess(): bool {
        return $this->code == 200 && ($this->before + $this->amount == $this->after);
    }
}