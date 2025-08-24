<?php
// API可用性测试脚本
header('Content-Type: text/html; charset=utf-8');

function testApi($name, $url, $timeout = 5) {
    echo "<h3>测试 {$name}</h3>\n";
    echo "<p>URL: {$url}</p>\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'user_agent' => 'LibreSpeed/1.0',
            'method' => 'GET',
            'header' => "Accept: application/json\r\n" .
                       "Accept-Encoding: gzip, deflate\r\n" .
                       "Connection: close\r\n",
            'ignore_errors' => true
        ]
    ]);
    
    $startTime = microtime(true);
    $response = @file_get_contents($url, false, $context);
    $endTime = microtime(true);
    $requestTime = $endTime - $startTime;
    
    echo "<p><strong>响应时间:</strong> " . number_format($requestTime, 3) . " 秒</p>\n";
    
    if ($response === false) {
        echo "<p style='color: red;'><strong>状态:</strong> ❌ 请求失败</p>\n";
        echo "<p><strong>错误信息:</strong> 无法连接到API</p>\n";
        return false;
    }
    
    echo "<p style='color: green;'><strong>状态:</strong> ✅ 请求成功</p>\n";
    echo "<p><strong>响应长度:</strong> " . strlen($response) . " 字节</p>\n";
    
    $data = json_decode($response, true);
    if ($data) {
        echo "<p><strong>JSON解析:</strong> ✅ 成功</p>\n";
        echo "<pre><strong>响应内容:</strong>\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";
    } else {
        echo "<p style='color: orange;'><strong>JSON解析:</strong> ⚠️ 失败</p>\n";
        echo "<pre><strong>原始响应:</strong>\n" . htmlspecialchars(substr($response, 0, 500)) . "</pre>\n";
    }
    
    echo "<hr>\n";
    return true;
}

function getTestIP() {
    // 尝试获取真实IP
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '8.8.8.8'; // fallback到公共DNS
}

$testIP = getTestIP();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API可用性测试</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background: #f8fafc;
            line-height: 1.6;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h1 { color: #1e293b; margin-bottom: 20px; }
        h3 { color: #475569; margin-top: 30px; }
        pre {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 14px;
        }
        .info-box {
            background: #e0f2fe;
            border-left: 4px solid #0ea5e9;
            padding: 15px;
            margin: 20px 0;
        }
        .test-summary {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
        }
        .btn {
            background: #3b82f6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
        }
        .btn:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 API可用性测试</h1>
        
        <div class="info-box">
            <p><strong>测试IP:</strong> <?= htmlspecialchars($testIP) ?></p>
            <p><strong>服务器IP:</strong> <?= $_SERVER['REMOTE_ADDR'] ?? 'N/A' ?></p>
            <p><strong>测试时间:</strong> <?= date('Y-m-d H:i:s') ?></p>
        </div>

        <?php
        $apis = [
            [
                'name' => 'ip-api.com (主要API)',
                'url' => "http://ip-api.com/json/{$testIP}?lang=zh-CN&fields=status,message,country,regionName,city,isp,as,query",
                'timeout' => 2
            ],
            [
                'name' => 'ipapi.co (备用API)',
                'url' => "https://ipapi.co/{$testIP}/json/",
                'timeout' => 3
            ],
            [
                'name' => 'ip-api.com (不带参数)',
                'url' => "http://ip-api.com/json/{$testIP}",
                'timeout' => 2
            ]
        ];

        $successCount = 0;
        $totalTime = 0;

        foreach ($apis as $api) {
            $startTime = microtime(true);
            $success = testApi($api['name'], $api['url'], $api['timeout']);
            $endTime = microtime(true);
            
            if ($success) {
                $successCount++;
            }
            $totalTime += ($endTime - $startTime);
        }
        ?>

        <div class="test-summary">
            <h3>📊 测试总结</h3>
            <p><strong>成功API数量:</strong> <?= $successCount ?> / <?= count($apis) ?></p>
            <p><strong>总测试时间:</strong> <?= number_format($totalTime, 3) ?> 秒</p>
            <p><strong>平均响应时间:</strong> <?= number_format($totalTime / count($apis), 3) ?> 秒</p>
            
            <?php if ($successCount == 0): ?>
                <p style="color: red;"><strong>⚠️ 所有API都不可用，建议检查网络连接或防火墙设置</strong></p>
            <?php elseif ($successCount < count($apis)): ?>
                <p style="color: orange;"><strong>⚠️ 部分API不可用，但应用仍可正常工作</strong></p>
            <?php else: ?>
                <p style="color: green;"><strong>✅ 所有API都正常工作</strong></p>
            <?php endif; ?>
        </div>

        <h3>🛠 故障排除建议</h3>
        <ul>
            <li><strong>网络连通性:</strong> 检查服务器是否能访问外网</li>
            <li><strong>防火墙设置:</strong> 确保出站HTTP/HTTPS请求不被阻止</li>
            <li><strong>DNS解析:</strong> 测试域名解析是否正常</li>
            <li><strong>代理设置:</strong> 检查是否有代理配置影响请求</li>
        </ul>

        <h3>🔧 快速操作</h3>
        <a href="?" class="btn">🔄 重新测试</a>
        <a href="index.html" class="btn">🏠 返回主页</a>
        <a href="get_ip_async.php" class="btn">⚡ 测试异步接口</a>
        
        <h3>📋 命令行测试</h3>
        <p>您也可以在服务器上直接运行以下命令测试:</p>
        <pre>
# 测试主要API
curl -v --max-time 5 "http://ip-api.com/json/<?= $testIP ?>?lang=zh-CN&fields=status,message,country,regionName,city,isp,as,query"

# 测试备用API  
curl -v --max-time 5 "https://ipapi.co/<?= $testIP ?>/json/"

# 测试网络连通性
ping -c 4 ip-api.com
ping -c 4 ipapi.co
        </pre>
    </div>
</body>
</html>
