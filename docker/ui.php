<?php
// 优化的用户IP获取函数
function getUserIP() {
    $ipHeaders = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_REAL_IP',        // Nginx
        'HTTP_X_FORWARDED_FOR',  // 代理服务器
        'HTTP_X_FORWARDED',      // 代理服务器
        'HTTP_X_CLUSTER_CLIENT_IP', // 集群负载均衡器
        'HTTP_CLIENT_IP',        // 代理服务器
        'HTTP_FORWARDED_FOR',    // 代理服务器
        'HTTP_FORWARDED'         // 代理服务器
    ];
    
    foreach ($ipHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = $_SERVER[$header];
            
            // 处理多个IP地址（逗号分隔）
            if (strpos($ips, ',') !== false) {
                $ipList = array_map('trim', explode(',', $ips));
                // 获取第一个非私有IP
                foreach ($ipList as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
                // 如果没有公网IP，返回第一个
                return $ipList[0];
            } else {
                // 单个IP地址
                $ip = trim($ips);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }
    
    // 如果没有找到其他IP，使用REMOTE_ADDR
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function getIpInfo($ip) {
    // 增加更多的运营商翻译规则
    $ispTranslations = [
        'China Telecom' => '中国电信',
        'Chinanet' => '中国电信',
        'China Telecom Corporation Limited' => '中国电信',
        'China Unicom' => '中国联通',
        'CHINA UNICOM' => '中国联通',
        'China Unicom Beijing Province Network' => '中国联通',
        'China Mobile' => '中国移动',
        'China Mobile Communications' => '中国移动',
        'China Mobile Communications Group Co.,Ltd' => '中国移动',
        'CMCC' => '中国移动',
        'Dr. Peng' => '鹏博士',
        'Dr.Peng Group' => '鹏博士',
        'Tietong' => '中国铁通',
        'China Tietong' => '中国铁通',
        'Alibaba' => '阿里云',
        'Alibaba Cloud' => '阿里云',
        'Aliyun' => '阿里云',
        'Tencent' => '腾讯云',
        'Tencent Cloud' => '腾讯云',
        'Baidu' => '百度云',
        'China Education and Research Network' => '教育网',
        'CERNET' => '教育网',
        'CSTNET' => '中科院网络中心',
        'China Science and Technology Network' => '中科院网络中心',
        'Great Wall Broadband' => '长城宽带',
        'Beijing Gehua CATV Network' => '歌华有线'
    ];
    
    // 使用cURL替代file_get_contents，提供更好的控制
    $apiSources = [
        [
            'url' => "http://ip-api.com/json/{$ip}?lang=zh-CN&fields=status,message,country,regionName,city,isp,as,query",
            'parser' => 'parseIpApi',
            'timeout' => 3
        ]
    ];
    
    foreach ($apiSources as $source) {
        $result = makeApiRequest($source['url'], $source['timeout']);
        if ($result !== false) {
            $data = json_decode($result, true);
            if ($data) {
                $parsed = call_user_func($source['parser'], $data, $ispTranslations);
                if ($parsed !== false) {
                    return $parsed;
                }
            }
        }
    }
    
    // 所有API都失败，返回基础信息
    return ['未知位置', '未知运营商'];
}

// 使用cURL进行API请求，提供更好的超时控制
function makeApiRequest($url, $timeout = 3) {
    // 检查cURL是否可用
    if (!function_exists('curl_init')) {
        // 如果cURL不可用，使用优化的file_get_contents
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
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'LibreSpeed/1.0',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Connection: close',
            'Cache-Control: no-cache'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // 检查响应是否有效
    if ($response === false || $httpCode !== 200 || !empty($error)) {
        return false;
    }
    
    return $response;
}

// 解析ip-api.com的响应
function parseIpApi($data, $ispTranslations) {
    if (!isset($data['status']) || $data['status'] !== 'success') {
        return false;
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
    
    $isp = $data['isp'] ?? '未知';
    $isp = translateIspName($isp, $ispTranslations);
    
    return [$location ?: '未知', $isp];
}

// 解析ipapi.co的响应
function parseIpApiCo($data, $ispTranslations) {
    if (isset($data['error']) && $data['error']) {
        return false;
    }
    
    $location = '';
    if (!empty($data['city'])) {
        $location .= $data['city'];
    }
    if (!empty($data['region']) && $data['region'] !== $data['city']) {
        $location .= ($location ? ', ' : '') . $data['region'];
    }
    if (!empty($data['country_name'])) {
        $location .= ($location ? ', ' : '') . $data['country_name'];
    }
    
    $isp = $data['org'] ?? '未知';
    $isp = translateIspName($isp, $ispTranslations);
    
    return [$location ?: '未知', $isp];
}

// 优化的ISP名称翻译函数
function translateIspName($isp, $translations) {
    // 去掉常见的前缀如AS号码
    $isp = preg_replace('/^AS\d+\s+/i', '', $isp);
    
    foreach ($translations as $en => $cn) {
        if (stripos($isp, $en) !== false) {
            return $cn;
        }
    }
    return $isp;
}

// 处理异步IP信息请求
if (isset($_GET['async_ip'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    
    if (isset($_GET['cors'])) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
    }
    
    try {
        $ip = getUserIP();
        
        if (isPrivateOrLocalIP($ip)) {
            echo json_encode([
                'error' => false,
                'ip' => $ip,
                'location' => '本地, 中国',
                'isp' => '本地网络'
            ]);
        } else {
            list($location, $isp) = getIpInfo($ip);
            echo json_encode([
                'error' => false,
                'ip' => $ip,
                'location' => $location,
                'isp' => $isp
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'error' => true,
            'message' => '获取IP信息失败: ' . $e->getMessage()
        ]);
    }
    exit;
}

$userIP = getUserIP();

// 优化的IP验证和分类
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

// 检查是否为本地或私有IP
if (isPrivateOrLocalIP($userIP)) {
    $userLocation = "本地, 中国";
    $userISP = "本地网络";
    $isAsync = false;
} elseif (isset($_GET['sync'])) {
    // 明确要求同步获取IP信息
    $maxWaitTime = 3;
    $startTime = microtime(true);
    
    $oldTimeLimit = ini_get('max_execution_time');
    ini_set('max_execution_time', 5);
    
    try {
        list($userLocation, $userISP) = getIpInfo($userIP);
        $elapsedTime = microtime(true) - $startTime;
        
        if (($userLocation === '未知位置' && $userISP === '未知运营商') || $elapsedTime > $maxWaitTime) {
            $userLocation = "网络位置";
            $userISP = "网络运营商";
        }
        $isAsync = false;
    } catch (Exception $e) {
        $userLocation = "网络位置";
        $userISP = "网络运营商";
        $elapsedTime = microtime(true) - $startTime;
        $isAsync = false;
    } finally {
        ini_set('max_execution_time', $oldTimeLimit);
    }
} else {
    // 默认使用异步模式，页面立即加载
    $userLocation = "正在获取...";
    $userISP = "正在获取...";
    $isAsync = true;
    $elapsedTime = 0;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= getenv('TITLE') ?: 'SpeedTest - 网络速度测试' ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: #1e293b;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* Main Content */
        .main {
            padding: 2rem 0;
        }

        .hero {
            text-align: center;
            margin-bottom: 2rem;
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #1e293b 0%, #475569 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.125rem;
            color: #64748b;
            max-width: 32rem;
            margin: 0 auto;
        }

        /* Speed Test Card */
        .speed-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .test-initial {
            text-align: center;
        }

        .test-icon {
            width: 8rem;
            height: 8rem;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .test-icon.pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .start-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .start-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }

        .start-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Progress */
        .progress-container {
            margin: 1.5rem 0;
        }

        .progress-text {
            font-size: 1.125rem;
            font-weight: 500;
            margin-bottom: 1rem;
            color: #475569;
        }

        .progress-bar {
            width: 100%;
            max-width: 24rem;
            margin: 0 auto;
            height: 0.75rem;
            background: rgba(59, 130, 246, 0.2);
            border-radius: 9999px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6 0%, #8b5cf6 100%);
            border-radius: 9999px;
            transition: width 0.3s ease;
            width: 0%;
        }

        .progress-percent {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 0.5rem;
        }

        /* Results */
        .results {
            display: none;
        }

        .results.show {
            display: block;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .result-item {
            text-align: center;
        }

        .result-icon {
            width: 2rem;
            height: 2rem;
            margin: 0 auto 0.5rem;
        }

        .result-label {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .result-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }

        .result-unit {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .result-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }

        .badge-excellent { background: #10b981; }
        .badge-good { background: #3b82f6; }
        .badge-average { background: #f59e0b; }
        .badge-poor { background: #ef4444; }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-secondary {
            background: transparent;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        /* Info Cards */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 0.75rem;
            padding: 1.5rem;
            position: relative;
        }

        .info-title {
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-title:hover {
            color: #3b82f6;
        }

        .info-title:active {
            color: #2563eb;
        }

        .refresh-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .refresh-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #334155;
        }

        .refresh-btn:active {
            background: #e2e8f0;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }

        .info-label {
            color: #64748b;
        }

        .info-value {
            font-weight: 500;
        }

        /* Footer */
        .footer {
            padding: 1rem 0;
            margin-top: 2rem;
            text-align: center;
            color: #64748b;
        }

        /* Credits Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 100;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.show {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            cursor: pointer;
            font-size: 1.5rem;
            color: #9ca3af;
        }
        .modal-close:hover {
            color: #1e293b;
        }
        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        .credits-list {
            list-style: none;
            padding: 0;
            margin-top: 1rem;
        }
        .credits-list li {
            margin-bottom: 0.75rem;
        }
        .credits-list strong {
            display: inline-block;
            width: 150px;
            color: #3b82f6;
        }
        .credits-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-style: italic;
            color: #64748b;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }
            .hero p {
                font-size: 1rem;
            }

            .results-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
                align-items: start;
            }

            .result-value {
                font-size: 1.5rem;
            }
            .result-label, .result-unit {
                font-size: 0.75rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .speed-card, .info-card {
                padding: 1rem;
            }

            .test-icon {
                width: 6rem;
                height: 6rem;
            }
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <main class="main">
        <div class="container">
            <!-- Hero Section -->
            <div class="hero">
                <h1><?= getenv('TITLE') ?: '网络速度测试' ?></h1>
                <p><?= getenv('SUBTITLE') ?: '测试您的网络连接速度，获取准确的下载、上传速度和延迟数据' ?></p>
            </div>

            <!-- Speed Test Card -->
            <div class="speed-card">
                <!-- Initial State -->
                <div id="initial-state" class="test-initial">
                    <div class="test-icon" id="test-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                           <path d="M5 12.55a11 11 0 0 1 14.08 0"></path>
                           <path d="M1.42 9a16 16 0 0 1 21.16 0"></path>
                           <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                           <line x1="12" y1="20" x2="12.01" y2="20"></line>
                        </svg>
                    </div>
                    <button class="start-btn" onclick="startSpeedTest()">开始测速</button>
                </div>

                <!-- Testing State -->
                <div id="testing-state" class="test-initial hidden">
                    <div class="test-icon pulse">
                        <svg width="64" height="64" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                           <path d="M5 12.55a11 11 0 0 1 14.08 0"></path>
                           <path d="M1.42 9a16 16 0 0 1 21.16 0"></path>
                           <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                           <line x1="12" y1="20" x2="12.01" y2="20"></line>
                        </svg>
                    </div>
                    <div class="progress-container">
                        <p class="progress-text" id="progress-text">正在测试延迟...</p>
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill"></div>
                        </div>
                        <p class="progress-percent" id="progress-percent">0%</p>
                    </div>
                </div>

                <!-- Results State -->
                <div id="results-state" class="results">
                    <div class="results-grid">
                        <div class="result-item">
                            <svg class="result-icon" style="color: #10b981;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            <p class="result-label">下载速度</p>
                            <p class="result-value" id="download-speed">0</p>
                            <p class="result-unit">Mbps</p>
                            <span class="result-badge" id="download-badge">测试中</span>
                        </div>

                        <div class="result-item">
                            <svg class="result-icon" style="color: #3b82f6;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                            <p class="result-label">上传速度</p>
                            <p class="result-value" id="upload-speed">0</p>
                            <p class="result-unit">Mbps</p>
                            <span class="result-badge" id="upload-badge">测试中</span>
                        </div>

                        <div class="result-item">
                            <svg class="result-icon" style="color: #f59e0b;" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            <p class="result-label">延迟</p>
                            <p class="result-value" id="ping-value">0</p>
                            <p class="result-unit">ms</p>
                            <span class="result-badge badge-good" id="jitter-info">抖动: 0ms</span>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button class="start-btn" onclick="startSpeedTest()">重新测试</button>
                    </div>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="info-grid">
                <div class="info-card">
                    <button class="refresh-btn" onclick="refreshPage()" title="刷新页面">
                        刷新
                    </button>
                    <h3 class="info-title" id="connection-info-title" onclick="toggleMode()" style="cursor: pointer; user-select: none; transition: color 0.3s ease;" title="点击切换同步/异步模式">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20 3H4c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h3l-1 1v2h12v-2l-1-1h3c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM4 14V5h16v9H4z"/>
                        </svg>
                        连接信息
                        <span id="mode-indicator" style="font-size: 0.75rem; color: #64748b; margin-left: 0.5rem; font-weight: normal;">
                            <?php echo isset($_GET['sync']) ? '(同步模式)' : '(异步模式)'; ?>
                        </span>
                    </h3>
                    <div class="info-item">
                        <span class="info-label">IP 地址:</span>
                        <span class="info-value"><?php echo $userIP; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">位置:</span>
                        <span class="info-value"><?php echo $userLocation; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">运营商:</span>
                        <span class="info-value"><?php echo $userISP; ?></span>
                    </div>
                    <!-- 添加调试信息，可在生产环境中删除 -->
                    <?php if (isset($_GET['debug'])): ?>
                    <div class="info-item" style="font-size: 0.75rem; color: #9ca3af;">
                        <span class="info-label">调试:</span>
                        <span class="info-value">
                            IP类型: <?php echo isPrivateOrLocalIP($userIP) ? '本地/私有' : '公网'; ?>
                            | 原始IP: <?php echo $_SERVER['REMOTE_ADDR'] ?? 'N/A'; ?>
                            <?php if (isset($elapsedTime)): ?>
                            | 获取时间: <?php echo number_format($elapsedTime, 2); ?>s
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <h3 class="info-title">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/>
                        </svg>
                        速度建议
                    </h3>
                    <div class="info-item">
                        <span class="info-label">网页浏览:</span>
                        <span class="info-value">1~5 Mbps</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">视频通话:</span>
                        <span class="info-value">1~4 Mbps</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">4K 视频:</span>
                        <span class="info-value">25+ Mbps</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>
                <?= getenv('COPYRIGHT') ?: '&copy; 2024 SpeedTest. 专业的网络速度测试工具' ?>
                <span style="margin: 0 0.5rem;">|</span>
                <a href="#" id="show-credits" style="color: inherit; text-decoration: none;">鸣谢</a>
            </p>
        </div>
    </footer>

    <!-- Credits Modal -->
    <div id="credits-modal-overlay" class="modal-overlay">
        <div class="modal-content">
            <span id="close-credits" class="modal-close">&times;</span>
            <h2 class="modal-title">鸣谢</h2>
            <p>本项目得以实现，离不开以下优秀的开源项目与服务：</p>
            <ul class="credits-list">
                <li><strong>核心测速引擎:</strong> <a href="https://github.com/librespeed/speedtest" target="_blank">LibreSpeed</a></li>
                <li><strong>IP地理位置接口:</strong> <a href="https://ip-api.com/" target="_blank">ip-api.com</a></li>
                <li><strong>图标库:</strong> <a href="https://heroicons.com/" target="_blank">Heroicons</a></li>
            </ul>
            <p class="credits-footer">v6ole：站在巨人的肩膀上。</p>
        </div>
    </div>

    <script type="text/javascript" src="/speedtest.js"></script>
    <script>
        let isTestRunning = false;
        let s = new Speedtest();
        let testData = {};
        let lastUpdateTime = 0; // 用于防抖的时间戳
        
        // 防抖函数，减少频繁的UI更新
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function startSpeedTest() {
            if (isTestRunning) {
                s.abort();
                return;
            }
            
            isTestRunning = true;
            s.setParameter("test_order", "I_P_D_U");
            
            document.getElementById('initial-state').classList.add('hidden');
            document.getElementById('testing-state').classList.remove('hidden');
            document.getElementById('results-state').classList.remove('show');
            document.getElementById('progress-text').textContent = '正在初始化...';
            updateProgress(0);
            
            s.onupdate = function(data) {
                if (!isTestRunning) return;
                testData = data;
                let status = data.testState;
                
                // 使用requestAnimationFrame优化UI更新，防止阻塞主线程
                requestAnimationFrame(function() {
                    if (status === 1) { // Download test
                        document.getElementById('progress-text').textContent = '正在测试下载速度...';
                        updateProgress(Math.round(data.dlProgress * 100));
                        if (data.dlStatus && data.dlStatus !== '0.00') {
                            document.getElementById('download-speed').textContent = data.dlStatus;
                        }
                    }
                    if (status === 2) { // Ping test
                        document.getElementById('progress-text').textContent = '正在测试延迟...';
                        updateProgress(Math.round(data.pingProgress * 100));
                        if (data.pingStatus) {
                            document.getElementById('ping-value').textContent = data.pingStatus;
                        }
                        if (data.jitterStatus) {
                            document.getElementById('jitter-info').textContent = `抖动: ${data.jitterStatus}ms`;
                        }
                    }
                    if (status === 3) { // Upload test
                        document.getElementById('progress-text').textContent = '正在测试上传速度...';
                        updateProgress(Math.round(data.ulProgress * 100));
                        if (data.ulStatus && data.ulStatus !== '0.00') {
                            document.getElementById('upload-speed').textContent = data.ulStatus;
                        }
                    }
                });
            };
            
            s.onend = function(aborted) {
                isTestRunning = false;
                if (aborted) {
                    console.log("测试已中止");
                    // 使用requestAnimationFrame优化UI更新
                    requestAnimationFrame(() => {
                        document.getElementById('testing-state').classList.add('hidden');
                        document.getElementById('initial-state').classList.remove('hidden');
                    });
                    return;
                }
                
                // 添加错误处理
                try {
                    showResults(testData);
                } catch (error) {
                    console.error('显示测试结果时出错:', error);
                    // 如果出错，显示错误信息
                    requestAnimationFrame(() => {
                        document.getElementById('progress-text').textContent = '测试完成，但显示结果时出错';
                        document.getElementById('testing-state').classList.add('hidden');
                        document.getElementById('initial-state').classList.remove('hidden');
                    });
                }
            };

            s.start();
        }

        // 优化的进度更新函数，使用防抖和requestAnimationFrame
        const updateProgress = debounce(function(progress) {
            const now = Date.now();
            // 限制更新频率为最多60fps
            if (now - lastUpdateTime < 16) return; // ~60fps
            lastUpdateTime = now;
            
            requestAnimationFrame(() => {
                document.getElementById('progress-fill').style.width = progress + '%';
                document.getElementById('progress-percent').textContent = progress + '%';
            });
        }, 50);

        function showResults(results) {
            const downloadSpeed = parseFloat(results.dlStatus) || 0;
            const uploadSpeed = parseFloat(results.ulStatus) || 0;
            const pingValue = parseFloat(results.pingStatus) || 0;
            const jitterValue = parseFloat(results.jitterStatus) || 0;

            // 使用requestAnimationFrame批量更新结果显示，减少DOM重绘
            requestAnimationFrame(() => {
                // Update result values
                document.getElementById('download-speed').textContent = downloadSpeed.toFixed(2);
                document.getElementById('upload-speed').textContent = uploadSpeed.toFixed(2);
                document.getElementById('ping-value').textContent = pingValue.toFixed(2);
                document.getElementById('jitter-info').textContent = `抖动: ${jitterValue.toFixed(2)}ms`;
                
                // Update badges
                const downloadBadge = getSpeedLevel(downloadSpeed);
                document.getElementById('download-badge').textContent = downloadBadge.level;
                document.getElementById('download-badge').className = 'result-badge ' + downloadBadge.class;
                
                const uploadBadge = getSpeedLevel(uploadSpeed);
                document.getElementById('upload-badge').textContent = uploadBadge.level;
                document.getElementById('upload-badge').className = 'result-badge ' + uploadBadge.class;
                
                // Hide testing state, show results
                document.getElementById('testing-state').classList.add('hidden');
                document.getElementById('results-state').classList.add('show');
            });
        }

        function getSpeedLevel(speed) {
            if (speed >= 50) return { level: '优秀', class: 'badge-excellent' };
            if (speed >= 25) return { level: '良好', class: 'badge-good' };
            if (speed >= 10) return { level: '一般', class: 'badge-average' };
            return { level: '较慢', class: 'badge-poor' };
        }

        function refreshPage() {
            window.location.reload();
        }

        // 切换同步/异步模式
        function toggleMode() {
            const currentUrl = new URL(window.location);
            const isCurrentlySync = currentUrl.searchParams.has('sync');
            
            if (isCurrentlySync) {
                // 当前是同步模式，切换到异步模式
                currentUrl.searchParams.delete('sync');
            } else {
                // 当前是异步模式，切换到同步模式
                currentUrl.searchParams.set('sync', '1');
            }
            
            // 跳转到新URL
            window.location.href = currentUrl.toString();
        }

        // Credits Modal Logic
        const showCreditsBtn = document.getElementById('show-credits');
        const closeCreditsBtn = document.getElementById('close-credits');
        const creditsModalOverlay = document.getElementById('credits-modal-overlay');

        showCreditsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            creditsModalOverlay.classList.add('show');
        });

        closeCreditsBtn.addEventListener('click', function() {
            creditsModalOverlay.classList.remove('show');
        });

        creditsModalOverlay.addEventListener('click', function(e) {
            if (e.target === creditsModalOverlay) {
                creditsModalOverlay.classList.remove('show');
            }
        });
        
        // 异步加载IP信息
        <?php if ($isAsync): ?>
        function loadIpInfoAsync() {
            // 使用同步模式相同的API逻辑，确保显示中文信息
            fetch('?async_ip=1')
                .then(response => response.json())
                .then(data => {
                    if (data && !data.error) {
                        updateIpInfo(data.location || '网络位置', data.isp || '网络运营商');
                    } else {
                        updateIpInfo('网络位置', '网络运营商');
                    }
                })
                .catch(error => {
                    console.warn('加载IP信息失败:', error);
                    updateIpInfo('网络位置', '网络运营商');
                });
        }
        
        function updateIpInfo(location, isp) {
            const infoItems = document.querySelectorAll('.info-item');
            
            infoItems.forEach(item => {
                const label = item.querySelector('.info-label');
                const value = item.querySelector('.info-value');
                
                if (label && value) {
                    if (label.textContent.includes('位置')) {
                        value.textContent = location;
                    } else if (label.textContent.includes('运营商')) {
                        value.textContent = isp;
                    }
                }
            });
        }
        
        // 页面加载完成后异步加载IP信息
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(loadIpInfoAsync, 100); // 稍微延迟以确保页面元素已加载
        });
        <?php endif; ?>
    </script>
</body>
</html>