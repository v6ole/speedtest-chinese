<?php
// 异步IP信息获取接口
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// 允许跨域请求（如果需要）
if (isset($_GET['cors'])) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
}

// 获取用户IP的函数
function getUserIP() {
    $ipHeaders = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED'
    ];
    
    foreach ($ipHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = $_SERVER[$header];
            
            if (strpos($ips, ',') !== false) {
                $ipList = array_map('trim', explode(',', $ips));
                foreach ($ipList as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
                return $ipList[0];
            } else {
                $ip = trim($ips);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function isPrivateOrLocalIP($ip) {
    if ($ip === '::1' || $ip === '127.0.0.1' || strpos($ip, '127.') === 0) {
        return true;
    }
    
    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === false;
}

function makeApiRequest($url, $timeout = 2) {
    if (!function_exists('curl_init')) {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'method' => 'GET',
                'header' => "Connection: close\r\n",
                'ignore_errors' => true
            ]
        ]);
        return @file_get_contents($url, false, $context);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'LibreSpeed/1.0',
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

function getIpInfoAsync($ip) {
    $url = "http://ip-api.com/json/{$ip}?lang=zh-CN&fields=status,message,country,regionName,city,isp,as,query";
    
    $response = makeApiRequest($url, 2);
    if ($response === false) {
        return ['error' => true, 'message' => 'API请求失败'];
    }
    
    $data = json_decode($response, true);
    if (!$data || $data['status'] !== 'success') {
        return ['error' => true, 'message' => 'API响应无效'];
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
    
    // 运营商翻译
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
    
    return [
        'error' => false,
        'ip' => $ip,
        'location' => $location ?: '未知位置',
        'isp' => $isp
    ];
}

// 主逻辑
try {
    $userIP = getUserIP();
    
    if (isPrivateOrLocalIP($userIP)) {
        echo json_encode([
            'error' => false,
            'ip' => $userIP,
            'location' => '本地, 中国',
            'isp' => '本地网络'
        ]);
    } else {
        $result = getIpInfoAsync($userIP);
        echo json_encode($result);
    }
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => '获取IP信息时发生错误: ' . $e->getMessage()
    ]);
}
?>
