<?php
//127.0.0.1/epgastro1.php
ini_set("max_execution_time", "3000000");
ini_set('date.timezone','Asia/Shanghai');
$fp="epgastro.xml";

function compress_html($string) {
    $string = str_replace("\r", '', $string);
    $string = str_replace("\n", '', $string);
    $string = str_replace("\t", '', $string);
    return $string;
}

header('Content-Type: text/plain;charset=UTF-8');

$chn = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!DOCTYPE tv SYSTEM \"http://api.torrent-tv.ru/xmltv.dtd\">\n<tv generator-info-name=\"秋哥綜合\" generator-info-url=\"https://www.tdm.com.mo/c_tv/?ch=Satellite\">\n";

$dt1 = date('Ymd');
$dt14 = date('Y-m-d');
$dt2 = date('Ymd', time() + 24 * 3600);
$dt3 = date('Ymd', time() + 6 * 24 * 3600);
$w1 = date("w");
if ($w1 < '1') {
    $w1 = 7;
}
$w2 = $w1 + 1;
$dt11 = date('Y-m-d');
$dt12 = date('Y-m-d', time() + 24 * 3600);

 $url22='https://contenthub-api.eco.astro.com.my/api/v2/search-linear?channelGuide=true&platform=acm&channelOrderBy=stbNumber,asc';
   $ch22 = curl_init();
    curl_setopt($ch22, CURLOPT_URL, $url22);
    curl_setopt($ch22, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch22, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch22, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch22, CURLOPT_ENCODING, '');
    curl_setopt($ch22, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch22, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $re22 = curl_exec($ch22);
    $httpCode = curl_getinfo($ch22, CURLINFO_HTTP_CODE);
    curl_close($ch22);

for ($k22 =0; $k22 <= count($title=json_decode($re22)->response->channels->data)-1; $k22++) {
$id=json_decode($re22)->response->channels->data[$k22]->id;
$title=json_decode($re22)->response->channels->data[$k22]->title;
   $chn .= "<channel id=\"".$title."\"><display-name lang=\"zh\">".$title."</display-name></channel>\n";
}

for ($k22 =0; $k22 <= count($title=json_decode($re22)->response->channels->data)-1; $k22++) {
//for ($k22 =0; $k22 <=2; $k22++) {
$id=json_decode($re22)->response->channels->data[$k22]->id;
$title=json_decode($re22)->response->channels->data[$k22]->title;
 $url221 = "https://contenthub-api.eco.astro.com.my/channel/$id.json";

$ch221 = curl_init();
    curl_setopt($ch221, CURLOPT_URL, $url221);
    curl_setopt($ch221, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch221, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch221, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch221, CURLOPT_ENCODING, '');
    curl_setopt($ch221, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch221, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $re221 = curl_exec($ch221);
    $httpCode = curl_getinfo($ch221, CURLINFO_HTTP_CODE);
    curl_close($ch221);
    
    // 检查请求是否成功
    if ($httpCode !== 200 || empty($re221)) {
        error_log("Failed to fetch data for channel " . $title . ", HTTP Code: " . $httpCode);
        continue;
    }
    
    $data = json_decode($re221);
    
    // 检查JSON解码是否成功
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error for channel " . $title . ": " . json_last_error_msg());
        continue;
    }
    
    // 检查数据结构是否存在
    if (!isset($data->response) || !isset($data->response->schedule)) {
        error_log("Invalid data structure for channel " . $title);
        continue;
    }
    
    $schedule221 = $data->response->schedule;
    
    // 处理今天的节目
    if (isset($schedule221->$dt11) && is_array($schedule221->$dt11)) {
        $tyuu221 = $schedule221->$dt11;
        $trm221 = count($tyuu221);
        
        for ($k221 = 0; $k221 < $trm221 - 1; $k221++) {
            if (isset($tyuu221[$k221]) && isset($tyuu221[$k221 + 1])) {
                $title221 = $tyuu221[$k221]->title ?? '未知节目';
                $starttime221 = $tyuu221[$k221]->datetime ?? '';
                $endtime221 = $tyuu221[$k221 + 1]->datetime ?? '';
                
                if (!empty($starttime221) && !empty($endtime221)) {
                    $start = str_replace([':', '-', ' ', '.0'], '', $starttime221);
                    $stop = str_replace([':', '-', ' ', '.0'], '', $endtime221);
                    
                    $chn .= "<programme start=\"{$start} +0800\" stop=\"{$stop} +0800\" channel=\"{$title}\">\n";
                    $chn .= "<title lang=\"zh\">" . htmlspecialchars($title221) . "</title>\n";
                    $chn .= "<desc lang=\"zh\"> </desc>\n";
                    $chn .= "</programme>\n";
                }
            }
        }
    }
    
    // 处理明天的节目
    if (isset($schedule221->$dt12) && is_array($schedule221->$dt12)) {
        $tyuu2211 = $schedule221->$dt12;
        $trm2211 = count($tyuu2211);
        
        for ($k2211 = 0; $k2211 < $trm2211 - 1; $k2211++) {
            if (isset($tyuu2211[$k2211]) && isset($tyuu2211[$k2211 + 1])) {
                $title2211 = $tyuu2211[$k2211]->title ?? '未知节目';
                $starttime2211 = $tyuu2211[$k2211]->datetime ?? '';
                $endtime2211 = $tyuu2211[$k2211 + 1]->datetime ?? '';
                
                if (!empty($starttime2211) && !empty($endtime2211)) {
                    $start = str_replace([':', '-', ' ', '.0'], '', $starttime2211);
                    $stop = str_replace([':', '-', ' ', '.0'], '', $endtime2211);
                    
                    $chn .= "<programme start=\"{$start} +0800\" stop=\"{$stop} +0800\" channel=\"{$title}\">\n";
                    $chn .= "<title lang=\"zh\">" . htmlspecialchars($title2211) . "</title>\n";
                    $chn .= "<desc lang=\"zh\"> </desc>\n";
                    $chn .= "</programme>\n";
                }
            }
        }
    }
}

$chn .= "</tv>\n";

// 可选：保存到文件
file_put_contents($fp, $chn);

//echo $chn;

?>
