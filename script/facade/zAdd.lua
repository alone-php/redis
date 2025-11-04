local key    = KEYS[1]
local score  = ARGV[1]
local value  = ARGV[2]
local ttl    = tonumber(ARGV[3])
local force  = tonumber(ARGV[4])
local result = redis.call("ZADD", key, score, value)
if ttl > 0 then
    if force == 1 or redis.call("TTL", key) < 0 then
        redis.call("EXPIRE", key, ttl)
    end
end
return result