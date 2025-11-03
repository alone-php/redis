local outKey = KEYS[1]
local inKey = KEYS[2]
local outField = ARGV[1]
local inField = ARGV[2]
local amount = tonumber(ARGV[3])
local initValue = ARGV[4]
-- 检查转出账户是否存在,不存在时初始化
if redis.call("HSETNX", outKey, outField, initValue) == 1 then
    return { 1 }
end
-- 获取转出账户
local outResult = redis.call("HGET", outKey, outField)
-- 等待初始化
if outResult == initValue then
    return { 4 }
end
-- 不存在时初始化
if outResult == false then
    return { 1 }
end
-- 检查转入账户是否存在,不存在时初始化
if redis.call("HSETNX", inKey, inField, initValue) == 1 then
    return { 2 }
end
-- 获取转入账户
local inResult = redis.call("HGET", inKey, inField)
-- 等待初始化
if inResult == initValue then
    return { 4 }
end
-- 不存在时初始化
if inResult == false then
    return { 2 }
end
-- 转换为数字
local outBefore = tonumber(outResult)
local inBefore = tonumber(inResult)
-- 返回当前余额
if amount == 0 then
    return { 200, outBefore, inBefore, outBefore, inBefore }
end
-- 余额不足
if outBefore - amount < 0 then
    return { 202, outBefore, inBefore, outBefore, inBefore }
end
-- 原子转账：扣
local outBalance = redis.call("HINCRBY", outKey, outField, -amount)
-- 扣失败
if outBalance == false then
    return { 201, outBefore, inBefore, outBefore, inBefore }
end
-- 原子转账： 加
local inBalance = redis.call("HINCRBY", inKey, inField, amount)
-- 加失败
if inBalance == false then
    local outRoll = redis.call("HINCRBY", outKey, outField, amount)
    -- 回滚失败
    if outRoll == false then
        return { 205, outBefore, inBefore, tonumber(outBalance), inBefore }
    end
    --失败
    return { 206, outBefore, inBefore, tonumber(outRoll), inBefore }
end

return { 200, outBefore, inBefore, tonumber(outBalance), tonumber(inBalance) }