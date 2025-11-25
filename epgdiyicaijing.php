<?php
// 错误报告开启以便调试
header('Content-Type: text/xml;charset=UTF-8');
ini_set("max_execution_time", "300");
ini_set('date.timezone','Asia/Shanghai');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定义常量
define('REQUEST_TIMEOUT', 10);
define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
define('OUTPUT_FILENAME', 'epgdiyicaijing.xml');

// 手动生成 XML 的函数
function generateXML($channels, $programs) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<tv generator-info-name="EPG Filter Script" source-info-name="Filtered EPG">' . "\n";
    
    // 添加频道
    foreach ($channels as $channel) {
        $channelName = $channel[1];
        $xml .= "  <channel id=\"{$channelName}\">\n";
        $xml .= "    <display-name lang=\"zh\">{$channelName}</display-name>\n";
        $xml .= "  </channel>\n";
    }
    
    // 添加节目
    foreach ($programs as $program) {
        $startTime = date('YmdHis', $program['start_time']) . ' +0800';
        $endTime = date('YmdHis', $program['end_time']) . ' +0800';
        $channelName = $program['channel_name'];
        $title = htmlspecialchars($program['name'], ENT_XML1, 'UTF-8');
        
        $xml .= "  <programme start=\"{$startTime}\" stop=\"{$endTime}\" channel=\"{$channelName}\">\n";
        $xml .= "    <title lang=\"zh\">{$title}</title>\n";
        $xml .= "    <desc lang=\"zh\">{$title}</desc>\n";
        $xml .= "  </programme>\n";
    }
    
    $xml .= '</tv>';
    return $xml;
}

// 格式化 XML 的函数（如果 DOM 扩展可用）
function formatXML($xmlString) {
    if (extension_loaded('dom')) {
        try {
            $dom = new DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xmlString);
            return $dom->saveXML();
        } catch (Exception $e) {
            // 如果格式化失败，返回原始 XML
            return $xmlString;
        }
    }
    return $xmlString;
}

try {
    // 频道列表
    $channels = [
        ['105', '第一财经'],
        ['104', '东方财经']
    ];
    
    $weekday = date('N');
    $allPrograms = [];
    
    // 为每个频道获取节目信息
    foreach ($channels as $channel) {
        $channelId = $channel[0];
        $channelName = $channel[1];
        
        $url = "https://vmsapi.yicai.com/epg/api/tv_program?channel={$channelId}&days={$weekday}";
        
        // 初始化cURL
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => REQUEST_TIMEOUT,
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_FAILONERROR => true
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("HTTP Error for channel {$channelId}: {$httpCode} - {$error}");
            continue;
        }
        
        if (empty($response)) {
            error_log("Empty response for channel {$channelId}");
            continue;
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error for channel {$channelId}: " . json_last_error_msg());
            continue;
        }
        
        if (!isset($data['epg_data']) || !is_array($data['epg_data'])) {
            error_log("No epg_data found for channel {$channelId}");
            continue;
        }
        
        // 处理节目信息
        foreach ($data['epg_data'] as $program) {
            if (!isset($program['start_time']) || !isset($program['end_time']) || !isset($program['name'])) {
                continue;
            }
            
            $allPrograms[] = [
                'start_time' => $program['start_time'],
                'end_time' => $program['end_time'],
                'name' => $program['name'],
                'channel_name' => $channelName
            ];
        }
    }
    
    // 生成 XML
    $xmlContent = generateXML($channels, $allPrograms);
    
    // 尝试格式化 XML（如果 DOM 扩展不可用，会返回原始 XML）
    $formattedXML = formatXML($xmlContent);
    
    // 保存到文件
    if (file_put_contents(OUTPUT_FILENAME, $formattedXML) === false) {
        error_log("Failed to save XML file");
    }
    
    // 输出 XML
    header('Content-Length: ' . strlen($formattedXML));
    echo $formattedXML;
    
} catch (Exception $e) {
    // 错误时返回简单的 XML 错误信息
    $errorXML = '<?xml version="1.0" encoding="UTF-8"?><error>' . htmlspecialchars($e->getMessage()) . '</error>';
    header('Content-Length: ' . strlen($errorXML));
    echo $errorXML;
}
?>
