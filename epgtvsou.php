<?php
ini_set("max_execution_time", "3000000");
ini_set('date.timezone','Asia/Shanghai');
header('Content-Type: text/plain; charset=UTF-8');
$dt1 = date('Ymd'); // 获取当前日期
$dt2 = date('Ymd', time() + 24 * 3600); // 第二天日期
$w1 = date("w"); // 当前第几周
$fp="epgtvsou.xml";//压缩版本的扩展名后加.gz
if ($w1 < '1') {
    $w1 = 7;
}

function compress_html($string) {
    $string = str_replace("\r", '', $string);
    $string = str_replace("\n", '', $string);
    $string = str_replace("\t", '', $string);
    return $string;
}

// 提取纯文本内容（去除HTML标签）
function extract_text($html) {
    return trim(strip_tags($html));
}

// 提取时间（从HTML中提取纯数字时间，如 "06:30" -> "0630"）
function extract_time($html) {
    // 先尝试从链接文本中提取时间
    if (preg_match('/>(\d{1,2}:\d{2})</', $html, $matches)) {
        return str_replace(':', '', trim($matches[1]));
    }
    
    // 如果没有链接，尝试从纯文本中提取时间格式
    $text = extract_text($html);
    if (preg_match('/(\d{1,2}:\d{2})/', $text, $matches)) {
        return str_replace(':', '', trim($matches[1]));
    }
    
    // 最后尝试提取所有数字
    return preg_replace('/[^\d]/', '', $text);
}

