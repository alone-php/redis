local outKey = KEYS[1]
local inKey = KEYS[2]
local amount = tonumber(ARGV[1])
local initValue = ARGV[2]
local ttl = tonumber(ARGV[3])

-- 转出账户初始化
if redis.call("SETNX", outKey, initValue) == 1 then
    return { 1 }
end

local outResult = redis.call("GET", outKey)
if outResult == initValue then
    return { 4 }
end
if outResult == false then
    return { 1 }
end

-- 转入账户初始化
if redis.call("SETNX", inKey, initValue) == 1 then
    return { 2 }
end

local inResult = redis.call("GET", inKey)
if inResult == initValue then
    return { 4 }
end
if inResult == false then
    return { 2 }
end

local outBefore = tonumber(outResult)
local inBefore = tonumber(inResult)

-- 查询余额
if amount == 0 then
    return { 200, outBefore, inBefore, outBefore, inBefore }
end

-- 转出余额不足
if outBefore - amount < 0 then
    return { 202, outBefore, inBefore, outBefore, inBefore }
end

-- 扣款
local outBalance = redis.call("INCRBY", outKey, -amount)
if outBalance == false then
    return { 201, outBefore, inBefore, outBefore, inBefore }
end
-- 设置有效时间
if ttl > 0 then
    redis.call("EXPIRE", outKey, ttl)
end

-- 加款
local inBalance = redis.call("INCRBY", inKey, amount)
if inBalance == false then
    -- 回滚失败前余额已发生变化，应恢复
    local outRoll = redis.call("INCRBY", outKey, amount)
    if outRoll == false then
        return { 205, outBefore, inBefore, tonumber(outBalance), inBefore }
    end
    return { 206, outBefore, inBefore, tonumber(outRoll), inBefore }
end

-- 设置有效时间
if ttl > 0 then
    redis.call("EXPIRE", inKey, ttl)
end

-- 成功
return { 200, outBefore, inBefore, tonumber(outBalance), tonumber(inBalance) }