<?php
$headers661 = [
    'Host: www.dubaione.ae',
    'Connection: keep-alive',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0.0.0',
    'Referer: https://www.dubaione.ae/content/dubaione/en-ae/schedule.html',
    'Cookie: cookieWarn.accepted=true; twtr_pixel_opt_in=N; eu_cn=1',
];

// 获取两个页面的内容
$pages = [
    'https://www.dubaione.ae/content/dubaione/en-ae/schedule.html',
    'https://www.dubaione.ae/content/dubaione/en-ae/schedule.2.html',
    'https://www.dubaione.ae/content/dubaione/en-ae/schedule.3.html',
    'https://www.dubaione.ae/content/dubaione/en-ae/schedule.4.html'
];

$allPrograms = [];
$allPagePrograms = []; // 存储每个页面的节目数组
$pageDates = []; // 存储每个页面的日期信息

foreach ($pages as $pageIndex => $pageUrl) {
    $ch661 = curl_init();
    curl_setopt($ch661, CURLOPT_URL, $pageUrl);
    curl_setopt($ch661, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch661, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch661, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch661, CURLOPT_HTTPHEADER, $headers661);
    $re661 = curl_exec($ch661);
    $re661 = str_replace('&', '', $re661);
    curl_close($ch661);

    // 创建DOMDocument对象
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // 忽略HTML格式错误
    $dom->loadHTML($re661);
    libxml_clear_errors();

    // 创建XPath对象
    $xpath = new DOMXPath($dom);

    // 为每个页面分别提取日期信息
    $dateElement = $xpath->query('//li[@class="red active"]//h5[@class="date"]')->item(0);
    $dateText = $dateElement ? $dateElement->nodeValue : '';
    preg_match('/(\w+)\s+(\w+)\s+(\d+),\s+(\d+)/', $dateText, $dateMatches);
    
    $day = $dateMatches[1] ?? '';
    $month = $dateMatches[2] ?? '';
    $dayNum = $dateMatches[3] ?? '';
    $year = $dateMatches[4] ?? '';
    
    // 如果没有找到日期，使用当前日期
    if (empty($month) || empty($dayNum) || empty($year)) {
        $currentDate = new DateTime();
        $currentDate->modify("+$pageIndex day"); // 第二个页面可能是下一天的节目
        $day = $currentDate->format('D');
        $month = $currentDate->format('M');
        $dayNum = $currentDate->format('d');
        $year = $currentDate->format('Y');
    }
    
    $fullDate = $month . ' ' . $dayNum . ', ' . $year;
    
    // 存储页面日期信息
    $pageDates[$pageIndex] = [
        'year' => $year,
        'month' => $month,
        'day' => $dayNum,
        'full_date' => $fullDate
    ];

    // 提取节目信息
    $programElements = $xpath->query('//ul[@id="th-productcommentlist"]/li');
    $pagePrograms = [];

    foreach ($programElements as $programElement) {
        // 提取节目标题
        $titleElement = $xpath->query('.//h5', $programElement)->item(0);
        $title = $titleElement ? trim($titleElement->nodeValue) : '';
        
        // 提取时间信息
        $timeElement = $xpath->query('.//li[contains(text(), "GMT:")]', $programElement)->item(0);
        $timeText = $timeElement ? trim($timeElement->nodeValue) : '';
        
        // 解析GMT和UAE时间
        preg_match('/GMT:\s*(\d+:\d+)\s*UAE:\s*(\d+:\d+)/', $timeText, $timeMatches);
        $gmtTime = $timeMatches[1] ?? '';
        $uaeTime = $timeMatches[2] ?? '';
        
        // 提取节目描述
        $descriptionElement = $xpath->query('.//p', $programElement)->item(0);
        $description = $descriptionElement ? trim($descriptionElement->nodeValue) : '';
        
        if ($title && $gmtTime && $uaeTime) {
            $pagePrograms[] = [
                'title' => $title,
                'gmt_time' => $gmtTime,
                'uae_time' => $uaeTime,
                'description' => $description,
                'page_index' => $pageIndex,
                'source_page' => $pageUrl
            ];
        }
    }
    
    $allPagePrograms[] = $pagePrograms;
    
    // 输出每个页面的日期信息用于调试
    error_log("页面 " . ($pageIndex + 1) . " 日期: $fullDate, 节目数量: " . count($pagePrograms));
}

