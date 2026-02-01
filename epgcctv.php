<?php
/**
 * CNTV EPG 提取與 XML 轉換腳本 (多頻道版)
 */

header('Content-Type: text/xml; charset=UTF-8');
ini_set("max_execution_time", 900);
ini_set('date.timezone', 'Asia/Shanghai');

$dt1 = date('Ymd'); // 今日
$dt2 = date('Ymd', time() + 86400); // 明日

/**
 * 將毫秒轉換為指定時區的日期時間字串
 */
function convertMillisToDateTime($millis, $timezone = 'Asia/Shanghai')
{
    $seconds = floor($millis / 1000);
    $date = new DateTime("@$seconds", new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone($timezone));
    return $date->format('YmdHis');
}

// 1. 定義要提取的頻道列表
$channels = [
    'cctvamerica' => 'CCTV-4美洲頻道',
    'cctveurope' => 'CCTV-4歐洲頻道'
];

// 2. 構建 XML 根節點
$xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><tv/>');
$xml->addAttribute('generator-info-name', 'CNTV EPG Generator');

// 3. 循環處理每個頻道
foreach ($channels as $id => $defaultName) {
    $channelAdded = false;

    // 分別取得今天與明天的 EPG
    foreach ([$dt1, $dt2] as $date) {
        $url = "https://api.cntv.cn/epg/getEpgInfoByChannelNew?c={$id}&serviceId=tvcctv&d={$date}&t=jsonp&cb=setItem1";
        $response = @file_get_contents($url);
        if ($response === false) {
            continue;
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

            // 只添加一次頻道資訊
            if (!$channelAdded) {
                $channelNode = $xml->addChild('channel');
                $channelNode->addAttribute('id', htmlspecialchars($actualName));
                $channelNode->addChild('display-name', htmlspecialchars($actualName));
                $channelAdded = true;
            }

            // 添加節目列表
            foreach ($programList as $item) {
                if (!isset($item['startTime'], $item['endTime'], $item['title'])) {
                    continue;
                }

                $programme = $xml->addChild('programme');
               
                $start = date("YmdHis",($item['startTime'])) . ' +0800';
                $end = date("YmdHis",($item['endTime'])) . ' +0800';

                $programme->addAttribute('start', $start);
                $programme->addAttribute('stop', $end);
                $programme->addAttribute('channel', htmlspecialchars($actualName));

                $programme->addChild('title', htmlspecialchars($item['title']))->addAttribute('lang', 'zh');

                // 可選：添加節目描述
                if (isset($item['desc']) && !empty($item['desc'])) {
                    $programme->addChild('desc', htmlspecialchars($item['desc']))->addAttribute('lang', 'zh');
                }
            }
        }
    }
}

// 4. 輸出最終整合的 XML
$xml_string= $xml->asXML();

 file_put_contents('epgcctv.xml', $xml_string);
?>
