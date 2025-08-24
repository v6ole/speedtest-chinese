<?php

/**
 * 优化的下载测试数据生成器
 * 支持高效的随机数据生成和流式输出
 */

// 禁用压缩
ini_set('zlib.output_compression', 'Off');
ini_set('output_buffering', 'Off');
ini_set('output_handler', '');

/**
 * 获取数据块数量
 * @return int 数据块数量（1MB每块）
 */
function getChunkCount()
{
    if (
        !array_key_exists('ckSize', $_GET)
        || !ctype_digit($_GET['ckSize'])
        || (int) $_GET['ckSize'] <= 0
    ) {
        return 4; // 默认4MB
    }

    // 限制最大为1GB避免内存溢出
    if ((int) $_GET['ckSize'] > 1024) {
        return 1024;
    }

    return (int) $_GET['ckSize'];
}

/**
 * 发送HTTP响应头
 */
function sendHeaders()
{
    header('HTTP/1.1 200 OK');

    if (isset($_GET['cors'])) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST');
    }

    // 指示文件下载
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=random.dat');
    header('Content-Transfer-Encoding: binary');

    // 缓存设置：不缓存此请求
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, s-maxage=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Connection: keep-alive');
}

/**
 * 生成高效的随机数据
 * @param int $size 数据大小（字节）
 * @return string 随机数据
 */
function generateRandomData($size) {
    // 使用高效的伪随机生成器
    static $pattern = null;
    if ($pattern === null) {
        // 生成一个模式块，然后重复使用
        $pattern = '';
        for ($i = 0; $i < 1024; $i++) {
            $pattern .= chr(mt_rand(0, 255));
        }
    }
    
    $data = '';
    $remaining = $size;
    
    while ($remaining > 0) {
        $chunkSize = min($remaining, 1024);
        $data .= substr($pattern, 0, $chunkSize);
        $remaining -= $chunkSize;
    }
    
    return $data;
}

// 执行主逻辑
$chunks = getChunkCount();
$chunkSize = 1048576; // 1MB

// 发送响应头
sendHeaders();

// 生成并输出数据块
for ($i = 0; $i < $chunks; $i++) {
    // 使用高效的数据生成
    echo generateRandomData($chunkSize);
    
    // 立即发送数据
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
    
    // 检查客户端连接状态
    if (connection_aborted()) {
        break;
    }
}
