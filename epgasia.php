<?php
header('Content-Type: text/plain; charset=UTF-8');
//header('Content-Type:text /html; charset=UTF-8');
ini_set("max_execution_time", "3000000");
//htaccess php_value max_execution_time 0;
ini_set('date.timezone','Asia/Shanghai');

$fp = "epgasia.xml"; // 压缩版本的扩展名后加.gz
$dt1 = date('Ymd'); // 獲取當前日期
$dt2 = date('Ymd', time() + 24 * 3600); // 第二天日期
$dt21 = date('Ymd', time() + 48 * 3600); // 第三天日期
$dt36 = date('Ymd', time() - 24 * 3600); // 前天日期
$dt3 = date('Ymd', time() + 7 * 24 * 3600);
$dt4 = date("Y/n/j"); // 獲取當前日期
$dt5 = date('Y/n/j', time() + 24 * 3600); // 第二天日期
$dt7 = date('Y'); // 獲取當前日期
$dt6 = date('YmdHi', time());
$dt111 = date('Y-m-d');
$time1111 = strtotime(date('Y-m-d', time())) * 1000;
$dt12 = date('Y-m-d', time() + 24 * 3600);
$dt10 = date('Y-m-d', time() - 24 * 3600);
$w1 = date("w"); // 當前第幾周
if ($w1 < '1') {
    $w1 = 7;
}
$w2 = $w1 + 1;

function match_string($matches) {
    return iconv('UCS-2', 'UTF-8', pack('H4', $matches[1]));
    //return  iconv('UCS-2BE', 'UTF-8', pack('H4', $matches[1]));
    //return  iconv('UCS-2LE', 'UTF-8', pack('H4', $matches[1]));
}

function compress_html($string) {
    $string = str_replace("\r", '', $string); // 清除换行符
    $string = str_replace("\n", '', $string); // 清除换行符
    $string = str_replace("\t", '', $string); // 清除制表符
    return $string;
}

function escape($str) {
    preg_match_all("/[\x80-\xff].|[\x01-\x7f]+/", $str, $r);
    $ar = $r[0];
    foreach ($ar as $k => $v) {
        if (ord($v[0]) < 128) {
            $ar[$k] = rawurlencode($v);
        } else {
            $ar[$k] = "%u" . bin2hex(iconv("UTF-8", "UCS-2", $v));
        }
    }
    return join("", $ar);
}

// 適合php7以上
function replace_unicode_escape_sequence($match) {
    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
}

$dt1 = date('Ymd'); // 獲取當前日期
$dt2 = date('Ymd', time() + 24 * 3600); // 第二天日期
$w1 = date("w"); // 當前第幾周

// 获取网页内容
$url36 = 'https://www.asiasatv.com/zb';
$ch36 = curl_init();
curl_setopt($ch36, CURLOPT_URL, $url36);
curl_setopt($ch36, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch36, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch36, CURLOPT_SSL_VERIFYHOST, FALSE);
curl_setopt($ch36, CURLOPT_ENCODING, 'Vary: Accept-Encoding');
$html36 = curl_exec($ch36);
curl_close($ch36);
$html36 = preg_replace('/\s(?=)/', '', $html36);

// 使用正则表达式提取 jsonData 变量内容
preg_match('/varjsonData=(.*?)showData/i', $html36, $matches36);

// 解析JSON数据
$data36 = json_decode($matches36[1], true);

if ($data36 === null) {
    die("JSON解析失败");
}

// 纯PHP生成XML的函数
function generateXML($data36) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<!DOCTYPE tv SYSTEM "http://api.torrent-tv.ru/xmltv.dtd">' . "\n";
    $xml .= '<tv generator-info-name="TBC有線" generator-info-url="https://www.tbc.net.tw/Epg/Epg/indexV2/0/1">' . "\n";
    
    // 添加频道信息
    $xml .= '  <channel id="亞洲衛視">' . "\n";
    $xml .= '    <display-name>亞洲衛視</display-name>' . "\n";
    $xml .= '  </channel>' . "\n";
    
    // 获取本周一的日期
    $baseDate = date('Ymd', strtotime('monday this week'));
    
    // 处理每一天的数据
    foreach ($data36 as $dayNumber => $dayPrograms) {
        // 跳过空数据
        if (empty($dayPrograms)) {
            continue;
        }
        
        // 计算当前节目对应的日期
        // 因为数组从0开始，0对应周一，所以需要减1
        $date = date('Ymd', strtotime($baseDate . ' + ' . ($dayNumber) . ' days'));
        
        // 添加每个节目
        foreach ($dayPrograms as $program) {
            // 跳过空节目
            if (empty($program['title']) || empty($program['start_time']) || empty($program['end_time'])) {
                continue;
            }
            
            // 构建完整的开始时间
            $startTime = $date . str_replace(':', '', $program['start_time']) . '00 +0800';
            
            // 处理结束时间
            if ($program['end_time'] === '00:00') {
                // 如果是00:00，可能是第二天
                $endDate = date('Ymd', strtotime($date . ' +1 day'));
                $stopTime = $endDate . '000000 +0800';
            } else {
                $stopTime = $date . str_replace(':', '', $program['end_time']) . '00 +0800';
            }
            
            // 添加节目信息
            $xml .= '  <programme start="' . $startTime . '" stop="' . $stopTime . '" channel="亞洲衛視">' . "\n";
            $xml .= '    <title>' . htmlspecialchars($program['title'], ENT_XML1, 'UTF-8') . '</title>' . "\n";
            $xml .= '    <desc>节目描述</desc>' . "\n";
            $xml .= '    <category>综艺</category>' . "\n";
            
            // 如果有节目ID则添加
            if (!empty($program['id'])) {
                $xml .= '    <episode-num>' . htmlspecialchars($program['id'], ENT_XML1, 'UTF-8') . '</episode-num>' . "\n";
            } else {
                $xml .= '    <episode-num></episode-num>' . "\n";
            }
            
            $xml .= '  </programme>' . "\n";
        }
    }
    
    $xml .= '</tv>';
    return $xml;
}

// 生成XML内容
$xmlContent = generateXML($data36);

// 输出XML
header('Content-Type: application/xml; charset=utf-8');
echo $xmlContent;

// 保存到文件
file_put_contents('epg.xml', $xmlContent);

// 如果需要保存压缩版本
if (strpos($fp, '.gz') !== false) {
    file_put_contents($fp, gzencode($xmlContent, 9));
} else {
    file_put_contents($fp, $xmlContent);
}

// 记录日志
$log = date('Y-m-d H:i:s') . " - XML生成完成，大小: " . strlen($xmlContent) . " 字节\n";
file_put_contents('epg_log.txt', $log, FILE_APPEND);
?>