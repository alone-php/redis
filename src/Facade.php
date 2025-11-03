<?php


namespace AlonePhp\Redis;

use Redis;

/**
 * Redis客户端
 */
class Facade extends Lua {
    /**
     * @param mixed $redis array使用自带的redis,也可以使用redis对像
     */
    public function __construct(mixed $redis = []) {
        $this->redis = is_array($redis) ? (new Client($redis)) : $redis;
    }

    /**
     * 金融类
     * @param array $config 设置
     * @return Banking
     */
    public function banking(array $config = []): Banking {
        return new Banking($this->redis, $config);
    }

    /**
     * 选择数据库
     * @param int $db
     * @return $this
     */
    public function select(int $db = 0): static {
        $this->client()->select($db);
        return $this;
    }
}