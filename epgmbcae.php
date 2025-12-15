<?php

// 設定時區以確保時間處理正確
date_default_timezone_set('Asia/Shanghai');
$fp="epgmbcae.xml";//压缩版
/**
 * 抓取指定 URL 的 EPG 資料
 * @param string $url 節目表網址
 * @param string $channel_name 頻道名稱
 * @return array 節目資料陣列
 */
function scrape_epg_data(string $url, string $channel_name): array
{
    // 確保 SimpleXMLElement 類別可用
    if (!class_exists('SimpleXMLElement')) {
        return ["error" => "需要 SimpleXML 擴展"];
    }
    
    $html = @file_get_contents($url);
    if ($html === false) {
        return ["error" => "無法獲取 URL: $url"];
    }

    $dom = new DOMDocument();
    // 抑制 HTML 載入時的警告和錯誤
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $programs = [];
    $current_date_str = '';
    $current_year = date('Y');

    // 找到所有節目容器
    // 節目容器是 div.boxed-category-1
    $program_containers = $xpath->query("//div[contains(@class, 'boxed-category-1')]");

    foreach ($program_containers as $program_container) {
        // 向上尋找最近的日期標題
        $current_node = $program_container->parentNode;
        $date_node = null;
        
        // 向上遍歷直到找到包含日期標題的同級元素
        // 這裡我們假設節目是按時間順序排列的，並且日期標題會出現在它所屬節目的前面。
        // 由於直接從 $program_container 向上找日期節點失敗，我們改為從整個 HTML 結構中，
        // 找到所有日期節點和節目節點，然後按順序處理。
        
        // 重新分析 HTML 結構，發現日期標題是 div.columns.small-12 內包含 div.dates
        // 節目容器是 div.columns.small-12 內包含 div.boxed-category-1
        
        // 為了確保日期正確，我們需要遍歷所有頂層子節點
        $all_tvgrid_children = $xpath->query("//div[contains(@class, 'row') and contains(@class, 'tvgrid')]/*");
        
        foreach ($all_tvgrid_children as $child_node) {
            // 檢查是否為日期標題
            $date_node = $xpath->query("./div[contains(@class, 'dates')]", $child_node)->item(0);
            if ($date_node) {
                $current_date_str = trim($date_node->textContent);
                // 檢查是否跨年
                if (strpos($current_date_str, 'January') !== false && date('n') == 12) {
                    $current_year = date('Y') + 1;
                } else {
                    $current_year = date('Y');
                }
                continue;
            }
            
            // 檢查是否為節目容器
            $program_container = $xpath->query("./div[contains(@class, 'boxed-category-1')]", $child_node)->item(0);
            
            if ($program_container) {
                // 節目資訊都在 $program_container 內部的 div.row 下
                $program_row = $xpath->query("./div[contains(@class, 'row')]", $program_container)->item(0);
                if (!$program_row) continue;

                // 1. 提取時間和時長 (在第一個 columns small-3 large-2 內)
                $time_duration_col = $xpath->query("./div[contains(@class, 'large-2')]", $program_row)->item(0);
                $time_str = '';
                $duration_minutes = 0;
                if ($time_duration_col) {
                    $time_node = $xpath->query("./ul/li[1]", $time_duration_col)->item(0);
                    $time_str = $time_node ? trim($time_node->textContent) : '';
                    
                    $duration_node = $xpath->query("./ul/li[2]/span[contains(@class, 'subheader')]", $time_duration_col)->item(0);
                    $duration_str = $duration_node ? trim($duration_node->textContent) : '';
                    if (preg_match('/\[(\d+)\s+minutes\]/i', $duration_str, $matches)) {
                        $duration_minutes = (int)$matches[1];
                    }
                }

                // 2. 提取標題和類型/年份 (在第三個 columns small-6 large-3 內)
                $title_type_col = $xpath->query("./div[contains(@class, 'large-3')]", $program_row)->item(0);
                $title = '';
                $type = '';
                $year = '';
                if ($title_type_col) {
                    $title_link = $xpath->query("./ul/li[1]/a", $title_type_col)->item(0);
                    $title = $title_link ? trim($title_link->textContent) : '';
                    
                    $type_year_node = $xpath->query("./ul/li[2]", $title_type_col)->item(0);
                    $type_year_str = $type_year_node ? trim($type_year_node->textContent) : '';
                    
                    if (preg_match('/(Movie|Program)\s+\((\d{4})\)/i', $type_year_str, $matches)) {
                        $type = $matches[1];
                        $year = $matches[2];
                    } else if (preg_match('/(Movie|Program)/i', $type_year_str, $matches)) {
                        $type = $matches[1];
                    }
                }

                // 3. 提取評分、描述和演員 (在第四個 columns small-12 large-6 內)
                $details_col = $xpath->query("./div[contains(@class, 'large-6')]", $program_row)->item(0);
                $rating = '';
                $description = '';
                $cast = [];
                if ($details_col) {
                    // 評分
                    $rating_node = $xpath->query("./div[contains(@class, 'rating')]/span", $details_col)->item(0);
                    $rating = $rating_node ? trim($rating_node->textContent) : '';

                    // 描述
                    $description_node = $xpath->query("./ul/li[1]", $details_col)->item(0);
                    $description = $description_node ? trim(str_replace('...Read more', '', $description_node->textContent)) : '';
                    
                    // 演員/主持人
                    $cast_nodes = $xpath->query("./ul/li[2]/a", $details_col);
                    foreach ($cast_nodes as $cast_node) {
                        $cast[] = trim($cast_node->textContent);
                    }
                }

                // 組合日期和時間
                $start_time = '';
                $stop_time = '';
                if ($current_date_str && $time_str) {
                    // 嘗試解析日期時間，例如 "Monday 15 December 2025 12:30 PM"
                    $datetime_str = "$current_date_str $current_year $time_str";
                    $timestamp = strtotime($datetime_str);
                    
                    // 如果解析失敗，嘗試將年份設為下一年（跨年情況）
                    if ($timestamp === false) {
                        $current_year_next = date('Y') + 1;
                        $datetime_str_next = "$current_date_str $current_year_next $time_str";
                        $timestamp = strtotime($datetime_str_next);
                    }
                    
                    // 格式化為 XMLTV 要求的 YYYYMMDDHHMMSS +0000 格式
                    $start_time = $timestamp ? date('YmdHis', $timestamp) . ' +0000' : '';
                    
                    // 計算結束時間
                    if ($timestamp && $duration_minutes > 0) {
                        $end_timestamp = $timestamp + ($duration_minutes * 60);
                        $stop_time = date('YmdHis', $end_timestamp) . ' +0000';
                    }
                }
                
                if ($title) {
                    $programs[] = [
                        'channel' => $channel_name,
                        'start' => $start_time,
                        'stop' => $stop_time,
                        'title' => $title,
                        'type' => $type,
                        'year' => $year,
                        'duration_minutes' => $duration_minutes,
                        'rating' => $rating,
                        'description' => $description,
                        'cast' => $cast,
                    ];
                }
            }
        }
    }

    return $programs;
}

