<?php

namespace AlonePhp\Redis\tips;

trait Tips {
    public int $code = 0;    // 状态码

    public function __construct(array $res) {
        foreach (array_intersect_key($res, get_object_vars($this)) as $k => $v) {
            $this->$k = $v;
        }
    }

    /**
     * 获取提示信息
     * @return string
     */
    public function getTips(): string {
        return (static::$tips[$this->code] ?? "") . ($this->code == 204 ? "(" . ($this->error["msg"] ?? "") . ")" : "");
    }

    /**
     * 设置提示
     * @param int        $code 状态码
     * @param string|int $text 提示内容
     * @return $this
     */
    public function setTips(int $code, string|int $text): static {
        static::$tips[$code] = $text;
        return $this;
    }
}