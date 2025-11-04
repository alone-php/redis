local key = KEYS[1]
local amount = tonumber(ARGV[1])
local initValue = ARGV[2]
local ttl = tonumber(ARGV[3])

-- 尝试首次写入
if redis.call("SETNX", key, initValue) == 1 then
    return { 1 }
end

-- 已存在
local result = redis.call("GET", key)
if result == initValue then
    return { 4 }
end

if result == false then
    return { 1 }
end

local before = tonumber(result)
if amount == 0 then
    return { 200, before, before }
end

if amount < 0 and before + amount < 0 then
    return { 202, before, before }
end

local balance = redis.call("INCRBY", key, amount)
if balance == false then
    return { 201 }
end

-- 设置有效时间
if ttl > 0 then
    redis.call("EXPIRE", key, ttl)
end

return { 200, before, tonumber(balance) }