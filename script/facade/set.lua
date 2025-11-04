local key    = KEYS[1]
local value  = ARGV[1]
local ttl    = tonumber(ARGV[2])
local force  = tonumber(ARGV[3])
local result = redis.call("SET", key, value)
if ttl > 0 then
    if force == 1 or redis.call("TTL", key) < 0 then
        redis.call("EXPIRE", key, ttl)
    end
end
return result