// 生成EPG XML
$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
$xml .= "<!DOCTYPE tv SYSTEM \"http://api.torrent-tv.ru/xmltv.dtd\">\n";
$xml .= "<tv generator-info-name=\"秋哥綜合\" generator-info-url=\"https://www.tdm.com.mo/c_tv/?ch=Satellite\">\n";
$id180=100637;
$cid18=array(
array('34d6b6de','湖南经视'),


array('42688016','湖南都市'),


array('630175b5','杭州HTV1'),
array('1e5fbb58','杭州HTV2'),
array('95d67054','杭州HTV3'),
array('672d3eb5','杭州HTV4'),
array('dd3b38a0','杭州HTV5'),

array('955ecdd7','北京文艺'),
array('92c15909','北京科教'),
array('3e5a5dd9','北京影视'),
array('3e2d42b6','北京财经'),
array('c77b68e3','冬奥纪实'),
array('e0f83832','北京生活'),
array('8e03f801','北京青年'),
array('568c1d01','北京新闻'),

array('da0c3f96','福建卫视'),
array('1c31fa33','福建综合'),
array('03876f1e','福建公共'),
array('2efe4968','福建新闻'),
array('278c5306','福建电视剧'),
array('055de5f7','福建旅游'),
array('5c010cb5','福建经济'),
array('2b0cec63','福建体育'),
array('58117ca6','福建少儿'),
array('1d7e3bf3','海峡卫视'),
array('2b0cec63','福建体育'),
array('4a075202','泉州综合'),
array('cc449830','泉州闽南语'),
array('e965b42d','福州新闻综合'),
array('e4c0a464','福州影视'),
array('b860949f','福州生活'),
array('f21d3136','福州少儿'),
array('ff77bdd7','厦门新闻'),
array('8c3501ed','厦门纪实'),
array('b76cd361','厦门生活'),
array('1793d645','厦门影视'),
array('d6253770','厦门卫视'),
array('4a075202','泉州综合'),
array('4a075202','泉州新闻'),
array('cc449830','泉州闽南语'),
array('f612a938','泉州都市'),
array('f8b5ec10','泉州影视'),
array('c8e99a9e','宁德公共'),

array('27679d2c','广州综合'),
array('989aeb46','深圳体育'),
array('13530beb','汕头经济'),
array('0b292c61','湖南都市'),
array('34d6b6de','湖南经视'),
array('bc0c3927','湖南金鹰卡通'),
array('1774c72d','湖南娱乐'),
array('1a4629ba','湖南电视剧'),
array('84f64837','湖南公共'),
array('2173780a','湖南电影'),
array('6e78c8ef','湖南教育'),
array('dcc1efb0','湖南国际'),

array('a7ccaf45','宁夏公共'),
array('7133443f','宁夏经济'),
array('69418797','宁夏少儿'),
array('795245bc','宁夏影视'),
array('6d837cac','宁夏家庭影院'),

array('553ef8d8','海南经济'),
array('4e635851','海南新闻'),
array('8d24f008','海南文旅'),
array('c23fe8bf','海南公共'),
array('565fea55','海南少儿'),
array('79d20678','三沙卫视'),

array('32ee6fd0','重庆新闻'),
array('ad4833b1','重庆汽摩'),
array('a2d3d6f8','重庆影视'),
array('11aba543','重庆科教'),
array('7abc2de5','重庆文体娱乐'),
array('d65885f7','重庆生活资讯'),
array('a9006c72','重庆时尚'),

array('a5a8ad70','贵州公共'),
array('0bd918c5','贵州影视'),
array('de88abbc','贵州生活'),
array('7970d041','贵州5'),
array('ed02b302','贵州科教'),
array('5a44bb16','教育1台'),
array('e04d023a','教育2台'),
array('d9d2a299','教育3台'),
array('a3659498','教育4台'),
array('f7a329b4','南京综合'),
array('a2b677b6','南京十八'),
array('46b866c6','南京教科'),
array('3c51f1d2','云南都市'),
array('eac944ba','云南娱乐'),
array('5946e149','云南生活'),
array('84f3d0dc','云南影视'),
array('4095b4d9','云南公共'),
array('e114b6f5','云南少儿'),
array('13535ab2','四川文旅'),
array('f37ad654','四川经济'),
array('ec7fb697','四川新闻'),
array('5e876c64','四川影视'),
array('a003e21f','四川妇女'),
array('c88415dc','四川公共'),
array('497b7291','峨嵋电影'),
array('724dd3b8','成都新闻'),
array('01314e96','成都经济'),
array('cf7a0aa2','成都公共'),
array('5fdc00b9','无锡新闻'),
array('88cb2793','无锡都市'),
array('4a35a673','合肥新闻'),
array('c1626df1','合肥法制'),
array('3d2e883e','合肥生活'),
array('316f7f3a','合肥财经'),
array('95901348','合肥影院'),
array('dadd46e2','徐州新闻'),
array('04798d36','徐州经济'),
array('79e7e145','徐州影视'),
array('c937bb3d','徐州公共'),
array('45c564c2','张家界新闻'),
array('0d721e93','张家界都市'),
array('d91d6317','湘潭综合'),
array('69f30901','娄底综合'),
array('38638158','星空卫视'),
array('a80db3ff','阳光卫视'),
array('47ddeb43','中华卫视'),
array('0c3e8f66','苏州新闻'),
array('92f4c356','苏州经济'),
array('b7a73659','苏州生活'),
array('c1457c1f','数码时代'),
array('026b0a8e','宁波新闻'),
array('69e009c7','镇海综合'),
array('52f82a59','温州新闻'),
array('d7c404f9','哈尔滨综合'),
array('21091e38','哈尔滨资讯'),
array('8d11e796','哈尔滨娱乐'),
array('533eda3c','哈尔滨影视'),
array('dc1a60d0','黑龙江都市'),
array('647ce786','黑龙江法制'),
array('7974ba5b','新疆综艺'),
array('316d50d4','新疆影视'),
array('55bfd9aa','新疆经济'),
array('2ec4c09d','南昌综合'),
array('0dad36d7','湖北教育'),

array('bd03b751','南宁公共'),
array('4fcb0eb5','南宁影视'),
array('8c3501ed','厦门2'),
array('f21d3136','福州少儿'),
array('e3db88c7','南宁都市'),
array('ff77bdd7','厦门1'),
array('b76cd361','厦门3'),
array('1793d645','厦门4'),
    
array('e965b42d','福州新闻'),
array('e211ffe8','南宁新闻'),
array('38638158','星空卫视'),
array('a80db3ff','阳光卫视'),
array('47ddeb43','中华卫视'),
array('0c3e8f66','苏州新闻'),
array('92f4c356','苏州经济'),
array('b7a73659','苏州生活'),
array('c1457c1f','数码时代'),
array('026b0a8e','宁波新闻'),
array('69e009c7','镇海综合'),
array('52f82a59','温州新闻'),
array('d7c404f9','哈尔滨综合'),
array('21091e38','哈尔滨资讯'),
array('8d11e796','哈尔滨娱乐'),
array('533eda3c','哈尔滨影视'),
array('dc1a60d0','黑龙江都市'),
array('647ce786','黑龙江法制'),
array('7974ba5b','新疆综艺'),
array('316d50d4','新疆影视'),
array('55bfd9aa','新疆经济'),
array('2ec4c09d','南昌综合'),
array('0dad36d7','湖北教育'),
array('32ee6fd0','重庆新闻'),
array('a9006c72','重庆时尚'),
array('11aba543','重庆科教'),

array('bd03b751','南宁公共'),
array('4fcb0eb5','南宁影视'),


);
$idn18=700110;
$url180="https://www.tvsou.com/epg/";
$nid18=sizeof($cid18);
 
