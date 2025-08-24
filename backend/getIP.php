<?php

/*
 * Enhanced IP detection script with ISP info from ipinfo.io/
 * 增强的IP检测脚本，支持ISP信息获取
 * 
 * Output: JSON string with processedString and rawIspInfo
 * 输出：包含processedString和rawIspInfo的JSON字符串
 */

// 基本安全检查
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit(json_encode(['error' => 'Method not allowed']));
}

// 生产环境建议设置为E_ERROR，开发环境可设置为E_ALL
error_reporting(E_ERROR);

// API密钥和配置安全检查
if (!defined('API_KEY_FILE')) {
    define('API_KEY_FILE', 'getIP_ipInfo_apikey.php');
}
if (!defined('SERVER_LOCATION_CACHE_FILE')) {
    define('SERVER_LOCATION_CACHE_FILE', 'getIP_serverLocation.php');
}
if (!defined('OFFLINE_IPINFO_DB_FILE')) {
    define('OFFLINE_IPINFO_DB_FILE', 'country_asn.mmdb');
}

require_once 'getIP_util.php';

/**
 * 获取本地或私有IP的描述信息
 * @param string $ip IP地址
 * @return string|null 本地IP描述或null
 */
function getLocalOrPrivateIpInfo($ip){
    // 使用统一的私有IP检测函数
    if (!isPrivateOrLocalIP($ip)) {
        return null;
    }
    
    // IPv6本地地址检测
    if ('::1' === $ip) {
        return 'localhost IPv6 access';
    }
    if (stripos($ip, 'fe80:') === 0) {
        return 'link-local IPv6 access';
    }
    if (preg_match('/^(fc|fd)([0-9a-f]{0,4}:){1,7}[0-9a-f]{1,4}$/i', $ip) === 1) {
        return 'ULA IPv6 access';
    }
    
    // IPv4本地地址检测
    if (strpos($ip, '127.') === 0) {
        return 'localhost IPv4 access';
    }
    if (strpos($ip, '10.') === 0) {
        return 'private IPv4 access';
    }
    if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip) === 1) {
        return 'private IPv4 access';
    }
    if (strpos($ip, '192.168.') === 0) {
        return 'private IPv4 access';
    }
    if (strpos($ip, '169.254.') === 0) {
        return 'link-local IPv4 access';
    }
    
    return 'private network access';
}

/**
 * 通过ipinfo.io API获取ISP信息（增强版）
 * @param string $ip IP地址
 * @return string|null JSON字符串或null
 */
function getIspInfo_ipinfoApi($ip){
    // 先检查缓存
    $cachedResult = IpInfoCache::get($ip, 'ipinfo');
    if ($cachedResult !== null) {
        return $cachedResult;
    }
    
    if (!file_exists(API_KEY_FILE) || !is_readable(API_KEY_FILE)){
        return null;
    }
    
    require API_KEY_FILE;
    if(empty($IPINFO_APIKEY)){
        return null;
    }
    
    $url = 'https://ipinfo.io/' . $ip . '/json?token=' . $IPINFO_APIKEY;
    $json = makeHttpRequest($url, 3); // 使用统一的HTTP请求函数
    
    if (!is_string($json)) {
        return null;
    }
    
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }
    
    $isp = null;
    // ISP名称可能在org或asn.name中
    if (array_key_exists('org', $data) && is_string($data['org']) && !empty($data['org'])) {
        $isp = preg_replace('/AS\\d+\\s/', '', $data['org']);
    } elseif (array_key_exists('asn', $data) && is_array($data['asn']) && !empty($data['asn']) && array_key_exists('name', $data['asn']) && is_string($data['asn']['name'])) {
        $isp = $data['asn']['name'];
    } else {
        return null;
    }
    
    $country = null;
    if(array_key_exists('country',$data) && is_string($data['country'])){
        $country = $data['country'];
    }
    
    // 距离计算（如果请求了的话）
    $distance = null;
    if(isset($_GET['distance']) && ($_GET['distance']==='mi' || $_GET['distance']==='km') && array_key_exists('loc', $data) && is_string($data['loc'])){
        $distance = calculateDistance($data['loc'], $_GET['distance']);
    }
    
    $processedString = $ip.' - '.$isp;
    if(is_string($country)){
        $processedString .= ', '.$country;
    }
    if(is_string($distance)){
        $processedString .= ' ('.$distance.')';
    }
    
    $result = json_encode([
        'processedString' => $processedString,
        'rawIspInfo' => $data ?: '',
    ]);
    
    // 存储到缓存
    IpInfoCache::set($ip, $result, 'ipinfo');
    
    return $result;
}

