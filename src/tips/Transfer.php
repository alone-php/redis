<?php

namespace AlonePhp\Redis\tips;
/**
 * - 双帐户转帐
 * - 状态码说明
 * - 200: 成功
 * - 201: 需要初始化
 * - 202: 初始化中等待
 * - 203: 余额不足
 * - 204: 执行超时
 * - 205: 异常错误
 */
class Transfer {
    public int        $code       = 0;   // 状态码
    public string     $msg        = "";  // 提示信息
    public float|int  $amount     = 0;   // 操作额度
    public string     $outKey     = "";  // 转出key
    public string     $outField   = "";  // 转出字段
    public float|int  $outBefore  = 0;   // 转出前额度
    public float|int  $outBalance = 0;   // 转出后额度
    public string     $inKey      = "";  // 转入key
    public string     $inField    = "";  // 转入字段
    public float|int  $inBefore   = 0;   // 转入前额度
    public float|int  $inBalance  = 0;   // 转入后额度
    public float|int  $execute    = 0;   // 执行时间
    public array|null $error      = [];  // 报错详细

    public function __construct(array $res) {
        foreach (array_intersect_key($res, get_object_vars($this)) as $k => $v) {
            $this->$k = $v;
        }
    }

    /**
     * 判断是否转帐成功
     * @return bool
     */
    public function is(): bool {
        return $this->code == 200 && $this->isOut() && $this->isIn();
    }

    /**
     * 判断转出是否成功
     * @return bool
     */
    public function isOut(): bool {
        return $this->outBefore - $this->outBalance == $this->amount;
    }

    /**
     * 判断转入是否成功
     * @return bool
     */
    public function isIn(): bool {
        return $this->inBalance - $this->inBefore == $this->amount;
    }
}