<?php

namespace AlonePhp\Redis\banking;
/**
 * 双帐户转帐
 */
class Transfer {
    use Tips;

    public float|int $amount    = 0;      // 操作额度
    public string    $outKey    = "";     // 转出key
    public string    $outField  = "";     // 转出字段
    public float|int $outBefore = 0;      // 转出前额度
    public float|int $outAfter  = 0;      // 转出后额度
    public string    $inKey     = "";     // 转入key
    public string    $inField   = "";     // 转入字段
    public float|int $inBefore  = 0;      // 转入前额度
    public float|int $inAfter   = 0;      // 转入后额度
    public float|int $execute   = 0;      // 执行时间

    public static array $tips = [
        200 => "成功",
        201 => "转出失败",
        202 => "转出余额不足",
        203 => "执行超时",
        204 => "异常错误",
        205 => "转入失败 并 回滚失败",
        206 => "转入失败 并 回滚成功"
    ];

    /**
     * 判断是否转帐成功
     * @return bool
     */
    public function isSuccess(): bool {
        return $this->code == 200 && $this->isOut() && $this->isIn();
    }

    /**
     * 判断转出是否成功
     * @return bool
     */
    public function isOut(): bool {
        return $this->outBefore - $this->amount == $this->outAfter;
    }

    /**
     * 判断转入是否成功
     * @return bool
     */
    public function isIn(): bool {
        return $this->inBefore + $this->amount == $this->inAfter;
    }
}