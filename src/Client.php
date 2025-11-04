<?php

namespace AlonePhp\Redis;

use Redis;
use Throwable;

/**
 * Redis客户端
 * @mixin Redis
 */
class Client {
    // 原生redis
    protected Redis|null $redis = null;
    // redis配置
    private array $config = [
        // 可选tcp tls ssl
        'scheme'         => 'tcp',
        //服务器的主机名或 IP 地址
        'host'           => '127.0.0.1',
        //服务器的端口号，默认是 6379
        'port'           => 6379,
        //服务器redis密码
        'password'       => null,
        //选择数据库
        'database'       => 0,
        //连接超时时间，以秒为单位。默认值为 0.0，表示无限制。
        'timeout'        => 2,
        //如果连接失败，重试的间隔时间（以毫秒为单位）。默认值为 0，表示不重试
        'retry_interval' => 0,
        //如果连接失败，重试次数,  0，表示不重试
        'retry_hits'     => 5,
        //读取超时时间，以秒为单位。默认值为 0，表示无限制
        'read_timeout'   => 3,
        //用于持久连接的标识符。如果提供此参数，连接将被视为持久连接 true使用 pconnect
        'persistent'     => false,
        //选项 array
        'options'        => null
    ];

    /**
     * @param array $config
     */
    public function __construct(array $config = []) {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取redis客户端
     * @param bool $retry 是否重连
     * @return ?Redis
     */
    public function client(bool $retry = false): ?Redis {
        if ($retry === true || !$this->isConnected()) {
            $retry_hits = (int) $this->config('retry_hits', 0);
            $retry_hits = $retry_hits > 0 ? $retry_hits : 1;
            for ($i = 0; $i < $retry_hits; $i++) {
                try {
                    $this->close()->connect();
                    if ($this->isConnected()) {
                        break;
                    }
                } catch (Throwable $e) {
                    if ($i === $retry_hits - 1) {
                        break;
                    }
                    usleep(1000);
                }
            }
        }
        return $this->redis;
    }

    /**
     * 连接redis
     * @return Redis|null
     */
    public function connect(): ?Redis {
        $this->close();
        $this->redis = new Redis();
        $host = $this->config('host', '127.0.0.1');
        $port = $this->config('port', 6379);
        $timeout = $this->config('timeout', 5);
        $retry_interval = $this->config('retry_interval', 0);
        $read_timeout = $this->config('read_timeout', 0);
        $scheme = $this->config('scheme', 'tcp');
        $options = $this->config('options', []);
        $isTls = ($scheme === 'tls') || str_starts_with($host, 'tls://') || str_starts_with($host, 'rediss://');
        if ($scheme === 'tls' && !str_starts_with($host, 'tls://') && !str_starts_with($host, 'rediss://')) {
            $host = 'tls://' . $host;
        }
        if ($isTls) {
            $options['ssl'] = array_merge([
                'verify_peer'      => false,
                'verify_peer_name' => false,
                'crypto_method'    => STREAM_CRYPTO_METHOD_TLS_CLIENT,
            ], $options['ssl'] ?? []);
        }
        $persistent = $this->config('persistent');
        if ($persistent) {
            $this->redis->pconnect($host, $port, $timeout, $persistent, $retry_interval, $read_timeout, $options);
        } else {
            $this->redis->connect($host, $port, $timeout, null, $retry_interval, $read_timeout, $options);
        }
        if (!empty($password = $this->config('password'))) {
            $this->redis->auth($password);
        }
        $database = (int) $this->config('database', 0);
        if ($database > 0) {
            $this->redis->select($database);
        }
        return $this->redis;
    }

    /**
     * 关闭连接
     * @return $this
     */
    public function close(): static {
        $this->redis?->close();
        $this->redis = null;
        return $this;
    }

    /**
     * 判断连接是否有效
     * @return bool
     */
    public function isConnected(): bool {
        if ($this->redis) {
            try {
                $result = $this->redis->ping();
                return stripos(strtolower((string) $result), 'pong') !== false;
            } catch (Throwable $e) {
                $this->redis = null;
                return false;
            }
        }
        return false;
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

    /**
     * @param string $name
     * @param array  $parameter
     * @return mixed
     */
    public function __call(string $name, array $parameter): mixed {
        return call_user_func_array([$this->client(), $name], $parameter);
    }
}