/**
 * 计算客户端与服务器之间的距离
 * @param string $clientLoc 客户端坐标
 * @param string $unit 单位（mi或km）
 * @return string|null 距离字符串或null
 */
function calculateDistance($clientLoc, $unit) {
    $serverLoc = null;
    
    // 读取缓存的服务器位置
    if (file_exists(SERVER_LOCATION_CACHE_FILE) && is_readable(SERVER_LOCATION_CACHE_FILE)) {
        require SERVER_LOCATION_CACHE_FILE;
    }
    
    // 如果没有缓存，获取服务器位置
    if (!is_string($serverLoc) || empty($serverLoc)) {
        if (!file_exists(API_KEY_FILE)) {
            return null;
        }
        require API_KEY_FILE;
        if (empty($IPINFO_APIKEY)) {
            return null;
        }
        
        $json = makeHttpRequest('https://ipinfo.io/json?token=' . $IPINFO_APIKEY, 3);
        if (!is_string($json)) {
            return null;
        }
        
        $sdata = json_decode($json, true);
        if (!is_array($sdata) || !array_key_exists('loc', $sdata) || !is_string($sdata['loc']) || empty($sdata['loc'])) {
            return null;
        }
        
        $serverLoc = $sdata['loc'];
        // 缓存服务器位置
        file_put_contents(SERVER_LOCATION_CACHE_FILE, "<?php\n\n\$serverLoc = '" . addslashes($serverLoc) . "';\n");
    }
    
    try {
        list($clientLatitude, $clientLongitude) = explode(',', $clientLoc);
        list($serverLatitude, $serverLongitude) = explode(',', $serverLoc);
        
        // 距离计算（采用大圆距离公式）
        $rad = M_PI / 180;
        $dist = acos(
            sin($clientLatitude * $rad) * sin($serverLatitude * $rad) + 
            cos($clientLatitude * $rad) * cos($serverLatitude * $rad) * 
            cos(($clientLongitude - $serverLongitude) * $rad)
        ) / $rad * 60 * 1.853;
        
        if ($unit === 'mi') {
            $dist /= 1.609344;
            $dist = round($dist, -1);
            return ($dist < 15) ? '<15 mi' : $dist . ' mi';
        } elseif ($unit === 'km') {
            $dist = round($dist, -1);
            return ($dist < 20) ? '<20 km' : $dist . ' km';
        }
    } catch (Exception $e) {
        // 距离计算失败，返回null
        return null;
    }
    
    return null;
}

/**
 * 通过ip-api.com获取ISP信息（备用API）
 * @param string $ip IP地址
 * @return string|null JSON字符串或null
 */
function getIspInfo_ipApiCom($ip) {
    // 先检查缓存
    $cachedResult = IpInfoCache::get($ip, 'ipapi');
    if ($cachedResult !== null) {
        return $cachedResult;
    }
    
    $url = "http://ip-api.com/json/{$ip}?lang=zh-CN&fields=status,message,country,regionName,city,isp,as,query";
    
    $response = makeHttpRequest($url, 2);
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (!$data || $data['status'] !== 'success') {
        return null;
    }
    
    $location = '';
    if (!empty($data['city'])) {
        $location .= $data['city'];
    }
    if (!empty($data['regionName']) && $data['regionName'] !== $data['city']) {
        $location .= ($location ? ', ' : '') . $data['regionName'];
    }
    if (!empty($data['country'])) {
        $location .= ($location ? ', ' : '') . $data['country'];
    }
    
    $isp = $data['isp'] ?? '未知运营商';
    
    // 运营商中文翻译
    $ispTranslations = [
        'China Telecom' => '中国电信',
        'Chinanet' => '中国电信',
        'China Unicom' => '中国联通',
        'China Mobile' => '中国移动',
        'CMCC' => '中国移动',
        'Alibaba' => '阿里云',
        'Tencent' => '腾讯云'
    ];
    
    foreach ($ispTranslations as $en => $cn) {
        if (stripos($isp, $en) !== false) {
            $isp = $cn;
            break;
        }
    }
    
    $processedString = $ip . ' - ' . $isp;
    if ($location) {
        $processedString .= ', ' . $location;
    }
    
    $result = json_encode([
        'processedString' => $processedString,
        'rawIspInfo' => $data ?: '',
    ]);
    
    // 存储到缓存
    IpInfoCache::set($ip, $result, 'ipapi');
    
    return $result;
}

