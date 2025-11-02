<?php


namespace AlonePhp\Redis;

use Throwable;

trait Lua {
    // LUA脚本信息
    protected array $script = [];


    /**
     * 单帐户
     * @return string
     */
    public static function luaBalance(): string {
        return <<<LUA
local key = KEYS[1]
local field = ARGV[1]
local amount = tonumber(ARGV[2])
local init_value = ARGV[3]

-- 检查账户是否存在
local exists = redis.call("HEXISTS", key, field)
if exists == 0 then
	local set_result = redis.call("HSETNX", key, field, init_value)
	if set_result == 1 then
		return {201}
	end
end

-- 获取
local res = redis.call("HGET", key, field)

-- 初始化中等待
if res == init_value then
	return {202}
end

-- 初始化
if res == false then
	return {201}
end

-- 转换为数字
local before = tonumber(res)

-- 返回当前余额
if amount == 0 then
	return {200, "Success", before, before}
end

-- 余额不足
if amount < 0 and before + amount < 0 then
	return {203, "Balance insufficient", before, before}
end

-- 原子加扣
local balance = redis.call("HINCRBY", key, field, amount)

-- 失败
if balance == false then
	return {206, "fail", before, before}
end

return {200, "Success", before, tonumber(balance)}
LUA;

    }

    /**
     * 双帐户
     * @return string
     */
    public static function luaTransfer(): string {
        return <<<LUA
local outKey = KEYS[1]
local inKey = KEYS[2]
local outField = ARGV[1]
local inField = ARGV[2]
local amount = tonumber(ARGV[3])
local init_value = ARGV[4]

-- 检查转出账户是否存在
local outExists = redis.call("HEXISTS", outKey, outField)
if outExists == 0 then
	if redis.call("HSETNX", outKey, outField, init_value) == 1 then
		return {201, "out"} -- 需要初始化转出账户
	end
end

-- 检查转入账户是否存在
local inExists = redis.call("HEXISTS", inKey, inField)
if inExists == 0 then
	if redis.call("HSETNX", inKey, inField, init_value) == 1 then
		return {201, "in"} -- 需要初始化转入账户
	end
end

-- 获取转出账户
local outRes = redis.call("HGET", outKey, outField)
-- 初始化中等待
if outRes == init_value then
	return {202, "out"}
end
-- 初始化
if outRes == false then
	return {201, "out"}
end

-- 获取转入账户
local inRes = redis.call("HGET", inKey, inField)
-- 初始化中等待
if inRes == init_value then
	return {202, "in"}
end
if inRes == false then
	return {201, "in"}
end

-- 转换为数字
local outAmount = tonumber(outRes)
local inAmount = tonumber(inRes)

-- 返回当前余额
if amount == 0 then
	return {200, "Success", outAmount, inAmount, outAmount, inAmount}
end

-- 余额不足
if outAmount - amount < 0 then
	return {203, "Balance insufficient", outAmount, inAmount, outAmount, inAmount}
end

-- 原子转账：扣
local newOutAmount = redis.call("HINCRBY", outKey, outField, -amount)

-- 扣失败
if newOutAmount == false then
	return {207, "fail",outAmount, inAmount, outAmount, inAmount}
end

-- 原子转账： 加
local newInAmount = redis.call("HINCRBY", inKey,  inField, amount)

-- 加失败
if newInAmount == false then
	local outRoll = redis.call("HINCRBY", outKey, outField, amount)
	-- 回滚失败
	if outRoll == false then
		return {208, "fail", outAmount, inAmount, newOutAmount, inAmount}
	end
	--失败
	return {206, "fail", outAmount, inAmount, outRoll, inAmount}
end

return {200, "Success", outAmount, inAmount, newOutAmount, newInAmount}
LUA;
    }
}