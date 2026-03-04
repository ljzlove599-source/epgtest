<?php
// PHP 脚本：从 Singtel TV EPG JSON 数据提取并生成 XML 格式的 EPG（支持多天数据）
// 用法：php this_script.php [起始日期 ddMMyyyy] [天数]，例如 php this_script.php 17022026 2
// 如果未提供参数，使用当前日期（新加坡时间 UTC+8）作为起始，生成 2 天数据
// 示例：生成 2026-02-17 和 2026-02-18 的 EPG

// 获取参数，默认起始日期为当前，生成 2 天
$startDateInput = isset($argv[1]) ? $argv[1] : date('dmY', strtotime('now +8 hours')); // ddMMyyyy
$days = isset($argv[2]) ? (int) $argv[2] : 2;

// 解析起始日期
$startDateObj = DateTime::createFromFormat('dmY', $startDateInput, new DateTimeZone('Asia/Singapore'));
if (!$startDateObj) {
    die("无效日期格式，请使用 ddMMyyyy（如 17022026）。\n");
}
$startDateObj->setTimezone(new DateTimeZone('Asia/Singapore'));

// 生成日期数组
$dates = [];
for ($i = 0; $i < $days; $i++) {
    $dateObj = clone $startDateObj;
    $dateObj->modify("+$i days");
    $dates[] = $dateObj->format('dmY');
}

