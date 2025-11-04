local key    = KEYS[1]
local field  = ARGV[1]
local value  = ARGV[2]
local ttl    = tonumber(ARGV[3])
local result = redis.call("HSET", key, field, value)
if ttl > 0 then
    if tonumber(ARGV[4]) == 1 or redis.call("TTL", key) < 0 then
        redis.call("EXPIRE", key, ttl)
    end
end
return result