if (PHP_MAJOR_VERSION >= 8){
    require_once("geoip2.phar");
}
function getIspInfo_ipinfoOfflineDb($ip){
    if (PHP_MAJOR_VERSION < 8 || !file_exists(OFFLINE_IPINFO_DB_FILE) || !is_readable(OFFLINE_IPINFO_DB_FILE)){
        return null;
    }
    $reader = new MaxMind\Db\Reader(OFFLINE_IPINFO_DB_FILE);
    $data = $reader->get($ip);
    if(!is_array($data)){
        return null;
    }
    $processedString = $ip.' - ' . $data['as_name'] . ', ' . $data['country_name'];
    return json_encode([
        'processedString' => $processedString,
        'rawIspInfo' => $data ?: '',
    ]);
}

function formatResponse_simple($ip,$ispName=null){
    $processedString=$ip;
    if(is_string($ispName)){
        $processedString.=' - '.$ispName;
    }
    return json_encode([
        'processedString' => $processedString,
        'rawIspInfo' => '',
    ]);
}

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 安全头，防止XSS和CSRF
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

if (isset($_GET['cors'])) {
    // 只允许必要的CORS头
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Max-Age: 86400');
}

// 缓存控制
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, s-maxage=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// 输入验证和清理
function validateInput() {
    // 验证distance参数
    if (isset($_GET['distance']) && !in_array($_GET['distance'], ['km', 'mi'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid distance parameter']);
        exit;
    }
    
    // 验证其他参数
    $allowedParams = ['isp', 'distance', 'cors', 'fallback', 'backup'];
    foreach ($_GET as $key => $value) {
        if (!in_array($key, $allowedParams, true)) {
            // 日志记录可疑参数，但不报错
            if (function_exists('error_log')) {
                error_log('Suspicious parameter in getIP.php: ' . $key);
            }
        }
    }
}

// 执行输入验证
validateInput();

// 定期清理过期缓存（1/100的概率）
if (rand(1, 100) === 1) {
    IpInfoCache::cleanup();
}

$ip = getClientIp();

// 如果请求ISP信息，依次尝试多种方式获取
if(isset($_GET['isp'])){
    $localIpInfo = getLocalOrPrivateIpInfo($ip);
    
    // 本地IP，无需获取进一步信息
    if (is_string($localIpInfo)) {
        echo formatResponse_simple($ip, $localIpInfo);
        exit;
    }
    
    // 尝试优先使用ipinfo.io API
    $r = getIspInfo_ipinfoApi($ip);
    if(!is_null($r)){
        echo $r;
        exit;
    }
    
    // 如果支持，尝试使用备用API（ip-api.com）
    if (isset($_GET['fallback']) || isset($_GET['backup'])) {
        $r = getIspInfo_ipApiCom($ip);
        if(!is_null($r)){
            echo $r;
            exit;
        }
    }
    
    // 尝试本地数据库
    $r = getIspInfo_ipinfoOfflineDb($ip);
    if(!is_null($r)){
        echo $r;
        exit;
    }
    
    // 所有方法都失败，返回简单响应
    echo formatResponse_simple($ip);
} else {
    // 不需要ISP信息，直接返回IP
    echo formatResponse_simple($ip);
}