// 合并所有节目并计算结束时间
for ($pageIndex = 0; $pageIndex < count($allPagePrograms); $pageIndex++) {
    $pagePrograms = $allPagePrograms[$pageIndex];
    $currentPageDate = $pageDates[$pageIndex];
    
    for ($i = 0; $i < count($pagePrograms); $i++) {
        $program = $pagePrograms[$i];
        
        // 设置当前节目的开始日期信息
        $program['start_date'] = $currentPageDate;
        
        if ($i < count($pagePrograms) - 1) {
            // 当前页面的节目：结束时间是下一个节目的开始时间，使用当前页面日期
            $program['end_time'] = $pagePrograms[$i + 1]['uae_time'];
            $program['end_date'] = $currentPageDate;
        } else {
            // 当前页面的最后一个节目
            if ($pageIndex < count($allPagePrograms) - 1) {
                // 如果不是最后一页，结束时间是下一页第一个节目的开始时间，使用下一页日期
                $nextPagePrograms = $allPagePrograms[$pageIndex + 1];
                $nextPageDate = $pageDates[$pageIndex + 1];
                if (count($nextPagePrograms) > 0) {
                    $program['end_time'] = $nextPagePrograms[0]['uae_time'];
                    $program['end_date'] = $nextPageDate;
                } else {
                    // 如果下一页没有节目，结束时间设为第二天的00:00，使用下一页日期
                    $program['end_time'] = '00:00';
                    $program['end_date'] = $nextPageDate;
                }
            } else {
                // 最后一页的最后一个节目，结束时间设为第二天的00:00，使用当前页面日期+1天
                $endDate = new DateTime("{$currentPageDate['year']}-{$currentPageDate['month']}-{$currentPageDate['day']} {$program['uae_time']}");
                $endDate->modify('+1 day');
                $program['end_time'] = '00:00';
                $program['end_date'] = [
                    'year' => $endDate->format('Y'),
                    'month' => $endDate->format('M'),
                    'day' => $endDate->format('d'),
                    'full_date' => $endDate->format('M d, Y')
                ];
            }
        }
        
        $allPrograms[] = $program;
    }
}

// 创建XMLTV格式的XML文档
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

// 创建文档类型
$implementation = new DOMImplementation();
$dtd = $implementation->createDocumentType('tv', '', 'http://api.torrent-tv.ru/xmltv.dtd');
$xml = $implementation->createDocument(null, '', $dtd);
$xml->encoding = 'UTF-8';
$xml->formatOutput = true;

// 创建根元素
$root = $xml->createElement('tv');
$root->setAttribute('generator-info-name', 'http://192.168.10.1/system/opt/');
$root->setAttribute('generator-info-url', 'QQ');
$xml->appendChild($root);

// 添加频道信息
$channel = $xml->createElement('channel');
$channel->setAttribute('id', 'Dubai One');
$displayName = $xml->createElement('display-name', 'Dubai One');
$displayName->setAttribute('lang', 'en');
$channel->appendChild($displayName);
$root->appendChild($channel);

// 添加每个节目信息
foreach ($allPrograms as $index => $program) {
    // 使用动态计算的开始时间和结束时间
    $startTime = $program['uae_time'];
    $endTime = $program['end_time'];
    
    // 使用对应的日期信息
    $startDate = $program['start_date'];
    $endDate = $program['end_date'];
    
    // 转换为XMLTV时间格式
    $startDateTime = convertToXmltvTime($startDate['year'], $startDate['month'], $startDate['day'], $startTime);
    
    // 处理结束时间
    if ($endTime === '00:00') {
        // 结束时间是第二天的00:00
        $endDateTime = convertToXmltvTime($endDate['year'], $endDate['month'], $endDate['day'], '00:00');
    } else {
        $endDateTime = convertToXmltvTime($endDate['year'], $endDate['month'], $endDate['day'], $endTime);
    }
    
    $programme = $xml->createElement('programme');
    $programme->setAttribute('start', $startDateTime);
    $programme->setAttribute('stop', $endDateTime);
    $programme->setAttribute('channel', 'Dubai One');
    
    // 添加标题
    $titleElement = $xml->createElement('title', htmlspecialchars($program['title']));
    $titleElement->setAttribute('lang', 'en');
    $programme->appendChild($titleElement);
    
    // 添加描述（如果有）
    if (!empty($program['description'])) {
        $descElement = $xml->createElement('desc', htmlspecialchars($program['description']));
        $descElement->setAttribute('lang', 'en');
        $programme->appendChild($descElement);
    }
    
    $root->appendChild($programme);
}

// 保存XML文件
$xml->save('epgdubaione.xml');

// 输出XML内容（这应该是脚本的最后输出）
header('Content-Type: application/xml; charset=utf-8');
echo $xml->saveXML();

// 辅助函数：转换为XMLTV时间格式
function convertToXmltvTime($year, $month, $day, $time) {
    // 将月份名称转换为数字
    $monthNames = [
        'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04',
        'May' => '05', 'Jun' => '06', 'Jul' => '07', 'Aug' => '08',
        'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dec' => '12'
    ];
    
    $monthNum = $monthNames[$month] ?? '01';
    
    // 格式化日期和时间
    $dateTimeString = $year . $monthNum . $day . ' ' . $time;
    $dateTime = DateTime::createFromFormat('Ymd H:i', $dateTimeString);
    
    // 转换为XMLTV格式: YYYYMMDDHHMMSS +0000
    return $dateTime->format('YmdHis') . ' +0400'; // 阿联酋时区为+4
}
?>
