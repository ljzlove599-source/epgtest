<?php

/**
 * EPG Extractor for lifetvprogramme.weebly.com
 * Format: YYYYMMDDHHMMSS +0800
 */

function fetch_html($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

function parse_epg($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $tables = $xpath->query("//table");
    if ($tables->length == 0) return null;

    $table = $tables->item(0);
    $rows = $table->getElementsByTagName('tr');
    if ($rows->length < 2) return null;

    $epg_data = [];
    $dates = [];

    // Extract dates from the second row (index 1)
    $date_cells = $rows->item(1)->getElementsByTagName('td');
    $current_year = 2026;
    
    for ($i = 0; $i < $date_cells->length; $i++) {
        $date_str = trim($date_cells->item($i)->textContent);
        if (preg_match('/(\d+)\/(\d+)/', $date_str, $matches)) {
            $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $dates[] = $current_year . $month . $day;
        }
    }

    // Iterate through program rows starting from index 2
    for ($i = 2; $i < $rows->length; $i++) {
        $cells = $rows->item($i)->getElementsByTagName('td');
        if ($cells->length < 8) continue;

        $time_range = trim($cells->item(0)->textContent);
        
        // 處理時間範圍，將 2400 轉換為 0000
        if (preg_match('/(\d{2}):(\d{2})-(\d{2}):(\d{2})/', $time_range, $time_matches)) {
            $start_hour = intval($time_matches[1]);
            $start_minute = $time_matches[2];
            $end_hour = intval($time_matches[3]);
            $end_minute = $time_matches[4];
            
            // 處理結束時間為 2400 的情況，轉換為 0000
            if ($end_hour == 24) {
                $end_hour = 0;
                $end_hour_str = "00";
            } else {
                $end_hour_str = str_pad($end_hour, 2, '0', STR_PAD_LEFT);
            }
            
            $start_time = str_pad($start_hour, 2, '0', STR_PAD_LEFT) . $start_minute . "00";
            $end_time = $end_hour_str . $end_minute . "00";
            
            for ($j = 1; $j <= 7; $j++) {
                if (isset($dates[$j-1]) && $cells->item($j)) {
                    $program_name = trim($cells->item($j)->textContent);
                    $program_name = preg_replace('/\s+/', ' ', $program_name);
                    
                    if (!empty($program_name)) {
                        $start_date = $dates[$j-1];
                        
                        // 處理跨天情況
                        if ($end_hour < $start_hour || ($end_hour == 0 && $start_hour > 0)) {
                            // 節目跨越到第二天
                            $end_date = date('Ymd', strtotime($start_date . ' +1 day'));
                        } else {
                            $end_date = $start_date;
                        }
                        
                        $epg_data[] = [
                            'start' => $start_date . $start_time . " +0800",
                            'stop' => $end_date . $end_time . " +0800",
                            'title' => $program_name
                        ];
                    }
                }
            }
        }
    }

    return $epg_data;
}

function generate_xml($epg_data) {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tv></tv>');
    $xml->addAttribute('generator-info-name', 'Manus EPG Extractor');
    
    // 添加 channel 元素
    $channel = $xml->addChild('channel');
    $channel->addAttribute('id', '生命電視台');
    $channel->addChild('display-name', '生命電視台');
    
    foreach ($epg_data as $item) {
        $programme = $xml->addChild('programme');
        $programme->addAttribute('start', $item['start']);
        $programme->addAttribute('stop', $item['stop']);
        $programme->addAttribute('channel', '生命電視台');
        
        $title = $programme->addChild('title', htmlspecialchars($item['title']));
        $title->addAttribute('lang', 'zh');
    }

    // Format XML for better readability
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    return $dom->saveXML();
}

// Main execution
//$url = 'https://lifetvprogramme.weebly.com/';
$url = 'https://lifetvprogramme.weebly.com/2--9--2--15-316803044634920.html/';
$html = fetch_html($url);
if ($html) {
    $epg_data = parse_epg($html);
    if ($epg_data) {
        $xml_string = generate_xml($epg_data);
        header('Content-Type: application/xml; charset=utf-8');
        echo $xml_string;
        
        // 保存XML到文件
        file_put_contents('epglifetv.xml', $xml_string);
    } else {
        echo "Error: Could not parse EPG data.";
    }
} else {
    echo "Error: Could not fetch website content.";
}
