<?php

/**
 * Multi-Channel EPG Generator for elcinema.com
 * Supports channels: 1130 (MBC Action), 1132 (MBC Max), 1128 (MBC 2)
 * Handles PM to AM transition for date increment.
 * Special handling for 00:00-06:00 programs (next day)
 * 
 * Pure PHP XML generation without SimpleXMLElement
 */

// 確保沒有任何輸出緩衝干擾
if (ob_get_length()) {
    ob_end_clean();
}

// 設置錯誤處理
error_reporting(0);
ini_set('display_errors', 0);

// 檢查必要的函數
if (!function_exists('curl_init')) {
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: text/plain; charset=utf-8');
    }
    die("錯誤: 需要 PHP 'curl' 擴展。請啟用它然後重試。");
}

// 設置 XML 輸出的 header
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/xml; charset=utf-8');
}

/**
 * 從 URL 獲取 HTML 內容
 */
function fetch_epg($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    $html = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('Curl error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    return $html;
}

/**
 * 從 HTML 解析 EPG 數據
 */
function parse_epg($html) {
    if (empty($html)) {
        return [];
    }
    
    // 使用 DOMDocument（如果可用）或備用方法
    if (class_exists('DOMDocument')) {
        return parse_epg_with_dom($html);
    } else {
        return parse_epg_with_regex($html);
    }
}

/**
 * 使用 DOMDocument 解析 EPG
 */
function parse_epg_with_dom($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    
    // 添加 XML 聲明以處理 UTF-8
    $html = '<?xml encoding="UTF-8">' . $html;
    
    if (!$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
        return [];
    }
    
    $xpath = new DOMXPath($dom);

    // 嘗試從頁面找到基礎日期
    $base_date = new DateTime('now', new DateTimeZone('Asia/Shanghai'));
    $date_header = $xpath->query("//h3[contains(text(), '202')] | //div[contains(@class, 'day-header')]");
    if ($date_header->length > 0) {
        try {
            $date_text = trim($date_header->item(0)->textContent);
            // 嘗試從文本中提取日期（可能格式為 "2025-01-02" 或包含日期的文本）
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $date_text, $matches)) {
                $parsed_date = DateTime::createFromFormat('Y-m-d', $matches[1], new DateTimeZone('Asia/Shanghai'));
                if ($parsed_date) {
                    $base_date = $parsed_date;
                }
            }
        } catch (Exception $e) {
            // 使用當前日期
        }
    }

    $nodes = $xpath->query("//div[contains(@class, 'boxed-category-')]");
    $programs = [];
    $last_time = null;
    $current_date = clone $base_date;

    foreach ($nodes as $node) {
        // 提取標題
        $title = "";
        $title_paths = [
            ".//ul[contains(@class, 'no-margin')]/li[1]",
            ".//div[contains(@class, 'small-7')]//li[1]",
            ".//div[contains(@class, 'small-6')]//li[1]",
            ".//a[contains(@href, '/work/')]"
        ];
        foreach ($title_paths as $path) {
            $t_nodes = $xpath->query($path, $node);
            if ($t_nodes->length > 0) {
                $title = trim($t_nodes->item(0)->textContent);
                if (!empty($title)) break;
            }
        }

        // 提取時間
        $time_str = "";
        $time_paths = [
            ".//ul[contains(@class, 'text-center')]/li[1]",
            ".//ul[contains(@class, 'no-margin')]/li[2]",
            ".//li[contains(text(), 'AM') or contains(text(), 'PM')]"
        ];
        foreach ($time_paths as $path) {
            $tm_nodes = $xpath->query($path, $node);
            foreach ($tm_nodes as $tm_node) {
                if (preg_match('/(\d{1,2}:\d{2}\s*(?:AM|PM))/i', $tm_node->textContent, $matches)) {
                    $time_str = $matches[1];
                    break 2;
                }
            }
        }

        // 提取持續時間
        $duration = 60;
        $dur_nodes = $xpath->query(".//span[contains(@class, 'subheader')]", $node);
        if ($dur_nodes->length > 0) {
            if (preg_match('/(\d+)/', $dur_nodes->item(0)->textContent, $matches)) {
                $duration = (int)$matches[1];
            }
        }

        if (empty($title) || empty($time_str)) continue;

        // 處理時間
        $time_obj = DateTime::createFromFormat('h:i A', $time_str);
        if (!$time_obj) continue;
        
        // 獲取小時和分鐘
        $hour = (int)$time_obj->format('G');
        $minute = (int)$time_obj->format('i');
        
        // 計算當前節目的時間（只包含時間部分）
        $program_time = $hour * 60 + $minute;
        
        // 如果是第一個節目，確定當前日期
        if ($last_time === null) {
            // 第一個節目，確定日期
            if ($hour < 6) {
                // 0-6點的節目屬於第二天
                $program_date = clone $base_date;
                $program_date->modify('+1 day');
            } else {
                // 6-24點的節目屬於當天
                $program_date = clone $base_date;
            }
        } else {
            // 不是第一個節目，檢查是否需要跨天
            if ($program_time < $last_time) {
                // 時間倒流，說明跨天了
                $current_date->modify('+1 day');
            }
            $program_date = clone $current_date;
        }
        
        // 更新上一個節目的時間
        $last_time = $program_time;
        
        // 創建完整的開始時間
        $start_dt = new DateTime($program_date->format('Y-m-d') . ' ' . $time_obj->format('H:i:s'), new DateTimeZone('Asia/Shanghai'));
        $end_dt = clone $start_dt;
        $end_dt->modify("+$duration minutes");

        $programs[] = [
            'title' => $title,
            'start' => $start_dt->format('YmdHis O'),
            'stop' => $end_dt->format('YmdHis O'),
        ];
    }
    return $programs;
}

