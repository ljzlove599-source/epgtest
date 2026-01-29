<?php
/**
 * 中天電視節目表提取與 XML EPG 生成腳本
 * 
 * 數據源: https://asia-east1-ctitv-237901.cloudfunctions.net/ProgramList-Api2
 * 格式: XML (EPG)
 * 日期格式: YYYYMMDDHHMMSS +0800
 */

// 設定參數
$chid = 'a1'; // 北美台
$date = isset($argv[1]) ? $argv[1] : date('Y-m-d'); // 預設為今天
$next_date = date('Y-m-d', strtotime($date . ' +1 day'));

// API URL
$api_url = "https://asia-east1-ctitv-237901.cloudfunctions.net/ProgramList-Api2?chid={$chid}&start={$date}&end={$next_date}";

// 獲取數據
$json_data = file_get_contents($api_url);
if (!$json_data) {
    die("無法從 API 獲取數據\n");
}

$programs = json_decode($json_data, true);
if (!is_array($programs)) {
    die("JSON 解析失敗\n");
}

// 格式化日期函數
function format_epg_date($iso_date) {
    // 原始格式為 2026-01-28T00:00:00Z (UTC)
    // 轉換為 20260128000000 +0800
    $dt = new DateTime($iso_date);
    $dt->setTimezone(new DateTimeZone('Asia/Taipei'));
    return $dt->format('YmdHis') . ' +0800';
}

// 生成 XML
$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tv/>');
$xml->addAttribute('generator-info-name', 'Manus EPG Generator');

// 添加頻道資訊 (假設固定為中天北美台)
$channel = $xml->addChild('channel');
$channel->addAttribute('id',  '中天北美台');
$channel->addChild('display-name', '中天北美台');

// 遍歷節目
foreach ($programs as $prog) {
    // 過濾掉不是當天開始的節目 (API 可能會返回跨天數據)
    $prog_start_local = new DateTime($prog['start']);
    $prog_start_local->setTimezone(new DateTimeZone('Asia/Taipei'));
    if ($prog_start_local->format('Y-m-d') !== $date) {
        // 如果需要包含所有返回的節目，可以註釋掉這行
        // continue; 
    }

    $programme = $xml->addChild('programme');
    $programme->addAttribute('start', format_epg_date($prog['start']));
    $programme->addAttribute('stop', format_epg_date($prog['end']));
    $programme->addAttribute('channel', '中天北美台');
    
    $title = $programme->addChild('title', htmlspecialchars($prog['title']));
    $title->addAttribute('lang', 'zh');
    
    if (isset($prog['levelName'])) {
        $rating = $programme->addChild('rating');
        $rating->addChild('value', htmlspecialchars($prog['levelName']));
    }
}

// 輸出 XML
header('Content-Type: application/xml; charset=utf-8');
$xml_content = $xml->asXML();
file_put_contents('epgzhongtianam.xml', $xml_content);
?>
