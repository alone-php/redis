<?php
/**
 * 根据编号计算分表（按区间和每区表数）
 * @param int $id     编号
 * @param int $number 每区记录数 10000
 * @param int $count  每区表数   5
 * @param int $length 表编号位数，不填自动按最大编号长度补齐
 * @return string 分表编号，带前导零
 */
function alone_redis_id_table(int $id, int $number, int $count, int $length = 0): string {
    $range = intdiv($id - 1, $number);
    $offset = ($id - 1) % $number;
    $per = ceil($number / $count);
    $index = $range * $count + intdiv($offset, $per) + 1;
    $length = $length > 0 ? $length : strlen((string) (($range + 1) * $count));
    return str_pad((string) $index, $length, '0', STR_PAD_LEFT);
}

/**
 * 生成订单号
 * @return string
 */
function alone_redis_order_id(): string {
    try {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return bin2hex($data);
    } catch (Throwable $e) {
        return md5(uniqid('', true) . microtime());
    }
}