/**
 * 備用方法：使用正則表達式解析 EPG
 */
function parse_epg_with_regex($html) {
    // 簡化的正則解析
    $programs = [];
    
    // 嘗試提取日期
    $base_date = new DateTime('now', new DateTimeZone('Asia/Shanghai'));
    if (preg_match('/<h3[^>]*>(.*?202\d.*?)<\/h3>/i', $html, $date_match)) {
        $date_text = trim(strip_tags($date_match[1]));
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $date_text, $date_matches)) {
            $parsed_date = DateTime::createFromFormat('Y-m-d', $date_matches[1], new DateTimeZone('Asia/shanghai'));
            if ($parsed_date) {
                $base_date = $parsed_date;
            }
        }
    }
    
    // 查找節目區塊
    preg_match_all('/<div[^>]*class="[^"]*boxed-category-[^"]*"[^>]*>(.*?)<\/div>/si', $html, $blocks);
    
    if (empty($blocks[0])) {
        return $programs;
    }
    
    $last_time = null;
    $current_date = clone $base_date;
    
    foreach ($blocks[0] as $block) {
        // 提取標題
        $title = '';
        if (preg_match('/<a[^>]*href="[^"]*\/work\/[^"]*"[^>]*>(.*?)<\/a>/si', $block, $title_match)) {
            $title = trim(strip_tags($title_match[1]));
        }
        
        // 提取時間
        $time_str = '';
        if (preg_match('/(\d{1,2}:\d{2}\s*(?:AM|PM))/i', $block, $time_match)) {
            $time_str = $time_match[1];
        }
        
        if (empty($title) || empty($time_str)) continue;
        
        // 處理時間
        $time_obj = DateTime::createFromFormat('h:i A', $time_str);
        if (!$time_obj) continue;
        
        $hour = (int)$time_obj->format('G');
        $minute = (int)$time_obj->format('i');
        $program_time = $hour * 60 + $minute;
        
        // 確定日期
        if ($last_time === null) {
            if ($hour < 6) {
                $program_date = clone $base_date;
                $program_date->modify('+1 day');
            } else {
                $program_date = clone $base_date;
            }
        } else {
            if ($program_time < $last_time) {
                $current_date->modify('+1 day');
            }
            $program_date = clone $current_date;
        }
        
        $last_time = $program_time;
        
        $start_dt = new DateTime($program_date->format('Y-m-d') . ' ' . $time_obj->format('H:i:s'), new DateTimeZone('Asia/Shanghai'));
        $end_dt = clone $start_dt;
        $end_dt->modify('+60 minutes');
        
        $programs[] = [
            'title' => $title,
            'start' => $start_dt->format('YmdHis O'),
            'stop' => $end_dt->format('YmdHis O'),
        ];
    }
    
    return $programs;
}

/**
 * 手動生成 XML
 */
function generate_xml($channels_data) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<tv generator-info-name="Manus Multi-Channel EPG Generator" generator-info-url="https://github.com/yourusername/epg-generator">' . "\n";
    
    // 添加頻道信息
    foreach ($channels_data['channels'] as $channel) {
        $xml .= '  <channel id="' . htmlspecialchars($channel['id'], ENT_XML1, 'UTF-8') . '">' . "\n";
        $xml .= '    <display-name lang="en">' . htmlspecialchars($channel['name'], ENT_XML1, 'UTF-8') . '</display-name>' . "\n";
        $xml .= '  </channel>' . "\n";
    }
    
    // 添加節目信息
    foreach ($channels_data['programmes'] as $programme) {
        $xml .= '  <programme start="' . htmlspecialchars($programme['start'], ENT_XML1, 'UTF-8') . '" stop="' . htmlspecialchars($programme['stop'], ENT_XML1, 'UTF-8') . '" channel="' . htmlspecialchars($programme['channel_id'], ENT_XML1, 'UTF-8') . '">' . "\n";
        $xml .= '    <title lang="en">' . htmlspecialchars($programme['title'], ENT_XML1, 'UTF-8') . '</title>' . "\n";
        $xml .= '  </programme>' . "\n";
    }
    
    $xml .= '</tv>';
    
    return $xml;
}

// 主程序
try {
    // 定義要抓取的頻道
    $channels = [
        '1130' => ['id' => 'mbc action', 'name' =>' mbc action'],
        '1132' => ['id' => 'mbc max', 'name' => 'mbc max'],
        '1128' => ['id' => 'mbc 2', 'name' => 'mbc 2'],
    ];
    
    $channels_data = [
        'channels' => [],
        'programmes' => []
    ];
    
    foreach ($channels as $code => $info) {
        // 添加頻道信息
        $channels_data['channels'][] = $info;
        
        // 抓取和解析
        $url = "https://elcinema.com/en/tvguide/$code/";
        $html = fetch_epg($url);
        if ($html !== false) {
            $programs = parse_epg($html);
            foreach ($programs as $prog) {
                $prog['channel_id'] = $info['id'];
                $channels_data['programmes'][] = $prog;
            }
        }
    }
    
    // 生成 XML
    $xml_output = generate_xml($channels_data);
    
    // 保存到文件
    file_put_contents('epgmbcae.xml', $xml_output);
    
    // 輸出 XML
    //echo $xml_output;
    
} catch (Exception $e) {
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "錯誤: " . $e->getMessage();
}



?>