$chn="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!DOCTYPE tv SYSTEM \"http://api.torrent-tv.ru/xmltv.dtd\">\n<tv generator-info-name=\"秋哥綜合\" generator-info-url=\"https://www.tdm.com.mo/c_tv/?ch=Satellite\">\n";
for ($idm18 = 1; $idm18 <= $nid18; $idm18++){
 $idd18=$idn18+$idm18;
   $chn.="<channel id=\"".$cid18[$idm18-1][1]."\"><display-name lang=\"zh\">".$cid18[$idm18-1][1]."</display-name></channel>\n";
                                        }


// 定义频道（这里以湖南经视为例）
for ($idm18 = 1; $idm18 <= $nid18; $idm18++){
 $idd18=$idn18+$idm18;

// 获取今天的节目表
$url = 'https://www.tvsou.com/epg/'.$cid18[$idm18-1][0].'/w' . $w1;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
$hea = [
    'Host: www.tvsou.com',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0',
    'Referer: https://www.tvsou.com/epg/difang/',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1',
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $hea);
$response = curl_exec($ch);
curl_close($ch);

$response = compress_html($response);

// 提取节目表格数据
preg_match('|<li class="layui-this tabs_c">(.*?)<ul class="c_time_ul">|i', $response, $matches);

// 存储今天所有节目的开始时间
$today_times = [];
$today_programs = [];

if (isset($matches[1])) {
    $table_content = $matches[1];
    
    // 提取所有td内容
    preg_match_all('|<td>(.*?)</td>|i', $table_content, $td_matches, PREG_SET_ORDER);
    
    $td_count = count($td_matches)/2;
    
    // 先提取所有时间点和节目
    for ($i = 0; $i < $td_count - 2; $i += 3) {
        $time_html = $td_matches[$i][1] ?? '';
        $program_html = $td_matches[$i+1][1] ?? '';
        
        $time = extract_time($time_html);
        $program = extract_text($program_html);
        
        if (strlen($time) === 4) {
            $today_times[] = $time;
            $today_programs[] = $program;
        }
    }
    
    // 生成今天的节目
    for ($i = 0; $i < count($today_times); $i++) {
        $time = $today_times[$i];
        $program = $today_programs[$i];
        
        // 计算结束时间
        if ($i + 1 < count($today_times)) {
            $end_time = $today_times[$i + 1];
        } else {
            // 如果是最后一个节目，检查是否跨天
            $end_time = "0000"; // 默认到午夜
        }
        
        // 判断是否跨天（结束时间小于开始时间）
        if ($end_time < $time && $end_time !== "2359") {
            // 跨天节目，结束日期是下一天
            $chn.= "<programme start=\"" . $dt1 . $time . "00\" stop=\"" . $dt2 . $end_time . "00\" channel=\"".$cid18[$idm18-1][1]. "\">\n";

// $chn .= "<programme start=\"" . $dt1 . $start_time_formatted . " +0800\" stop=\"" . $dt2 . $end_time_formatted . " +0800\" channel=\"" . htmlspecialchars($channel_name, ENT_XML1, 'UTF-8') . "\">\n";
        } else {
            // 不跨天，结束日期是当天
            $chn .= "<programme start=\"" . $dt1 . $time . "00\" stop=\"" . $dt1 . $end_time . "00\" channel=\"".$cid18[$idm18-1][1]. "\">\n";
        }
        
        $chn .= "  <title lang=\"zh\">" . htmlspecialchars($program, ENT_XML1, 'UTF-8') . "</title>\n";
        $chn .= "  <desc lang=\"zh\"></desc>\n";
        $chn .= "</programme>\n";
    }
}

// 获取明天的节目表
$url = 'https://www.tvsou.com/epg/'.$cid18[$idm18-1][0].'/w' . ($w1 + 1);

//$url = "https://www.tvsou.com/epg/'.$cid18[$idm18-1][0].'/w" . ($w1 + 1);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
$hea = [
    'Host: www.tvsou.com',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0',
    'Referer: https://www.tvsou.com/epg/difang/',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1',
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $hea);
$response = curl_exec($ch);
curl_close($ch);

$response = compress_html($response);

// 提取节目表格数据
preg_match('|<li class="layui-this tabs_c">(.*?)<ul class="c_time_ul">|i', $response, $matches);

// 存储明天所有节目的开始时间和节目名
$tomorrow_times = [];
$tomorrow_programs = [];

if (isset($matches[1])) {
    $table_content = $matches[1];
    
    // 提取所有td内容
    preg_match_all('|<td>(.*?)</td>|i', $table_content, $td_matches, PREG_SET_ORDER);
    
    $td_count = count($td_matches)/2;
    
    // 先提取所有时间点和节目
    for ($i = 0; $i < $td_count - 2; $i += 3) {
        $time_html = $td_matches[$i][1] ?? '';
        $program_html = $td_matches[$i+1][1] ?? '';
        
        $time = extract_time($time_html);
        $program = extract_text($program_html);
        
        if (strlen($time) === 4) {
            $tomorrow_times[] = $time;
            $tomorrow_programs[] = $program;
        }
    }
    
    // 计算第二天的日期（明天的明天）
    $dt3 = date('Ymd', time() + 48 * 3600);
    
    // 生成明天的节目
    for ($i = 0; $i < count($tomorrow_times); $i++) {
        $time = $tomorrow_times[$i];
        $program = $tomorrow_programs[$i];
        
        // 计算结束时间
        if ($i + 1 < count($tomorrow_times)) {
            $end_time = $tomorrow_times[$i + 1];
        } else {
            // 如果是最后一个节目，检查是否跨天
            $end_time = "0000"; // 默认到午夜
        }
        
        // 判断是否跨天（结束时间小于开始时间）
        if ($end_time < $time && $end_time !== "2359") {
            // 跨天节目，结束日期是下一天（即dt3）
            $chn .= "<programme start=\"" . $dt2 . $time . "00\" stop=\"" . $dt3 . $end_time . "00\" channel=\"".$cid18[$idm18-1][1]. "\">\n";
        } else {
            // 不跨天，结束日期是当天
            $chn .= "<programme start=\"" . $dt2 . $time . "00\" stop=\"" . $dt2 . $end_time . "00\" channel=\"".$cid18[$idm18-1][1]. "\">\n";
        }
        
        $chn .= "  <title lang=\"zh\">" . htmlspecialchars($program, ENT_XML1, 'UTF-8') . "</title>\n";
        $chn .= "  <desc lang=\"zh\"></desc>\n";
        $chn .= "</programme>\n";
    }
}
}
$chn .= "</tv>";

// 输出XML
//echo $chn;

// 可选：保存到文件
 file_put_contents('epgtvmao.xml', $chn);
?>