// 频道 ID 到名称的完整映射数组（同前，覆盖 150+ 频道）
$channelMap = [
    // Sports & Combat Channels
    '6110' => 'mio Sports',
    '6124' => 'mio Sports',
    '6126' => 'LaLiga Sports TV',
    '6119' => 'Fight Sports',
    '5111' => 'mio Sports',
    '5112' => 'mio Sports 2',
    '5113' => 'mio Sports 3',
    '6127' => 'Premier Sports',
    '5119' => 'Outdoor Channel',
    '5257' => 'SPOTV',

    // Movies & Entertainment Channels
    '5662' => 'HBO',
    '5670' => 'HITS Movies',
    '5310' => 'AXN',
    '6420' => 'Celestial Movies',
    '6301' => 'HBO Hits',
    '6424' => 'CINEMAX',
    '5668' => 'ZEE TV',
    '5302' => 'Lifetime',
    '5318' => 'BBC First',
    '5303' => 'HITS',
    '5304' => 'ROCK Entertainment',
    '5513' => 'tvN Asia',
    '5634' => 'Zee Tamil',
    '5517' => 'TVB Jade',
    '5638' => 'Astro Ria',
    '5518' => 'KBS World',
    '5639' => 'DramaFirst',
    '5644' => 'Colors',
    '5646' => 'STAR Plus',
    '5648' => 'Sony SAB',
    '6571' => 'Astro Vaanavil',
    '6692' => 'GMA Life TV',

    // Kids & Animation Channels
    '6236' => 'Cartoon Network',
    '5630' => 'Nick Jr.',
    '6422' => 'Cartoon Network',
    '5226' => 'Cartoonito',
    '2120100' => 'Nickelodeon Asia',
    '5240' => 'Nickelodeon Asia',
    '5238' => 'CBeebies',
    '6423' => 'Disney Channel',

    // News Channels
    '5151' => 'Sky News',
    '5157' => 'NHK World',
    '5682' => 'NHK World Premium',
    '5167' => 'CNN International',
    '5041' => 'Al Jazeera English',
    '5680' => 'WION',
    '5165' => 'FOX News',
    '5561' => 'ET Today',
    '6166' => 'BBC World News',
    '6547' => 'Phoenix InfoNews',
    '5676' => 'Times Now',
    '5677' => 'Bloomberg Television',
    '5040' => 'France 24',
    '5161' => 'DW English',
    '5038' => 'Arirang TV',
    '5173' => 'CNBC Asia',
    '5690' => 'ABS-CBN News',

    // Chinese-Language Channels
    '5537' => 'iChannel',
    '5538' => 'Hi TV',
    '6507' => 'Formosa TV',
    '5501' => 'Zhejiang Satellite',
    '5502' => 'Dragon TV',
    '5503' => 'Hunan TV',

    // Hindi & Indian Entertainment
    '5654' => 'Sony Entertainment',
    '5656' => 'Zee Cinema',
    '5668' => 'Sony MAX',
    '5632' => 'Sun TV',
    '5623' => 'Colors Tamil',
    '5625' => 'Sun Music',
    '5627' => 'Zee Music',
    '5628' => 'Aditya TV',

    // Korean & Japanese Channels
    '5619' => 'ONE TV Asia',
    '5604' => 'K-PLUS',
    '5580' => 'Okto',
    '5340' => 'ANIPLUS',
    '5342' => 'Animax Asia',

    // Filipino Channels
    '22437' => 'The Filipino Channel',
    '5693' => 'GMA Pinoy TV',
    '5056' => 'GMA News TV',
    '6694' => 'ABS-CBN',

    // Educational & Documentary Channels
    '5211' => 'National Geographic',
    '5212' => 'BBC Earth',
    '5215' => 'Animal Planet',
    '6203' => 'Discovery Channel',
    '5202' => 'Investigation Discovery',
    '5208' => 'National Geographic',
    '5209' => 'HISTORY',

    // Lifestyle & Food Channels
    '6255' => 'Food Network',
    '5250' => 'HGTV',
    '5251' => 'TLC',
    '5254' => 'BBC Lifestyle',
    '5256' => 'Asian Food Network',

    // Music Channels
    '5671' => 'MTV Asia',

    // Regional Asian Channels
    '5618' => 'Astro Awani',
    '5610' => 'Astro Arena',
    '5607' => 'Astro Vaanavil',

    // General Entertainment & Mixed
    '5658' => 'Vasantham',
    '5005' => 'Channel 5',
    '5007' => 'Channel 8',
    '5002' => 'Suria',
    '5003' => 'Vasantham',
    '5004' => 'CNA',
    '5521' => 'Ch U',
    '5585' => 'Okto',
    '5326' => 'Channel NewsAsia',
    '5206' => 'Crime + Investigation',
    '5671' => 'Star Maa',
    '6516' => 'TVBS',
    '5557' => 'CCTV-4',
    '5171' => 'Bloomberg Television',
    '5689' => 'GMA',
    '6421' => 'HBO Signature',
    '5652' => 'HBO Family',
    '5319' => 'MeWatch',
    // 3 位频道号映射
    '2' => 'Channel 5',
    '3' => 'Channel 8',
    '4' => 'Suria',
    '5' => 'Vasantham',
    '6' => 'CNA',
    '7' => 'Ch U',
    '11' => 'e-Le',
    '16' => 'Fight Sports',
    '17' => 'Outdoor Channel',
    '102' => 'Hub Premier 1',
    '111' => 'mio Sports',
    '112' => 'mio Sports 2',
    '113' => 'mio Sports 3',
    '114' => 'SPOTV',
    '115' => 'SPOTV 2',
    '116' => 'Eurosport',
    '118' => 'Premier Sports',
    '123' => 'Cricbuzz',
    '124' => 'Cricbuzz 2',
    '125' => 'Cricket Plus',
    '126' => 'beIN SPORTS',
    '127' => 'beIN SPORTS 2',
    '128' => 'beIN SPORTS 3',
    '131' => 'MOLA Sport',
    '151' => 'Sky News',
    '155' => 'CGTN',
    '157' => 'NHK World',
    '159' => 'France 24 English',
    '161' => 'Deutsche Welle',
    '165' => 'FOX News',
    '166' => 'BBC News',
    '167' => 'CNN International',
    '171' => 'Bloomberg Television',
    '173' => 'CNBC Asia',
    '202' => 'Discovery Channel',
    '203' => 'BBC Earth',
    '204' => 'Discovery Asia',
    '206' => 'Crime + Investigation',
    '208' => 'Animal Planet',
    '209' => 'HISTORY',
    '211' => 'CGTN Documentary',
    '226' => 'Cartoon Network',
    '228' => 'Cartoonito',
    '236' => 'CBeebies',
    '238' => 'Nick Jr.',
    '240' => 'Nickelodeon Asia',
    '250' => 'HGTV',
    '251' => 'ABC Australia',
    '252' => 'Food Network',
    '254' => 'TLC',
    '255' => 'BBC Lifestyle',
    '256' => 'Asian Food Network',
    '257' => 'TRACE Sports Stars',
    '301' => 'HITS Movies',
    '302' => 'Lifetime',
    '303' => 'HITS NOW',
    '304' => 'AXN',
    '308' => 'BBC First',
    '310' => 'ROCK Action',
    '318' => 'ROCK Entertainment',
    '326' => 'HITS',
    '340' => 'ANIPLUS',
    '342' => 'Animax',
    '349' => 'MTV LIVE',
    '420' => 'HBO',
    '421' => 'HBO Signature',
    '422' => 'HBO Family',
    '423' => 'HBO Hits',
    '424' => 'CINEMAX',
    '502' => 'Jia Le Channel',
    '503' => 'Ju Le Cool',
    '507' => 'TVBS Asia',
    '511' => 'TVB Jade',
    '512' => 'now Jelli',
    '513' => 'ONE HD Mandarin',
    '516' => 'TVBS News',
    '517' => 'TVB Xing He',
    '518' => 'tvN Asia',
    '521' => 'ETTV Asia',
    '534' => 'CCTV Entertainment',
    '535' => 'Dragon TV Intl',
    '536' => 'Hunan International',
    '537' => 'BRTV International',
    '538' => 'China Movie Channel',
    '547' => 'Phoenix InfoNews',
    '555' => 'CCTV-4',
    '557' => 'CTI Asia',
    '561' => 'ETTV Asia News',
    '571' => 'CM+',
    '580' => 'CCM',
    '585' => 'Celestial Movies',
    '602' => 'Astro Prima',
    '603' => 'Astro Citra',
    '604' => 'ONE HD Malay',
    '606' => 'KBS World',
    '607' => 'Pesona',
    '608' => 'Astro Ria',
    '609' => 'Astro Maya',
    '610' => 'Citra Drama',
    '618' => 'Drama Channel',
    '619' => 'tvN Malay',
    '622' => 'Sun TV',
    '623' => 'Colors Tamil',
    '624' => 'Zee Thirai',
    '625' => 'KTV',
    '627' => 'Sun Music',
    '628' => 'Adithya TV',
    '632' => 'Zee Tamil',
    '634' => 'Vijay',
    '638' => 'Asianet',
    '639' => 'Asianet Movies',
    '644' => 'SET Hindi',
    '646' => 'ZEE TV',
    '648' => 'SAB TV',
    '652' => 'Colors',
    '654' => 'Star Bharat',
    '656' => 'STAR Plus',
    '662' => 'Star Gold',
    '668' => 'Sony Max',
    '670' => 'Maa Movies',
    '671' => 'Star Maa',
    '676' => 'Times Now',
    '677' => 'Zoom TV',
    '680' => 'WION',
    '682' => 'NHK World Premium',
    '686' => 'France 24 French',
    '688' => 'GMA Pinoy TV',
    '689' => 'GMA Life TV',
    '690' => 'GMA News TV',
    '691' => 'GMA Hits',
    '692' => 'Cinema One Global',
    '693' => 'The Filipino Channel',
    '694' => 'ABS-CBN News',
    '630' => 'Sony YAY!',
];

