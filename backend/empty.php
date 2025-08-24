<?php

/**
 * 上传和Ping测试空响应端点（安全增强版）
 * 用于处理上传数据和Ping请求
 */

// 基本安全检查
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    http_response_code(405);
    header('Allow: GET, POST, HEAD');
    exit('Method Not Allowed');
}

// 限制上传数据大小（防止DoS攻击）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
    $maxUploadSize = 100 * 1024 * 1024; // 100MB限制
    
    if ($contentLength > $maxUploadSize) {
        http_response_code(413);
        exit('Payload Too Large');
    }
}

// 设置安全响应头
header('HTTP/1.1 200 OK');

if (isset($_GET['cors'])) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, HEAD');
    header('Access-Control-Allow-Headers: Content-Encoding, Content-Type');
}

// 防止缓存
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, s-maxage=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Connection: keep-alive');

// 安全头
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// 如果是POST请求，消耗上传数据但不存储
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 读取上传数据但不存储（测试目的）
    $input = fopen('php://input', 'rb');
    if ($input) {
        while (!feof($input)) {
            fread($input, 8192); // 读取并丢弃
        }
        fclose($input);
    }
}
