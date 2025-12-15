<?php
// 設置時區以正確處理時間
date_default_timezone_set('Asia/Shanghai');

// 創建 XML 文檔
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$tv = $xml->createElement('tv');
$tv->setAttribute('source-info-url', 'https://elcinema.com/en/tvguide/');
$tv->setAttribute('source-info-name', 'elCinema.com');
$tv->setAttribute('generator-info-name', 'Manus EPG Generator');
$xml->appendChild($tv);

// 頻道資訊陣列 - 三個頻道
$channels = [
   // ['1130', 'mbc action'],
    ['1132', 'mbc max'],
    ['1128', 'mbc 2'],
];

// 先添加所有频道定义
foreach ($channels as $channel) {
    $channelId = htmlspecialchars($channel[1]);
    $channelName = htmlspecialchars($channel[1]);
    
    $channelElem = $xml->createElement('channel');
    $channelElem->setAttribute('id', $channelId);
    $tv->appendChild($channelElem);
    
    $displayName = $xml->createElement('display-name', $channelName);
    $displayName->setAttribute('lang', 'zh');
    $channelElem->appendChild($displayName);
}

// 处理每个频道的节目表
foreach ($channels as $channel) {
    $channelId = isset($channel[0]) ? $channel[0] : '';
    $channelName = isset($channel[1]) ? $channel[1] : '';
    
    $url = 'https://elcinema.com/en/tvguide/' . $channelId . '/';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $html_content = curl_exec($ch);
    if (curl_error($ch)) {
        error_log('cURL Error for channel ' . $channelName . ': ' . curl_error($ch));
        curl_close($ch);
        continue; // 跳过这个频道，继续处理下一个
    }
    curl_close($ch);

    // 創建 DOMDocument 和 DOMXPath
    $dom = new DOMDocument();
    @$dom->loadHTML($html_content);
    $xpath = new DOMXPath($dom);

    // 提取日期資訊
    $date_node = $xpath->query("//h3[contains(text(), 'December')] | //h3[contains(text(), 'January')] | //h3[contains(text(), 'February')] | //h3[contains(text(), 'March')] | //h3[contains(text(), 'April')] | //h3[contains(text(), 'May')] | //h3[contains(text(), 'June')] | //h3[contains(text(), 'July')] | //h3[contains(text(), 'August')] | //h3[contains(text(), 'September')] | //h3[contains(text(), 'October')] | //h3[contains(text(), 'November')]");

    $current_date_str = '';
    if ($date_node->length > 0) {
        $current_date_str = trim($date_node->item(0)->textContent);
    } else {
        $current_date_str = date('l d F');
    }

    $current_year = date('Y');
    $base_date_timestamp = strtotime($current_date_str . ' ' . $current_year);
    if ($base_date_timestamp === false) {
        $base_date_timestamp = time();
    }

    // 提取節目數據
    $programs = [];
    $program_nodes = $xpath->query("//div[contains(@class, 'boxed-category-1')]");

    $last_stop_time = null;
    $current_date_for_channel = $base_date_timestamp; // 每个频道独立的日期跟踪

    foreach ($program_nodes as $node) {
        $program = [];

        // 1. 提取開始時間 (Start Time)
        $time_node = $xpath->query(".//div[contains(@class, 'small-3') and contains(@class, 'large-2')]/ul/li[1]", $node);
        $duration_node = $xpath->query(".//div[contains(@class, 'small-3') and contains(@class, 'large-2')]/ul/li[2]/span", $node);
        
        $start_time_str = $time_node->length > 0 ? trim($time_node->item(0)->textContent) : null;
        $duration_str = $duration_node->length > 0 ? trim($duration_node->item(0)->textContent) : null;

        if (!$start_time_str) continue;

        // 處理時間和日期
        $start_datetime_str = date('Y-m-d', $current_date_for_channel) . ' ' . $start_time_str;
        $start_timestamp = strtotime($start_datetime_str);

        // 處理跨日問題
        if ($last_stop_time !== null && $start_timestamp < $last_stop_time) {
            // 跨日，日期加一天
            $current_date_for_channel = strtotime('+1 day', $current_date_for_channel);
            $start_datetime_str = date('Y-m-d', $current_date_for_channel) . ' ' . $start_time_str;
            $start_timestamp = strtotime($start_datetime_str);
        }

        // 處理持續時間
        $duration_minutes = 0;
        if (preg_match('/\[(\d+)\s+minutes\]/', $duration_str, $matches)) {
            $duration_minutes = (int)$matches[1];
        }
        
        $stop_timestamp = $start_timestamp + ($duration_minutes * 60);
        $last_stop_time = $stop_timestamp;

        // 格式化為 XMLTV 標準時間格式
        $program['start'] = date('YmdHis', $start_timestamp) . ' +0800';
        $program['stop'] = date('YmdHis', $stop_timestamp) . ' +0800';

        // 2. 提取標題 (Title) 和類別 (Category)
        $title_node = $xpath->query(".//div[contains(@class, 'small-6') and contains(@class, 'large-3')]/ul/li[1]/a", $node);
        $category_node = $xpath->query(".//div[contains(@class, 'small-6') and contains(@class, 'large-3')]/ul/li[2]", $node);

        $program['title'] = $title_node->length > 0 ? trim($title_node->item(0)->textContent) : 'N/A';
        $program['category'] = $category_node->length > 0 ? trim($category_node->item(0)->textContent) : 'N/A';

        // 3. 提取描述 (Description)
        $desc_node = $xpath->query(".//div[contains(@class, 'small-12') and contains(@class, 'large-6')]/ul/li[last()]", $node);
        $description = '';
        if ($desc_node->length > 0) {
            $li_text = trim($desc_node->item(0)->firstChild->textContent);
            
            $hidden_span = $xpath->query(".//span[contains(@class, 'hide')]", $desc_node->item(0));
            $hidden_text = $hidden_span->length > 0 ? trim($hidden_span->item(0)->textContent) : '';
            
            $description = str_replace('...Read more', '', $li_text) . $hidden_text;
            $program['desc'] = trim($description);
        } else {
            $program['desc'] = 'No description available.';
        }

        $programs[] = $program;
    }

    // 寫入節目資訊
    foreach ($programs as $program_data) {
        $programme = $xml->createElement('programme');
        $programme->setAttribute('start', $program_data['start']);
        $programme->setAttribute('stop', $program_data['stop']);
        $programme->setAttribute('channel', $channelName);
        $tv->appendChild($programme);

        // 標題
        $title = $xml->createElement('title', htmlspecialchars($program_data['title']));
        $title->setAttribute('lang', 'en');
        $programme->appendChild($title);

        // 描述
        $desc = $xml->createElement('desc', htmlspecialchars($program_data['desc']));
        $desc->setAttribute('lang', 'en');
        $programme->appendChild($desc);

        // 類別
        $category = $xml->createElement('category', htmlspecialchars($program_data['category']));
        $category->setAttribute('lang', 'en');
        $programme->appendChild($category);
    }
}

// 輸出 XML
$xml_output = $xml->saveXML();
file_put_contents('epgmbcae.xml', $xml_output);
//echo $xml_output ;
// 输出成功信息
//echo "EPG XML generated successfully in epg.xml\n";
//echo "Generated EPG for " . count($channels) . " channels:\n";
/*
foreach ($channels as $channel) {
    echo "- " . $channel[1] . " (ID: " . $channel[0] . ")\n";
}
*/
?>