// 收集所有节目数据（数组：频道 => 节目数组）
$allPrograms = [];
$allChannels = [];
foreach ($dates as $dateStr) {
    $url = "https://www.singtel.com/etc/singtel/public/tv/epg-parsed-data/{$dateStr}.json";
    $json = file_get_contents($url);
    if ($json === false) {
        echo "警告：无法获取 {$dateStr} 的数据，跳过。\n";
        continue;
    }
    $data = json_decode($json, true);
    if ($data === null) {
        echo "警告：{$dateStr} JSON 解析失败，跳过。\n";
        continue;
    }

    // 合并节目和频道
    foreach ($data as $channelId => $programs) {
        $allChannels[$channelId] = true;
        if (!isset($allPrograms[$channelId])) {
            $allPrograms[$channelId] = [];
        }
        foreach ($programs as $program) {
            // 添加日期标识到节目中（可用于排序，但后续按时间排序即可）
            $program['date'] = $dateStr;
            $allPrograms[$channelId][] = $program;
        }
    }
}

if (empty($allPrograms)) {
    die("无有效数据获取。\n");
}

// 生成 XML 字符串
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<tv generator-info-name="Singtel EPG">' . "\n";

// 输出所有唯一频道，使用原始ID作为id，显示名称作为display-name
foreach (array_keys($allChannels) as $channelId) {
    $displayName = $channelMap[$channelId] ?? $channelId;
    // 修改点1：id 使用原始 $channelId，保证唯一性
    $xml .= '<channel id="' . htmlspecialchars($channelId) . '">' . "\n";
    $xml .= '<display-name lang="en">' . htmlspecialchars($displayName) . '</display-name>' . "\n";
    $xml .= '</channel>' . "\n";
}

