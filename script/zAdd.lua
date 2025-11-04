local key    = KEYS[1]
local score  = tonumber(ARGV[1])
local value  = tonumber(ARGV[2])
-- 有效时间/秒
local ttl    = tonumber(ARGV[3])
-- 是否强制设置时间
local force  = tonumber(ARGV[4])

-- 是否首次写入
local exists = 0
if force == 0 and ttl > 0 and redis.call("EXISTS", key) == 0 then
    exists = 1
end

-- 写入
local result = redis.call("ZADD", key, score, value)

-- 设置过期
if exists == 1 or force == 1 then
    redis.call("EXPIRE", key, ttl)
end

return result