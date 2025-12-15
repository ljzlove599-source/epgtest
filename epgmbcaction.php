<?php
/**
 * ElCinema TV Guide Scraper
 * 從 elcinema.com 提取電視節目數據並輸出為 EPG XML 格式
 * 
 * 使用方法:
 * php tvguide_scraper_final.php
 * 
 * 輸出: tvguide_epg.xml
 */

// 設定時區
date_default_timezone_set('Asia/shanghai');

// 目標 URL
$url = 'https://elcinema.com/en/tvguide/1130/';

// 獲取網頁內容
$html = file_get_contents($url);
if ($html === false) {
    die("無法獲取網頁內容\n");
}

// 使用 DOMDocument 解析 HTML
$dom = new DOMDocument();
libxml_use_internal_errors(true); // 忽略 HTML 錯誤
$dom->loadHTML($html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

// 頻道信息
$channelName = 'MBC Action';
$channelId = 'mbc action';

$programs = [];

// 查找所有日期標題
$dateNodes = $xpath->query("//div[contains(@class, 'dates')]");
$dateMap = [];

foreach ($dateNodes as $dateNode) {
    $dateText = trim($dateNode->textContent);
    // 解析日期，例如 "Sunday 14 December"
    if (preg_match('/(\w+)\s+(\d+)\s+(\w+)/', $dateText, $matches)) {
        $day = $matches[2];
        $month = $matches[3];
        $year = date('Y'); // 使用當前年份
        $dateStr = "$day $month $year";
        $timestamp = strtotime($dateStr);
        if ($timestamp !== false) {
            $dateMap[$dateText] = $timestamp;
        }
    }
}

// 查找所有節目容器
$boxes = $xpath->query("//div[starts-with(@class, 'boxed-category-')]");

// 確定當前日期
$currentDate = null;
if (!empty($dateMap)) {
    $currentDate = reset($dateMap);
}

foreach ($boxes as $box) {
    $className = $box->getAttribute('class');
    
    // 跳過標題類
    if (strpos($className, 'header') !== false) {
        continue;
    }
    
    // 檢查前面是否有日期標題
    $parent = $box->parentNode;
    if ($parent) {
        $prevDates = $xpath->query("preceding::div[contains(@class, 'dates')][1]", $box);
        if ($prevDates->length > 0) {
            $prevDateText = trim($prevDates->item(0)->textContent);
            if (isset($dateMap[$prevDateText])) {
                $currentDate = $dateMap[$prevDateText];
            }
        }
    }
    
    if ($currentDate === null) {
        continue;
    }
    
    // 提取時間
    $timeDivs = $xpath->query(".//div[@class='columns small-3 large-2']", $box);
    
    if ($timeDivs->length == 0) {
        // 嘗試灰色背景格式
        $grayBoxes = $xpath->query(".//div[@class='columns small-9 large-11']", $box);
        if ($grayBoxes->length > 0) {
            $content = $grayBoxes->item(0)->textContent;
            $lines = array_filter(array_map('trim', explode("\n", $content)));
            $lines = array_values($lines);
            
            if (count($lines) >= 2) {
                $title = $lines[0];
                $timeText = $lines[1];
                
                if (preg_match('/(\d+):(\d+)\s+(AM|PM)/', $timeText, $timeMatch)) {
                    $hour = intval($timeMatch[1]);
                    $minute = intval($timeMatch[2]);
                    $ampm = $timeMatch[3];
                    
                    // 轉換為 24 小時制
                    if ($ampm == 'PM' && $hour != 12) {
                        $hour += 12;
                    } elseif ($ampm == 'AM' && $hour == 12) {
                        $hour = 0;
                    }
                    
                    $startTimestamp = $currentDate + ($hour * 3600) + ($minute * 60);
                    
                    // 提取時長
                    $duration = 60;
                    if (preg_match('/\[(\d+)\s+minutes\]/', $timeText, $durationMatch)) {
                        $duration = intval($durationMatch[1]);
                    }
                    
                    $stopTimestamp = $startTimestamp + ($duration * 60);
                    
                    $programs[] = [
                        'start' => date('YmdHis', $startTimestamp),
                        'stop' => date('YmdHis', $stopTimestamp),
                        'title' => $title,
                        'desc' => '',
                        'actors' => [],
                        'category' => '',
                        'date' => ''
                    ];
                }
            }
        }
        
        // 嘗試格式 2: columns small-7 large-11（阿拉伯語節目）
        $altBoxes = $xpath->query(".//div[@class='columns small-7 large-11']", $box);
        if ($altBoxes->length > 0) {
            $ulNodes = $xpath->query(".//ul[@class='unstyled no-margin']", $altBoxes->item(0));
            if ($ulNodes->length > 0) {
                $liNodes = $xpath->query(".//li", $ulNodes->item(0));
                if ($liNodes->length >= 2) {
                    // 第一個 li 是標題
                    $title = trim($liNodes->item(0)->textContent);
                    // 第二個 li 包含時間
                    $timeText = trim($liNodes->item(1)->textContent);
                    
                    if (preg_match('/(\d+):(\d+)\s+(AM|PM)/', $timeText, $timeMatch)) {
                        $hour = intval($timeMatch[1]);
                        $minute = intval($timeMatch[2]);
                        $ampm = $timeMatch[3];
                        
                        // 轉換為 24 小時制
                        if ($ampm == 'PM' && $hour != 12) {
                            $hour += 12;
                        } elseif ($ampm == 'AM' && $hour == 12) {
                            $hour = 0;
                        }
                        
                        $startTimestamp = $currentDate + ($hour * 3600) + ($minute * 60);
                        
                        // 提取時長
                        $duration = 60;
                        if (preg_match('/\[(\d+)\s+minutes\]/', $timeText, $durationMatch)) {
                            $duration = intval($durationMatch[1]);
                        }
                        
                        $stopTimestamp = $startTimestamp + ($duration * 60);
                        
                        $programs[] = [
                            'start' => date('YmdHis', $startTimestamp),
                            'stop' => date('YmdHis', $stopTimestamp),
                            'title' => $title,
                            'desc' => '',
                            'actors' => [],
                            'category' => '',
                            'date' => ''
                        ];
                    }
                }
            }
        }
        continue;
    }
    
    $timeDiv = $timeDivs->item(0);
    $timeText = $timeDiv->textContent;
    
    // 提取時間
    if (!preg_match('/(\d+):(\d+)\s+(AM|PM)/', $timeText, $timeMatch)) {
        continue;
    }
    
    $hour = intval($timeMatch[1]);
    $minute = intval($timeMatch[2]);
    $ampm = $timeMatch[3];
    
    // 轉換為 24 小時制
    if ($ampm == 'PM' && $hour != 12) {
        $hour += 12;
    } elseif ($ampm == 'AM' && $hour == 12) {
        $hour = 0;
    }
    
    $startTimestamp = $currentDate + ($hour * 3600) + ($minute * 60);
    
    // 提取時長
    $duration = 60;
    if (preg_match('/\[(\d+)\s+minutes\]/', $timeText, $durationMatch)) {
        $duration = intval($durationMatch[1]);
    }
    
    $stopTimestamp = $startTimestamp + ($duration * 60);
    
    // 提取標題
    $titleDivs = $xpath->query(".//div[contains(@class, 'columns small-6 large-3')]", $box);
    if ($titleDivs->length == 0) {
        continue;
    }
    
    $titleDiv = $titleDivs->item(0);
    $titleLinks = $xpath->query(".//a[1]", $titleDiv);
    if ($titleLinks->length == 0) {
        continue;
    }
    
    $title = trim($titleLinks->item(0)->textContent);
    if (empty($title)) {
        continue;
    }
    
    // 提取類型和年份
    $progType = '';
    $progYear = '';
    $typeLis = $xpath->query(".//li[2]", $titleDiv);
    if ($typeLis->length > 0) {
        $typeText = trim($typeLis->item(0)->textContent);
        if (preg_match('/(Series|Program|Movie)/', $typeText, $typeMatch)) {
            $progType = $typeMatch[1];
        }
        if (preg_match('/\((\d{4})\)/', $typeText, $yearMatch)) {
            $progYear = $yearMatch[1];
        }
    }
    
    // 提取評分
    $rating = '';
    $ratingSpans = $xpath->query(".//span[@class='legend']", $box);
    if ($ratingSpans->length > 0) {
        $ratingText = trim($ratingSpans->item(0)->textContent);
        if (preg_match('/([\d.]+)/', $ratingText, $ratingMatch)) {
            $rating = $ratingMatch[1];
            if ($rating == '0' || $rating == '0.0') {
                $rating = '';
            }
        }
    }
    
    // 提取演員
    $actors = [];
    $actorUls = $xpath->query(".//ul[@class='list-separator']", $box);
    if ($actorUls->length > 0) {
        $actorLinks = $xpath->query(".//a", $actorUls->item(0));
        foreach ($actorLinks as $actorLink) {
            $actors[] = trim($actorLink->textContent);
        }
    }
    
    // 提取描述
    $description = '';
    $descDivs = $xpath->query(".//div[contains(@class, 'columns small-12 large-6')]", $box);
    if ($descDivs->length > 0) {
        $descLis = $xpath->query(".//li", $descDivs->item(0));
        if ($descLis->length >= 3) {
            $descText = trim($descLis->item($descLis->length - 1)->textContent);
            $descText = preg_replace('/\.\.\.Read more/', '', $descText);
            $descText = trim($descText);
            if (strlen($descText) > 50) {
                $description = $descText;
            }
        }
    }
    
    // 組合完整描述
    $fullDesc = '';
    if (!empty($description)) {
        $fullDesc = $description;
    }
    if (!empty($progType)) {
        $fullDesc .= ($fullDesc ? ' | ' : '') . "Type: $progType";
    }
    if (!empty($progYear)) {
        $fullDesc .= " ($progYear)";
    }
    if (!empty($rating)) {
        $fullDesc .= ($fullDesc ? ' | ' : '') . "Rating: $rating/10";
    }
    
    $programs[] = [
        'start' => date('YmdHis', $startTimestamp),
        'stop' => date('YmdHis', $stopTimestamp),
        'title' => $title,
        'desc' => $fullDesc,
        'actors' => $actors,
        'category' => $progType,
        'date' => $progYear
    ];
}

// 生成 EPG XML
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

// 創建根元素
$tv = $xml->createElement('tv');
$tv->setAttribute('generator-info-name', 'ElCinema TV Guide Scraper');
$tv->setAttribute('generator-info-url', 'https://elcinema.com');
$xml->appendChild($tv);

// 添加頻道信息
$channel = $xml->createElement('channel');
$channel->setAttribute('id', $channelId);

$displayName = $xml->createElement('display-name', htmlspecialchars($channelName));
$channel->appendChild($displayName);

$tv->appendChild($channel);

// 添加節目信息
foreach ($programs as $prog) {
    $programme = $xml->createElement('programme');
    $programme->setAttribute('start', $prog['start'] . ' +0800');
    $programme->setAttribute('stop', $prog['stop'] . ' +0800');
    $programme->setAttribute('channel', $channelId);
    
    // 標題
    $title = $xml->createElement('title');
    $title->setAttribute('lang', 'en');
    $title->appendChild($xml->createTextNode($prog['title']));
    $programme->appendChild($title);
    
    // 描述
    if (!empty($prog['desc'])) {
        $desc = $xml->createElement('desc');
        $desc->setAttribute('lang', 'en');
        $desc->appendChild($xml->createTextNode($prog['desc']));
        $programme->appendChild($desc);
    }
    
    // 類別
    if (!empty($prog['category'])) {
        $category = $xml->createElement('category');
        $category->setAttribute('lang', 'en');
        $category->appendChild($xml->createTextNode($prog['category']));
        $programme->appendChild($category);
    }
    
    // 演員
    if (!empty($prog['actors'])) {
        $credits = $xml->createElement('credits');
        foreach ($prog['actors'] as $actor) {
            $actorNode = $xml->createElement('actor');
            $actorNode->appendChild($xml->createTextNode($actor));
            $credits->appendChild($actorNode);
        }
        $programme->appendChild($credits);
    }
    
    // 日期
    if (!empty($prog['date'])) {
        $date = $xml->createElement('date', $prog['date']);
        $programme->appendChild($date);
    }
    
    $tv->appendChild($programme);
}

// 輸出 XML
 $xml->saveXML();

// 同時保存到文件
$outputFile = 'epgmbcaction.xml';
$xml->save($outputFile);
//echo "\n\nEPG XML 已保存到: $outputFile\n";
//echo "共提取 " . count($programs) . " 個節目\n";
?>