// 然后输出所有节目（按频道和起始时间排序）
foreach ($allPrograms as $channelId => $programs) {
    // 按起始时间排序
    usort($programs, function($a, $b) {
        return strtotime($a['startDateTime']) <=> strtotime($b['startDateTime']);
    });

    foreach ($programs as $program) {
        $startStr = $program['startDateTime']; // 如 "2026-02-17T00:00:00"
        $duration = (int) $program['duration']; // 秒

        // 使用DateTime处理时间，确保时区正确（假设JSON时间已是新加坡时间）
        $startDt = new DateTime($startStr, new DateTimeZone('Asia/Singapore'));
        $stopDt = clone $startDt;
        $stopDt->modify("+$duration seconds");

        // 格式化为 XMLTV 要求的格式：YYYYMMDDHHMMSS +0800
        $startXml = $startDt->format('YmdHis') . ' +0800';
        $stopXml = $stopDt->format('YmdHis') . ' +0800';

        $title = $program['program']['title'] ?? '';
        $desc = $program['program']['description'] ?? '';
        $category = $program['program']['category'] ?? '';
        $subCategory = $program['program']['subCategory'] ?? '';

        // 修改点2：channel 属性使用原始 $channelId，与 <channel> 标签的 id 一致
        $xml .= '  <programme start="' . $startXml . '" stop="' . $stopXml . '" channel="' . htmlspecialchars($channelId) . '">' . "\n";
        $xml .= '    <title lang="en">' . htmlspecialchars($title) . '</title>' . "\n";
        if (!empty($desc)) {
            $xml .= '    <desc lang="en">' . htmlspecialchars($desc) . '</desc>' . "\n";
        }
        if (!empty($category)) {
            $xml .= '    <category lang="en">' . htmlspecialchars($category) . '</category>' . "\n";
        }
        if (!empty($subCategory)) {
            $xml .= '    <category lang="en">' . htmlspecialchars($subCategory) . '</category>' . "\n";
        }
        $xml .= '  </programme>' . "\n";
    }
}

$xml .= '</tv>' . "\n";

// 写入文件
file_put_contents('epgsingtel.xml', $xml);
echo "EPG 已生成至 epgsingtel.xml\n";
