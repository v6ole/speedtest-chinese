<?php
// 获取用户IP和基本信息
function getUserIP() {
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        return $_SERVER['REMOTE_ADDR'];
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ip_list[0]);
    }
    if (isset($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    return $_SERVER['REMOTE_ADDR'];
}

function translateIsp($isp_en) {
    $translations = [
        'China Telecom' => '中国电信',
        'Chinanet' => '中国电信',
        'China Unicom' => '中国联通',
        'China Mobile' => '中国移动',
        'Dr. Peng' => '鹏博士',
        'Tietong' => '中国铁通',
        'Alibaba' => '阿里云',
        'Tencent' => '腾讯云',
        'Baidu' => '百度云',
        'China Education and Research Network' => '教育网'
    ];

    foreach ($translations as $en => $cn) {
        if (stripos($isp_en, $en) !== false) {
            return $cn;
        }
    }
    return $isp_en; // 如果没有匹配，返回原始名称
}

function getIpInfo($ip) {
    try {
        // 使用 ip-api.com 查询, 并请求中文结果
        $url = "http://ip-api.com/json/{$ip}?lang=zh-CN&fields=status,message,country,city,isp";
        $response = @file_get_contents($url);
        if ($response === false) {
            return ['未知', '未知'];
        }

        $data = json_decode($response, true);
        if ($data && $data['status'] == 'success') {
            $location = ($data['city'] ? $data['city'] . ', ' : '') . $data['country'];
            $isp = translateIsp($data['isp']);
            return [$location, $isp];
        }
        return ['未知', '未知'];
    } catch (Exception $e) {
        return ['未知', '未知'];
    }
}

$userIP = getUserIP();
// Handle local/private IPs for development
$isPrivateIp = filter_var(
    $userIP,
    FILTER_VALIDATE_IP,
    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
) === false;

if ($userIP === '127.0.0.1' || $isPrivateIp) {
    $userLocation = "本地, 中国";
    $userISP = "本地网络";
} else {
    list($userLocation, $userISP) = getIpInfo($userIP);
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
        }

        .info-title {
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
                    <h3 class="info-title">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20 3H4c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h3l-1 1v2h12v-2l-1-1h3c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM4 14V5h16v9H4z"/>
                        </svg>
                        连接信息
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
                if (status === 1) { // Download test
                    document.getElementById('progress-text').textContent = '正在测试下载速度...';
                    updateProgress(Math.round(data.dlProgress * 100));
                    document.getElementById('download-speed').textContent = data.dlStatus;
                }
                if (status === 2) { // Ping test
                    document.getElementById('progress-text').textContent = '正在测试延迟...';
                    updateProgress(Math.round(data.pingProgress * 100));
                    document.getElementById('ping-value').textContent = data.pingStatus;
                    document.getElementById('jitter-info').textContent = `抖动: ${data.jitterStatus}ms`;
                }
                if (status === 3) { // Upload test
                    document.getElementById('progress-text').textContent = '正在测试上传速度...';
                    updateProgress(Math.round(data.ulProgress * 100));
                    document.getElementById('upload-speed').textContent = data.ulStatus;
                }
            };
            
            s.onend = function(aborted) {
                isTestRunning = false;
                if (aborted) {
                    console.log("测试已中止");
                    document.getElementById('testing-state').classList.add('hidden');
                    document.getElementById('initial-state').classList.remove('hidden');
                    return;
                }
                showResults(testData);
            };

            s.start();
        }

        function updateProgress(progress) {
            document.getElementById('progress-fill').style.width = progress + '%';
            document.getElementById('progress-percent').textContent = progress + '%';
        }

        function showResults(results) {
            const downloadSpeed = parseFloat(results.dlStatus) || 0;
            const uploadSpeed = parseFloat(results.ulStatus) || 0;
            const pingValue = parseFloat(results.pingStatus) || 0;
            const jitterValue = parseFloat(results.jitterStatus) || 0;

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
        }

        function getSpeedLevel(speed) {
            if (speed >= 50) return { level: '优秀', class: 'badge-excellent' };
            if (speed >= 25) return { level: '良好', class: 'badge-good' };
            if (speed >= 10) return { level: '一般', class: 'badge-average' };
            return { level: '较慢', class: 'badge-poor' };
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
    </script>
</body>
</html>