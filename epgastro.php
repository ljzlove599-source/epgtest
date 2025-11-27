<?php
//127.0.0.1/epgastro1.php
ini_set("max_execution_time", "3000000");
ini_set('date.timezone','Asia/Shanghai');
$fp="epgastro.xml";

function compress_html($string) {
    $string = str_replace("\r", '', $string);
    $string = str_replace("\n", '', $string);
    $string = str_replace("\t", '', $string);
    return $string;
}

// XML清理函数
function clean_xml_content($content) {
    // 移除控制字符（除了制表符、换行符和回车符）
    $content = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $content);
    
    // 处理XML特殊字符
    $content = htmlspecialchars($content, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    
    // 移除连续的空格
    $content = preg_replace('/\s+/', ' ', $content);
    
    return trim($content);
}

header('Content-Type: text/plain;charset=UTF-8');

$chn = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!DOCTYPE tv SYSTEM \"http://api.torrent-tv.ru/xmltv.dtd\">\n<tv generator-info-name=\"秋哥綜合\" generator-info-url=\"https://www.tdm.com.mo/c_tv/?ch=Satellite\">\n";

$dt1 = date('Ymd');
$dt14 = date('Y-m-d');
$dt2 = date('Ymd', time() + 24 * 3600);
$dt3 = date('Ymd', time() + 6 * 24 * 3600);
$w1 = date("w");
if ($w1 < '1') {
    $w1 = 7;
}
$w2 = $w1 + 1;
$dt11 = date('Y-m-d');
$dt12 = date('Y-m-d', time() + 24 * 3600);

