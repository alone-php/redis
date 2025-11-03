local key    = KEYS[1]
local field  = ARGV[1]
local value  = ARGV[2]
-- 有效时间/秒
local ttl    = tonumber(ARGV[3])
-- 是否强制设置时间
local force  = tonumber(ARGV[4])

-- 是否首次写入：
local exists = 0
if force == 0 and ttl > 0 then
    exists = (redis.call("EXISTS", key) == 0) and 1 or 0
end

-- 写入字段
local res = redis.call("HSET", key, field, value)

-- 设置过期：
if exists == 1 or force == 1 then
    redis.call("EXPIRE", key, ttl)
end

return res