local key = KEYS[1]
local ttl = tonumber(ARGV[1])
local force = tonumber(ARGV[2])

-- 后面 ARGV[3..] = field, value 成对出现
for i = 3, #ARGV, 2 do
    redis.call("HSET", key, ARGV[i], ARGV[i + 1])
end

if ttl > 0 then
    if force == 1 or redis.call("TTL", key) < 0 then
        redis.call("EXPIRE", key, ttl)
    end
end

return 1