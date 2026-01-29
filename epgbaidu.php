 ﻿<?php
header('Content-Type: text/xml; charset=UTF-8');
ini_set("max_execution_time", "300");
ini_set('date.timezone', 'Asia/Shanghai');

$dt1 = date('Ymd'); // 当前日期
$dt2 = date('Ymd', time() + 24 * 3600); // 第二天日期
$dt3 = date('Ymd', time() + 48 * 3600); // 第三天日期

function convert_gbk_to_utf8($str)
{
    if (function_exists('iconv')) {
        return iconv('GBK', 'UTF-8//IGNORE', $str);
    } elseif (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, 'UTF-8', 'GBK');
    }
    return $str;
}

// 频道列表
$cid10 = array(
    array('新视觉', '新视觉'),
    array('劲爆体育', '劲爆体育'),
    array('海峡卫视', '海峡卫视'),
    array('深视都市频道', '深视都市频道'),
    array('深视电视剧频道', '深视电视剧频道'),
    array('深视财经生活频道', '深视财经生活频道'),
    array('深视体育健康频道', '深视体育健康频道'),
    array('深视少儿频道', '深视少儿频道'),
    array('深视移动电视频道', '深视移动电视频道'),
    array('福建电视台新闻频道', '福建电视台新闻频道'),
    array('福建乡村振兴·公共', '福建乡村振兴·公共'),
    array('福建电视剧频道', '福建电视剧频道'),
    array('福建旅游频道', '福建旅游频道'),
    array('福建经济频道', '福建经济频道'),
    array('福建体育频道', '福建体育频道'),
    array('福建少儿频道', '福建少儿频道'),
    array('福建综合频道', '福建综合频道'),
    // array('劲爆体育', '劲爆体育'), // 重复的行，已注释掉
    array('江苏城市频道', '江苏城市频道'),
    array('江苏综艺频道', '江苏综艺频道'),
    array('江苏影视频道', '江苏影视频道'),
    array('江苏新闻频道', '江苏新闻频道'),
    array('江苏教育频道', '江苏教育频道'),
    array('江苏体育休闲频道', '江苏体育休闲频道'),
    array('江苏优漫卡通频道', '江苏优漫卡通频道'),
    array('江苏国际频道', '江苏国际频道'),
);

// 开始生成 XML
$chn = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
$chn .= "<!DOCTYPE tv SYSTEM \"http://api.torrent-tv.ru/xmltv.dtd\">\n";
$chn .= "<tv generator-info-name=\"mytvsuper\" generator-info-url=\"https://www.tdm.com.mo/c_tv/?ch=Satellite\">\n";

// 添加频道定义
foreach ($cid10 as $channel) {
    $chn .= "<channel id=\"" . $channel[1] . "\">\n";
    $chn .= "<display-name lang=\"zh\">" . $channel[1] . "</display-name>\n";
    $chn .= "</channel>\n";
}

// 获取每个频道的节目信息
foreach ($cid10 as $channel) {
    $channelName = $channel[1];
    
    // 获取三个时间段的节目（今天、明天、后天）
    $programs = array();
    
    for ($tab = 1; $tab <= 3; $tab++) {
        $url = "https://sp1.baidu.com/8aQDcjqpAAV3otqbppnN2DJv/api.php?query=" . 
               urlencode($channelName) . "&co=data[tabid=" . $tab . "]&resource_id=12520";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => 'gzip, deflate'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $response = convert_gbk_to_utf8($response);
            $response = str_replace('/', '', $response);
            
            preg_match_all('/,"title":"(.*?)",/i', $response, $titles, PREG_SET_ORDER);
            preg_match_all('/"times":"(.*?)",/i', $response, $times, PREG_SET_ORDER);
            
            $count = min(count($titles), count($times));
            for ($i = 0; $i < $count; $i++) {
                $programs[] = array(
                    'title' => isset($titles[$i][1]) ? $titles[$i][1] : '',
                    'time' => isset($times[$i][1]) ? $times[$i][1] : ''
                );
            }
        }
        
        // 避免请求过快
        usleep(100000);
    }
    
    // 生成节目单XML
    $programCount = count($programs);
    for ($i = 0; $i < $programCount - 1; $i++) {
        $startTime = isset($programs[$i]['time']) ? $programs[$i]['time'] : '';
        $endTime = isset($programs[$i + 1]['time']) ? $programs[$i + 1]['time'] : '';
        $title = isset($programs[$i]['title']) ? $programs[$i]['title'] : '';
        
        if (!empty($startTime) && !empty($endTime) && !empty($title)) {
            $startTime = str_replace(['/', ' ', ':'], '', $startTime) . '00 +0800';
            $endTime = str_replace(['/', ' ', ':'], '', $endTime) . '00 +0800';
            
            $chn .= "<programme start=\"" . $startTime . "\" stop=\"" . $endTime . "\" channel=\"" . $channelName . "\">\n";
            $chn .= "<title lang=\"zh\">" . htmlspecialchars($title, ENT_XML1, 'UTF-8') . "</title>\n";
            $chn .= "<desc lang=\"zh\"> </desc>\n";
            $chn .= "</programme>\n";
        }
    }
    
    // 添加最后一个节目
    if ($programCount > 0) {
        $lastIndex = $programCount - 1;
        $lastTime = isset($programs[$lastIndex]['time']) ? $programs[$lastIndex]['time'] : '';
        $lastTitle = isset($programs[$lastIndex]['title']) ? $programs[$lastIndex]['title'] : '';
        
        if (!empty($lastTime) && !empty($lastTitle)) {
            $lastTime = str_replace(['/', ' ', ':'], '', $lastTime) . '00 +0800';
            $lastDate = substr($lastTime, 0, 8); // 获取日期部分
            $endTime = $lastDate . "235900 +0800";
            
            $chn .= "<programme start=\"" . $lastTime . "\" stop=\"" . $endTime . "\" channel=\"" . $channelName . "\">\n";
            $chn .= "<title lang=\"zh\">" . htmlspecialchars($lastTitle, ENT_XML1, 'UTF-8') . "</title>\n";
            $chn .= "<desc lang=\"zh\"> </desc>\n";
            $chn .= "</programme>\n";
        }
    }
}

$chn .= "</tv>\n";

// 输出 XML
echo $chn;
file_put_contents('epgbaidu.xml', $chn);

?>
