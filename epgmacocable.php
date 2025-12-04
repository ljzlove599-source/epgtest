<?php
header( 'Content-Type: text/plain; charset=UTF-8');
ini_set("max_execution_time", "3000000");
ini_set('date.timezone','Asia/Shanghai');

// 设置错误报告（开发时启用，生产环境应关闭）
error_reporting(E_ALL);
ini_set('display_errors', 1);

$fp="epgmacocable.xml";//压缩版本的扩展名后加.gz


// 获取动态日期
$today = date('Y-m-d');
$nextWeek = date('Y-m-d', time() + 7 * 24 * 3600);

$chn = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!DOCTYPE tv SYSTEM \"http://api.torrent-tv.ru/xmltv.dtd\">\n<tv generator-info-name=\"秋哥綜合\" generator-info-url=\"https://www.tdm.com.mo/c_tv/?ch=Satellite\">\n";

// 获取频道列表
$url22 = 'https://www.macaucabletv.com/api/config';
$ch22 = curl_init();

curl_setopt_array($ch22, [
    CURLOPT_URL => $url22,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => 'gzip, deflate, br, zstd',
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => [
        'Host: www.macaucabletv.com',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6',
        'Referer: https://www.macaucabletv.com/tvSchedule',
        'Cookie: NEXT_LOCALE=zh-Hant'
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_FOLLOWLOCATION => true,
]);

$response22 = curl_exec($ch22);
curl_close($ch22);

// 检查响应是否有效
if (!$response22) {
    die("无法获取频道列表");
}

$config22 = json_decode($response22);
if (!$config22 || !isset($config22->programmeConfig->haveScheduleChannels)) {
    die("无效的API响应");
}

$channels = $config22->programmeConfig->haveScheduleChannels;
$trm = count($channels);

// 循环获取每个频道的节目表
for ($k = 0; $k < $trm; $k++) {
    $channelCode = $channels[$k];
    
    $url221 = 'https://app.ksmctv.com/ApiWebService/rest/query';
    $data221 = [
        'header' => [
            'encrypt' => false
        ],
        'request' => [
            'data' => [
                'APP' => 'MCTVWEB',
                'Device' => 'MCTV001',
                'Channel' => $channelCode,
                'CategoryCode' => '',
                'DateFrom' => $today,
                'DateTo' => $nextWeek,
                'Lang' => 'TC'
            ],
            'resource_id' => '/mctvweb/get_epg_list'
        ]
    ];

    $headers221 = [
        'Host: app.ksmctv.com',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: zh-TW,zh;q=0.8,en-US;q=0.5,en;q=0.3',
        'Accept-Encoding: gzip, deflate, br, zstd',
        'Content-Type: application/json',
        'Origin: https://www.macaucabletv.com',
        'Referer: https://www.macaucabletv.com/',
        'Connection: keep-alive'
    ];

    $ch221 = curl_init();
    curl_setopt($ch221, CURLOPT_URL, $url221);
    curl_setopt($ch221, CURLOPT_POST, 1);
    curl_setopt($ch221, CURLOPT_POSTFIELDS, json_encode($data221));
    curl_setopt($ch221, CURLOPT_HTTPHEADER, $headers221);
    curl_setopt($ch221, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch221, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch221, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch221, CURLOPT_ENCODING, 'gzip');
    
    $response221 = curl_exec($ch221);
    $httpCode = curl_getinfo($ch221, CURLINFO_HTTP_CODE);
    curl_close($ch221);

    // 检查API响应
    if (!$response221 || $httpCode != 200) {
        continue; // 跳过这个频道，继续下一个
    }

    $result221 = json_decode($response221);
    
    // 检查JSON解码是否成功
    if (!$result221 || !isset($result221->result->List) || !is_array($result221->result->List)) {
        continue;
    }

    $programList221 = $result221->result->List;
    $listCount221 = count($programList221);

    if ($listCount221 > 0) {
        // 获取频道名称（从第一个节目获取）
        $channelName = isset($programList221[0]->ChannelName) ? $programList221[0]->ChannelName : "Channel_" . $channelCode;
        
        // 添加频道信息
        $chn .= "<channel id=\"" . htmlspecialchars($channelName) . "\">\n";
        $chn .= "<display-name lang=\"zh\">" . htmlspecialchars($channelName) . "</display-name>\n";
        $chn .= "</channel>\n";

        // 添加节目信息
        foreach ($programList221 as $program) {
            if (!isset($program->StartDate) || !isset($program->EndDate) || !isset($program->ProgramName)) {
                continue; // 跳过不完整的节目数据
            }

            // 格式化时间
            $startTime = str_replace(['-', ' ', ':'], '', $program->StartDate) . " +0800";
            $endTime = str_replace(['-', ' ', ':'], '', $program->EndDate) . " +0800";
            
            // 添加到XML
            $chn .= "<programme start=\"" . $startTime . "\" stop=\"" . $endTime . "\" channel=\"" . htmlspecialchars($channelName) . "\">\n";
            $chn .= "<title lang=\"zh\">" . htmlspecialchars($program->ProgramName) . "</title>\n";
            $chn .= "<desc lang=\"zh\"></desc>\n";
            $chn .= "</programme>\n";
        }
    }
}






$chn .= "</tv>\n";
file_put_contents($fp, $chn);
echo "EPG文件生成完成！路径：{$fp}";
?>