/**
 * 將節目資料陣列轉換為 XMLTV 格式的 XML 字串
 * @param array $all_programs 包含所有頻道節目資料的陣列
 * @return string XML 字串
 */
function generate_xml(array $all_programs): string
{
    // 建立 XML 根節點
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tv generator-info-name="Manus EPG Scraper"></tv>');
    
    // 儲存已添加的頻道，避免重複
    $added_channels = [];

    // 1. 建立 <channel> 標籤
    foreach ($all_programs as $program) {
        $channel_name = $program['channel'];
        if (!in_array($channel_name, $added_channels)) {
            $channel = $xml->addChild('channel');
            $channel_id = str_replace(' ', '.', $channel_name);
            $channel->addAttribute('id', $channel_id);
            $display_name = $channel->addChild('display-name', $channel_name);
            $display_name->addAttribute('lang', 'en');
            $added_channels[] = $channel_name;
        }
    }

    // 2. 建立 <programme> 標籤
    foreach ($all_programs as $program) {
        // 確保有開始時間和標題才輸出
        if (empty($program['start']) || empty($program['title'])) {
            continue;
        }
        
        $programme = $xml->addChild('programme');
        $programme->addAttribute('start', $program['start']);
        $programme->addAttribute('stop', $program['stop']);
        $programme->addAttribute('channel', str_replace(' ', '.', $program['channel']));

        // 標題
        $title = $programme->addChild('title', $program['title']);
        $title->addAttribute('lang', 'en');

        // 描述
        if (!empty($program['description'])) {
            // 清理描述中的 HTML 標籤和不必要的換行
            $clean_desc = preg_replace('/\s+/', ' ', strip_tags($program['description']));
            $desc = $programme->addChild('desc', $clean_desc);
            $desc->addAttribute('lang', 'en');
        }

        // 類別 (Type)
        if (!empty($program['type'])) {
            $programme->addChild('category', $program['type']);
        }
        
        // 年份
        if (!empty($program['year'])) {
            $programme->addChild('date', $program['year']);
        }

        // 評分
        if (!empty($program['rating'])) {
            $programme->addChild('star-rating')->addChild('value', $program['rating']);
        }

        // 演員/主持人
        if (!empty($program['cast'])) {
            $credits = $programme->addChild('credits');
            foreach ($program['cast'] as $person) {
                // 由於無法確定是演員還是導演，統一使用 actor
                $credits->addChild('actor', $person);
            }
        }
    }

    // 格式化輸出
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    // 由於 SimpleXMLElement 的 asXML() 可能會丟失格式化，我們使用 DOMDocument 重新載入
    $xml_string = $xml->asXML();
    if ($xml_string) {
        $dom->loadXML($xml_string);
        return $dom->saveXML();
    }
    return '';
}

// --- 主要執行區塊 ---

$urls = [
    'mbc 2' => 'https://elcinema.com/en/tvguide/1128/',
    'mbc max' => 'https://elcinema.com/en/tvguide/1132/',
];

$all_programs = [];
foreach ($urls as $channel_name => $url) {
    $data = scrape_epg_data($url, $channel_name);
    if (isset($data['error'])) {
        // 輸出錯誤訊息到標準錯誤，不影響 XML 輸出
        file_put_contents('php://stderr', "錯誤: " . $data['error'] . "\n");
        // 繼續處理下一個 URL
        continue;
    }
    $all_programs = array_merge($all_programs, $data);
}

// 輸出 XML
$xml = generate_xml($all_programs);


//echo $xml;
file_put_contents($fp, $xml);


//輸出 XML
// $xml->saveXML();
// 程式碼結束$xml->saveXML();

// 同時保存到文件
//$outputFile = 'epgmbcae1.xml';
//$xml->save($outputFile);
//echo "\n\nEPG XML 已保存到: $outputFile\n";
//echo "共提取 " . count($programs) . " 個節目\n";



?>
