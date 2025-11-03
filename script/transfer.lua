local outKey = KEYS[1]
local inKey = KEYS[2]
local outField = ARGV[1]
local inField = ARGV[2]
local amount = tonumber(ARGV[3])
local initValue = ARGV[4]
-- 检查转出账户是否存在
if redis.call("HSETNX", outKey, outField, initValue) == 1 then
    return { 201, "out" }
end
-- 获取转出账户
local outResult = redis.call("HGET", outKey, outField)
-- 初始化中等待
if outResult == initValue then
    return { 202, "out" }
end
-- 初始化
if outResult == false then
    return { 201, "out" }
end
-- 检查转入账户是否存在
if redis.call("HSETNX", inKey, inField, initValue) == 1 then
    return { 201, "in" }
end
-- 获取转入账户
local inResult = redis.call("HGET", inKey, inField)
-- 初始化中等待
if inResult == initValue then
    return { 202, "in" }
end
if inResult == false then
    return { 201, "in" }
end
-- 转换为数字
local outBefore = tonumber(outResult)
local inBefore = tonumber(inResult)
-- 返回当前余额
if amount == 0 then
    return { 200, "Success", outBefore, inBefore, outBefore, inBefore }
end
-- 余额不足
if outBefore - amount < 0 then
    return { 203, "Balance insufficient", outBefore, inBefore, outBefore, inBefore }
end
-- 原子转账：扣
local outBalance = redis.call("HINCRBY", outKey, outField, -amount)
-- 原子转账： 加
local inBalance = redis.call("HINCRBY", inKey, inField, amount)
return { 200, "Success", outBefore, inBefore, tonumber(outBalance), tonumber(inBalance) }