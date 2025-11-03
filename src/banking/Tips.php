<?php

namespace AlonePhp\Redis\banking;

trait Tips {
    public int        $code  = 0;      // 状态码
    public array|null $error = null;   // 报错详细

    /**
     * 获取提示信息
     * @param array|null $tips
     * @return string|int|float
     */
    public function getTips(array|null $tips = null): string|int|float {
        (!empty($tips)) && static::$tips = array_replace(static::$tips, $tips);
        return (static::$tips[$this->code] ?? "") . ($this->code == 204 ? "(" . ($this->error["msg"] ?? "") . ")" : "");
    }

    /**
     * 设置提示
     * @param int              $code 状态码
     * @param string|int|float $text 提示内容
     * @return $this
     */
    public function setTips(int $code, string|int|float $text): static {
        static::$tips[$code] = $text;
        return $this;
    }

    /**
     * @param array $res
     */
    public function __construct(array $res) {
        foreach (array_intersect_key($res, get_object_vars($this)) as $k => $v) {
            $this->$k = $v;
        }
        $this->execute = (float) number_format($this->execute, 6, '.', '');
    }
}