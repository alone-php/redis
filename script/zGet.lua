local key = KEYS[1]
local minScore = ARGV[1]
local maxScore = ARGV[2]
local items = redis.call("ZRANGEBYSCORE", key, minScore, maxScore, "WITHSCORES")
if #items > 0 then
    for i = 1, #items, 2 do
        redis.call("ZREM", key, items[i])
    end
end
return items