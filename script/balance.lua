local key = KEYS[1]
local field = ARGV[1]
local amount = tonumber(ARGV[2])
local initValue = ARGV[3]
-- 检查账户是否存在
if redis.call("HSETNX", key, field, initValue) == 1 then
    return { 201 }
end
-- 获取
local result = redis.call("HGET", key, field)
-- 初始化中等待
if result == initValue then
    return { 202 }
end
-- 初始化
if result == false then
    return { 201 }
end
-- 转换为数字
local before = tonumber(result)
-- 返回当前余额
if amount == 0 then
    return { 200, "Success", before, before }
end
-- 余额不足
if amount < 0 and before + amount < 0 then
    return { 203, "Balance insufficient", before, before }
end
-- 原子加扣
local balance = redis.call("HINCRBY", key, field, amount)
return { 200, "Success", before, tonumber(balance) }