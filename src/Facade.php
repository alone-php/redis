<?php

namespace AlonePhp\Redis;

use AlonePhp\Redis\frame\Lua;

/**
 * Redis客户端
 */
class Facade {
    use Lua;

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

    /**
     * @param string|int  $type     类型或者文件名
     * @param array       $params   参数
     * @param int         $keyCount key数量
     * @param string|null $lua      自定脚本内容
     * @return mixed
     */
    public function eval(string|int $type, array $params, int $keyCount, string|null $lua = null): mixed {
        return $this->setLua($type, $lua)->execLua($type, $params, $keyCount);
    }

    /**
     * 删除全部指定前缀:key
     * @param string|int|null $keyPrefix key前缀, null清空全部redis
     * @return int
     */
    public function delete(string|int|null $keyPrefix): int {
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
}