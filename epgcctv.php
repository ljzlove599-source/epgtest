<?php
/**
 * CNTV EPG 提取與 XML 轉換腳本 (多頻道版)
 * 修復 XML 格式問題，符合 XMLTV 標準
 */

header('Content-Type: application/xml; charset=utf-8');
date_default_timezone_set('Asia/Shanghai');

// 1. 定義要提取的頻道列表
$channels = [
     'cctvamerica' => 'CCTV-4美洲頻道',
    'cctveurope' => 'CCTV-4歐洲頻道'
];
$date = '20260201';

// 2. 構建 XML 根節點
$xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><tv/>');
$xml->addAttribute('generator-info-name', 'CNTV EPG Generator');
$xml->addAttribute('source-info-url', 'https://api.cntv.cn');

// 3. 先收集所有頻道和節目數據
$channelDataList = [];
$programmeDataList = [];

foreach ($channels as $id => $defaultName) {
    $url = "https://api.cntv.cn/epg/getEpgInfoByChannelNew?c={$id}&serviceId=tvcctv&d={$date}&t=jsonp&cb=setItem1";
    
    $response = file_get_contents($url);
    if ($response === false) {
        continue; // 跳過無法獲取的頻道
    }

    // 處理 JSONP 格式
    if (preg_match('/setItem1\((.*)\);/s', $response, $matches)) {
        $jsonData = json_decode($matches[1], true);
        if (!$jsonData || !isset($jsonData['data'][$id]['list'])) {
            continue;
        }

        $channelData = $jsonData['data'][$id];
        $actualName = $channelData['channelName'] ?? $defaultName;
        $programList = $channelData['list'];

        // 存儲頻道信息
        $channelDataList[$id] = [
            'id' => $id,
            'name' => $actualName
        ];

        // 存儲節目信息
        foreach ($programList as $item) {
            if (!isset($item['startTime']) || !isset($item['endTime']) || !isset($item['title'])) {
                continue;
            }
            
            // 格式化時間: 20260201000000 +0800
            $start = date('YmdHis', $item['startTime']) . ' +0800';
            $end = date('YmdHis', $item['endTime']) . ' +0800';
            
            $programmeDataList[] = [
                'channel' => $id,
                'start' => $start,
                'stop' => $end,
                'title' => str_replace('/','',$item['title'])
            ];
        }
    }
}

// 4. 先添加所有頻道節點
foreach ($channelDataList as $channelData) {
    $channelNode = $xml->addChild('channel');
    $channelNode->addAttribute('id', $channelData['id']);
    $displayName = $channelNode->addChild('display-name', htmlspecialchars($channelData['name']));
    $displayName->addAttribute('lang', 'zh');
    
    // 可選：添加其他頻道信息
    $channelNode->addChild('url', 'https://tv.cctv.com/');
}

// 5. 再添加所有節目節點
foreach ($programmeDataList as $programmeData) {
    $programme = $xml->addChild('programme');
    $programme->addAttribute('channel', $programmeData['channel']);
    $programme->addAttribute('start', $programmeData['start']);
    $programme->addAttribute('stop', $programmeData['stop']);
    
    $title = $programme->addChild('title', htmlspecialchars($programmeData['title']));
    $title->addAttribute('lang', 'zh');
    
    // 可選：添加其他節目信息
    // $programme->addChild('desc', '節目描述')->addAttribute('lang', 'zh');
    // $programme->addChild('category', '電視劇')->addAttribute('lang', 'zh');
}

// 6. 輸出最終整合的 XML
$dom = new DOMDocument('1.0', 'utf-8');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());

echo $dom->saveXML();
?>