$url22='https://contenthub-api.eco.astro.com.my/api/v2/search-linear?channelGuide=true&platform=acm&channelOrderBy=stbNumber,asc';
$ch22 = curl_init();
curl_setopt($ch22, CURLOPT_URL, $url22);
curl_setopt($ch22, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch22, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch22, CURLOPT_SSL_VERIFYHOST, FALSE);
curl_setopt($ch22, CURLOPT_ENCODING, '');
curl_setopt($ch22, CURLOPT_TIMEOUT, 10);
curl_setopt($ch22, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

$re22 = curl_exec($ch22);
$httpCode = curl_getinfo($ch22, CURLINFO_HTTP_CODE);
curl_close($ch22);

// 检查第一个API请求是否成功
if ($httpCode !== 200 || empty($re22)) {
    error_log("Failed to fetch channel list, HTTP Code: " . $httpCode);
    die("Failed to fetch channel data");
}

$channelsData = json_decode($re22);
if (json_last_error() !== JSON_ERROR_NONE || !isset($channelsData->response->channels->data)) {
    error_log("JSON decode error for channel list: " . json_last_error_msg());
    die("Invalid channel data format");
}

$channels = $channelsData->response->channels->data;
$channelCount = count($channels);

// 创建频道列表
for ($k22 = 0; $k22 < $channelCount; $k22++) {
    $id = $channels[$k22]->id;
    $title = clean_xml_content($channels[$k22]->title);
    
    // 确保频道ID是有效的XML名称
    $channel_id = preg_replace('/[^a-zA-Z0-9._-]/', '_', $title);
    $channel_id = substr($channel_id, 0, 50); // 限制长度
    
    $chn .= "<channel id=\"" . $channel_id . "\">\n";
    $chn .= "  <display-name lang=\"zh\">" . $title . "</display-name>\n";
    $chn .= "</channel>\n";
}

// 获取每个频道的节目表
for ($k22 = 0; $k22 < $channelCount; $k22++) {
    $id = $channels[$k22]->id;
    $title = clean_xml_content($channels[$k22]->title);
    $channel_id = preg_replace('/[^a-zA-Z0-9._-]/', '_', $title);
    $channel_id = substr($channel_id, 0, 50);
    
    $url221 = "https://contenthub-api.eco.astro.com.my/channel/$id.json";

    $ch221 = curl_init();
    curl_setopt($ch221, CURLOPT_URL, $url221);
    curl_setopt($ch221, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch221, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch221, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch221, CURLOPT_ENCODING, '');
    curl_setopt($ch221, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch221, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $re221 = curl_exec($ch221);
    $httpCode = curl_getinfo($ch221, CURLINFO_HTTP_CODE);
    curl_close($ch221);
    
    // 检查请求是否成功
    if ($httpCode !== 200 || empty($re221)) {
        error_log("Failed to fetch data for channel " . $title . " (ID: " . $id . "), HTTP Code: " . $httpCode);
        continue;
    }
    
    $data = json_decode($re221);
    
    // 检查JSON解码是否成功
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error for channel " . $title . ": " . json_last_error_msg());
        continue;
    }
    
    // 检查数据结构是否存在
    if (!isset($data->response) || !isset($data->response->schedule)) {
        error_log("Invalid data structure for channel " . $title);
        continue;
    }
    
    $schedule221 = $data->response->schedule;
    
    // 处理今天的节目
    if (isset($schedule221->$dt11) && is_array($schedule221->$dt11)) {
        $tyuu221 = $schedule221->$dt11;
        $trm221 = count($tyuu221);
        
        for ($k221 = 0; $k221 < $trm221 - 1; $k221++) {
            if (isset($tyuu221[$k221]) && isset($tyuu221[$k221 + 1])) {
                $title221 = clean_xml_content($tyuu221[$k221]->title ?? '未知节目');
                $starttime221 = $tyuu221[$k221]->datetime ?? '';
                $endtime221 = $tyuu221[$k221 + 1]->datetime ?? '';
                
                if (!empty($starttime221) && !empty($endtime221)) {
                    $start = str_replace([':', '-', ' ', '.0'], '', $starttime221);
                    $stop = str_replace([':', '-', ' ', '.0'], '', $endtime221);
                    
                    // 验证时间格式
                    if (preg_match('/^\d{14}$/', $start) && preg_match('/^\d{14}$/', $stop)) {
                        $chn .= "<programme start=\"{$start} +0800\" stop=\"{$stop} +0800\" channel=\"{$channel_id}\">\n";
                        $chn .= "  <title lang=\"zh\">" . $title221 . "</title>\n";
                        $chn .= "  <desc lang=\"zh\"> </desc>\n";
                        $chn .= "</programme>\n";
                    }
                }
            }
        }
    }
    
    // 处理明天的节目
    if (isset($schedule221->$dt12) && is_array($schedule221->$dt12)) {
        $tyuu2211 = $schedule221->$dt12;
        $trm2211 = count($tyuu2211);
        
        for ($k2211 = 0; $k2211 < $trm2211 - 1; $k2211++) {
            if (isset($tyuu2211[$k2211]) && isset($tyuu2211[$k2211 + 1])) {
                $title2211 = clean_xml_content($tyuu2211[$k2211]->title ?? '未知节目');
                $starttime2211 = $tyuu2211[$k2211]->datetime ?? '';
                $endtime2211 = $tyuu2211[$k2211 + 1]->datetime ?? '';
                
                if (!empty($starttime2211) && !empty($endtime2211)) {
                    $start = str_replace([':', '-', ' ', '.0'], '', $starttime2211);
                    $stop = str_replace([':', '-', ' ', '.0'], '', $endtime2211);
                    
                    // 验证时间格式
                    if (preg_match('/^\d{14}$/', $start) && preg_match('/^\d{14}$/', $stop)) {
                        $chn .= "<programme start=\"{$start} +0800\" stop=\"{$stop} +0800\" channel=\"{$channel_id}\">\n";
                        $chn .= "  <title lang=\"zh\">" . $title2211 . "</title>\n";
                        $chn .= "  <desc lang=\"zh\"> </desc>\n";
                        $chn .= "</programme>\n";
                    }
                }
            }
        }
    }
}

$chn .= "</tv>\n";

// 保存到文件前进行最终验证
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

//echo $chn;
?>
