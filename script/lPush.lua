local key    = KEYS[1]
local value  = ARGV[1]
-- 有效时间（秒）
local ttl    = tonumber(ARGV[2])
-- 是否强制覆盖 TTL（0=仅首次创建时设置，1=强制设置）
local force  = tonumber(ARGV[3])

-- 是否首次写入
local exists = 0
if force == 0 and ttl > 0 and redis.call("EXISTS", key) == 0 then
    exists = 1
end

-- 写入列表头部
local result = redis.call("LPUSH", key, value)

-- 设置过期时间
if exists == 1 or force == 1 then
    redis.call("EXPIRE", key, ttl)
end

return result