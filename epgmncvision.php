<?php
ini_set("max_execution_time", 300);
ini_set('date.timezone', 'Asia/Shanghai');
$fp = "epgmncvision.xml";

header('Content-Type: text/plain;charset=UTF-8');

// 日期设置
$dt1 = date('Y-m-d');
$dt2 = date('Y-m-d', time() + 24 * 3600);
$dt11 = date('Ymd');
$dt12 = date('Ymd', time() + 24 * 3600);

// XML 头部
$chn = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!DOCTYPE tv SYSTEM \"http://api.torrent-tv.ru/xmltv.dtd\">\n<tv generator-info-name=\"印尼mncvision有線電視\" generator-info-url=\"http://www.lotustv.cc/\">\n";

// 获取频道列表
function fetchChannels() {
    $url = 'https://www.mncvision.id/schedule/table';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => FALSE,
        CURLOPT_SSL_VERIFYHOST => FALSE,
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) {
        return [];
    }
    
    preg_match_all('/<option value="([^"]+)">([^<]+)<\/option>/', $response, $matches, PREG_SET_ORDER);
    $channels = [];
    
    foreach ($matches as $match) {
        $value = $match[1];
        $fullText = trim($match[2]);
        $channelName = preg_replace('/\s*-\s*\[Channel\s*\d+\]\s*$/', '', $fullText);
        
        $channels[] = [
            'value' => $value,
            'name' => $channelName
        ];
    }
    
    return $channels;
}

// 获取节目表
function fetchSchedule($channelValue, $date) {
    $url = 'https://www.mncvision.id/schedule/table';
    $data = [
        "search_model" => 'channel',
        "af0rmelement" => 'aformelement',
        "fdate" => $date,
        "fchannel" => $channelValue,
        "submit" => 'Cari',
    ];
    
    $headers = [
        'Referer: https://www.mncvision.id/schedule/table',
        'Origin: https://www.mncvision.id',
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => FALSE,
        CURLOPT_SSL_VERIFYHOST => FALSE,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) {
        return ['times' => [], 'programs' => []];
    }
    
    preg_match_all('|<td class="text-center">(.*?)<\/td>|i', $response, $times, PREG_SET_ORDER);
    preg_match_all('|title="(.*?)" rel|i', $response, $programs, PREG_SET_ORDER);
    
    return [
        'times' => $times,
        'programs' => $programs
    ];
}

// 生成节目XML
function generateProgramXML($datePrefix, $times, $programs, $channelName) {
    $xml = '';
    $programCount = count($programs);
    
    for ($i = 0; $i < $programCount - 1; $i++) {
        $startTime = $datePrefix . str_replace(':', '', $times[$i * 2][1]) . '00 +0700';
        $stopTime = $datePrefix . str_replace(':', '', $times[($i + 1) * 2][1]) . '00 +0700';
        
        $xml .= "<programme start=\"{$startTime}\" stop=\"{$stopTime}\" channel=\"{$channelName}\">\n";
        $xml .= "<title lang=\"zh\">" . htmlspecialchars($programs[$i][1]) . "</title>\n";
        $xml .= "<desc lang=\"zh\"> </desc>\n";
        $xml .= "</programme>\n";
    }
    
    return $xml;
}

// 主程序
try {
    // 获取频道列表
    $channels = fetchChannels();
    
    if (empty($channels)) {
        throw new Exception("无法获取频道列表");
    }
    
    // 生成频道XML
    foreach ($channels as $channel) {
        $chn .= "<channel id=\"{$channel['name']}\">\n";
        $chn .= "<display-name lang=\"zh\">{$channel['name']}</display-name>\n";
        $chn .= "</channel>\n";
    }
    
    // 为每个频道获取节目表
    $totalChannels = count($channels);
    $processed = 0;
    
    foreach ($channels as $channel) {
        $processed++;
        
        // 获取第一天的节目
        $schedule1 = fetchSchedule($channel['value'], $dt1);
        if (!empty($schedule1['programs'])) {
            $chn .= generateProgramXML($dt11, $schedule1['times'], $schedule1['programs'], $channel['name']);
        }
        
        // 获取第二天的节目
        $schedule2 = fetchSchedule($channel['value'], $dt2);
        if (!empty($schedule2['programs'])) {
            $chn .= generateProgramXML($dt12, $schedule2['times'], $schedule2['programs'], $channel['name']);
        }
        
        // 添加延迟避免请求过快
        if ($processed < $totalChannels) {
            sleep(1);
        }
    }
    
    // 完成XML
    $chn .= "</tv>\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}

// 可选：保存到文件
if (!empty($chn)) {
    // 移除可能的BOM字符
    $chn = preg_replace('/^\xEF\xBB\xBF/', '', $chn);
    
    $result = file_put_contents($fp, $chn);
    if ($result === false) {
        error_log("Failed to write XML file");
    } else {
        echo "XML file generated successfully: " . $fp . " (Size: " . $result . " bytes)\n";
        
        // 验证XML格式
        $xml = simplexml_load_string($chn);
        if ($xml === false) {
            echo "Warning: Generated XML may have formatting issues\n";
            // 输出前几个错误
            libxml_use_internal_errors(true);
            $errors = libxml_get_errors();
            if (!empty($errors)) {
                foreach (array_slice($errors, 0, 5) as $error) {
                    echo "XML Error: " . $error->message . "\n";
                }
            }
        } else {
            echo "XML format validation: PASSED\n";
        }
    }
} else {
    error_log("No XML content to save");
}
?>
