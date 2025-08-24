<?php

/**
 * 统一的IP工具类 - 避免代码重复
 * 整合了项目中所有IP获取和处理功能
 * 新增：缓存机制、性能优化
 */

/**
 * 获取客户端真实IP地址（增强版）
 * 支持多种代理和负载均衡器的IP头检测
 * @return string 客户端IP地址
 */
function getClientIp() {
    $ipHeaders = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_REAL_IP',            // Nginx
        'HTTP_X_FORWARDED_FOR',      // 标准代理服务器
        'HTTP_X_FORWARDED',          // 其他代理服务器
        'HTTP_X_CLUSTER_CLIENT_IP',  // 集群负载均衡器
        'HTTP_CLIENT_IP',            // 代理服务器
        'HTTP_FORWARDED_FOR',        // 代理服务器
        'HTTP_FORWARDED'             // RFC 7239
    ];
    
    foreach ($ipHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = $_SERVER[$header];
            
            // 处理多个IP地址（逗号分隔）
            if (strpos($ips, ',') !== false) {
                $ipList = array_map('trim', explode(',', $ips));
                // 优先获取第一个非私有IP
                foreach ($ipList as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return preg_replace('/^::ffff:/', '', $ip);
                    }
                }
                // 如果没有公网IP，返回第一个有效IP
                foreach ($ipList as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return preg_replace('/^::ffff:/', '', $ip);
                    }
                }
            } else {
                // 单个IP地址
                $ip = trim($ips);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return preg_replace('/^::ffff:/', '', $ip);
                }
            }
        }
    }
    
    // 最后回退到REMOTE_ADDR
    return preg_replace('/^::ffff:/', '', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
}

/**
 * 检测IP是否为私有或本地地址
 * @param string $ip IP地址
 * @return bool 是否为私有/本地IP
 */
function isPrivateOrLocalIP($ip) {
    // IPv6本地地址
    if ($ip === '::1') {
        return true;
    }
    
    // IPv4本地地址
    if ($ip === '127.0.0.1' || strpos($ip, '127.') === 0) {
        return true;
    }
    
    // 使用PHP内置的私有IP检测
    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === false;
}

/**
 * 创建HTTP请求（支持cURL和file_get_contents）
 * @param string $url 请求URL
 * @param int $timeout 超时时间（秒）
 * @return string|false 响应内容或false
 */
function makeHttpRequest($url, $timeout = 3) {
    // 优先使用cURL
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'LibreSpeed/2.0',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Connection: close'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode !== 200) {
            return false;
        }
        
        return $response;
    }
    
    // 回退到file_get_contents
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'method' => 'GET',
            'header' => "Accept: application/json\r\n" .
                       "Connection: close\r\n" .
                       "User-Agent: LibreSpeed/2.0\r\n",
            'ignore_errors' => true
        ]
    ]);
    
/**
 * IP信息缓存管理类
 */
class IpInfoCache {
    private static $cacheDir = null;
    private static $cacheTtl = 3600; // 1小时缓存
    
    /**
     * 初始化缓存目录
     */
    private static function initCacheDir() {
        if (self::$cacheDir === null) {
            self::$cacheDir = sys_get_temp_dir() . '/speedtest_ip_cache';
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0755, true);
            }
        }
    }
    
    /**
     * 生成缓存键
     */
    private static function getCacheKey($ip, $source = 'default') {
        return md5($ip . '_' . $source);
    }
    
    /**
     * 获取缓存数据
     */
    public static function get($ip, $source = 'default') {
        self::initCacheDir();
        
        $cacheFile = self::$cacheDir . '/' . self::getCacheKey($ip, $source) . '.json';
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $data = @file_get_contents($cacheFile);
        if (!$data) {
            return null;
        }
        
        $cacheData = json_decode($data, true);
        if (!$cacheData || !isset($cacheData['timestamp'], $cacheData['data'])) {
            return null;
        }
        
        // 检查是否过期
        if (time() - $cacheData['timestamp'] > self::$cacheTtl) {
            @unlink($cacheFile);
            return null;
        }
        
        return $cacheData['data'];
    }
    
    /**
     * 存储缓存数据
     */
    public static function set($ip, $data, $source = 'default') {
        self::initCacheDir();
        
        $cacheFile = self::$cacheDir . '/' . self::getCacheKey($ip, $source) . '.json';
        
        $cacheData = [
            'timestamp' => time(),
            'data' => $data
        ];
        
        @file_put_contents($cacheFile, json_encode($cacheData));
    }
    
    /**
     * 清理过期缓存
     */
    public static function cleanup() {
        self::initCacheDir();
        
        $files = glob(self::$cacheDir . '/*.json');
        $now = time();
        
        foreach ($files as $file) {
            if ($now - filemtime($file) > self::$cacheTtl) {
                @unlink($file);
            }
        }
    }
}
