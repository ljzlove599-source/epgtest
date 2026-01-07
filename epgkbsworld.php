<?php
header('Content-Type: text/plain; charset=UTF-8');
ini_set("max_execution_time", "900");
ini_set('date.timezone','Asia/Shanghai');
ini_set('memory_limit', '512M');

// 开启输出缓冲

//header('Content-Type: application/xml; charset=utf-8');

// 清理字符串中的无效XML字符
function clean_xml_string($string) {
    if (empty($string)) {
        return '';
    }
    
    // 移除控制字符和非XML合法字符（除了制表符、换行符和回车符）
    $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);
    
    // 替换XML特殊字符
    $string = str_replace(
        ['&', '<', '>', '"', "'"],
        ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
        $string
    );
    
    return trim($string);
}

function fetch_epg($date) {
    $url = "https://kbsworld.kbs.co.kr/schedule/list.php?lang=e&time_add=0&sdate=" . $date;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $html = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("cURL Error for $date: " . curl_error($ch));
    }
    
    curl_close($ch);

    if (!$html) {
        return null;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    
    // 使用正确的编码处理
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    // 根據觀察到的 HTML 結構更新選擇器
    $items = $xpath->query("//li[contains(@class, 'twice-tab')]");

    $programs = [];
    foreach ($items as $item) {
        $program = [];
        
        // 提取時間 (從 twice-left-tab 獲取)
        $timeNode = $xpath->query(".//div[contains(@class, 'twice-left-tab')]//span[contains(@class, 'time')]", $item);
        $program['time'] = $timeNode->length > 0 ? clean_xml_string(trim($timeNode->item(0)->nodeValue)) : '';

        // 提取類別
        $genreNode = $xpath->query(".//div[contains(@class, 'genre')]", $item);
        $program['category'] = $genreNode->length > 0 ? clean_xml_string(trim($genreNode->item(0)->nodeValue)) : '';

        // 提取標題 (在 twice-right-tab 內的 div.title)
        $titleNode = $xpath->query(".//div[contains(@class, 'twice-right-tab')]//div[contains(@class, 'title')]", $item);
        if ($titleNode->length > 0) {
            $program['title'] = clean_xml_string(trim($titleNode->item(0)->nodeValue));
        } else {
            $program['title'] = '';
        }

        // 提取描述
        $descNode = $xpath->query(".//div[contains(@class, 'twice-right-tab')]//div[contains(@class, 'desc')]", $item);
        $program['description'] = $descNode->length > 0 ? clean_xml_string(trim($descNode->item(0)->nodeValue)) : '';

        if (!empty($program['title'])) {
            $program['date'] = $date; // 添加日期信息
            $programs[] = $program;
        }
    }

    return $programs;
}

// 获取两天的节目数据
$dates = ['2026-01-07', '2026-01-08'];
$all_programs = [];

foreach ($dates as $date) {
    $programs = fetch_epg($date);
    if ($programs) {
        $all_programs = array_merge($all_programs, $programs);
    }
    // 为了避免请求过快，可以添加短暂延迟
    usleep(100000); // 100ms 延迟
}

// 按日期和时间排序节目
usort($all_programs, function($a, $b) {
    $timeA = strtotime($a['date'] . ' ' . $a['time']);
    $timeB = strtotime($b['date'] . ' ' . $b['time']);
    return $timeA - $timeB;
});

// 计算结束时间
function calculate_end_times($programs) {
    $programs_with_end = [];
    $count = count($programs);
    
    for ($i = 0; $i < $count; $i++) {
        $current_program = $programs[$i];
        
        // 计算开始时间戳
        $start_datetime = $current_program['date'] . ' ' . $current_program['time'];
        $start_timestamp = strtotime($start_datetime);
        
        // 计算结束时间
        if ($i < $count - 1) {
            // 下一个节目的开始时间就是当前节目的结束时间
            $next_program = $programs[$i + 1];
            $next_start_datetime = $next_program['date'] . ' ' . $next_program['time'];
            $end_timestamp = strtotime($next_start_datetime);
            
            // 确保结束时间晚于开始时间（正常情况下应该如此）
            if ($end_timestamp <= $start_timestamp) {
                // 如果结束时间不晚于开始时间，则假设节目时长为30分钟
                $end_timestamp = $start_timestamp + (30 * 60);
            }
        } else {
            // 最后一个节目（第二天的最后一个节目），设置结束时间为第二天的23:59
            $last_program_date = $current_program['date'];
            $end_timestamp = strtotime($last_program_date . ' 23:59:00');
            
            // 确保结束时间晚于开始时间
            if ($end_timestamp <= $start_timestamp) {
                // 如果开始时间晚于23:59，则设置结束时间为开始时间+30分钟
                $end_timestamp = $start_timestamp + (30 * 60);
            }
        }
        
        // 将开始和结束时间转换为所需的格式
        $current_program['start_timestamp'] = $start_timestamp;
        $current_program['end_timestamp'] = $end_timestamp;
        
        $programs_with_end[] = $current_program;
    }
    
    return $programs_with_end;
}

// 计算结束时间
$all_programs = calculate_end_times($all_programs);

// 建立 XML 输出
try {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tv/>');
    $xml->addAttribute('generator-info-name', 'KBS World EPG Scraper');
    $xml->addAttribute('generator-info-url', 'https://kbsworld.kbs.co.kr/');

    $channel = $xml->addChild('channel');
    $channel->addAttribute('id', 'KBSWorld');
    $channel->addChild('display-name', 'KBS World');
    $channel->addChild('display-name', 'KBS World')->addAttribute('lang', 'en');

    if ($all_programs) {
        foreach ($all_programs as $p) {
            $programme = $xml->addChild('programme');
            $programme->addAttribute('channel', 'KBSWorld');
            
            // 设置开始时间
            $startTime = date('YmdHis', $p['start_timestamp']) . ' +0900';
            $programme->addAttribute('start', $startTime);
            
            // 设置结束时间
            $endTime = date('YmdHis', $p['end_timestamp']) . ' +0900';
            $programme->addAttribute('stop', $endTime);
            
            $programme->addChild('title', $p['title'])->addAttribute('lang', 'en');
            if (!empty($p['description'])) {
                $programme->addChild('desc', $p['description'])->addAttribute('lang', 'en');
            }
            if (!empty($p['category'])) {
                $programme->addChild('category', $p['category'])->addAttribute('lang', 'en');
            }
        }
    }
    
    // 输出XML
    $xml_string = $xml->asXML();
    
    // 清理输出缓冲
    ob_clean();
    
    /*
输出更格式化的XML
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml_string);
*/
  file_put_contents('epgkbsworld.xml', $xml_string);
    
} catch (Exception $e) {
    // 如果XML生成失败，输出错误信息
    ob_clean();
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<error>';
    echo '<message>' . htmlspecialchars($e->getMessage()) . '</message>';
    echo '<file>' . htmlspecialchars($e->getFile()) . '</file>';
    echo '<line>' . htmlspecialchars($e->getLine()) . '</line>';
    echo '</error>';
}
