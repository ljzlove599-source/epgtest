<?php
header('Content-Type: text/plain; charset=UTF-8');
ini_set("max_execution_time", "900");
ini_set('date.timezone','Asia/Shanghai');
ini_set('memory_limit', '512M');
$dt1=date('Y-m-d');//獲取當前日期
$dt18=date('Y');//獲取當前日期
$dt2=date('Y-m-d',time()+24*3600);//第二天日期
$dt21=date('Y-m-d',time()+48*3600);//第三天日期
$dt3=date('Ymd',time()+6*24*3600);

$dt11=date('Ymd');//獲取當前日期
$dt12=date('Ymd',time()+24*3600);//第二天日期
$dt13=date('Ymd',time()+48*3600);//第二天日期
$fp = 'epgunifi.xml'; // 定义输出文件名

$chn = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!DOCTYPE tv SYSTEM \"http://api.torrent-tv.ru/xmltv.dtd\">\n<tv generator-info-name=\"http://192.168.10.1/system/opt/\" generator-info-url=\"QQ\">\n";

// 获取频道列表
for ($k7 = 0; $k7 <= 7; $k7++) {
//https://data-store-cdn.api.tmcms.quickplay.com/content/epg?start=2026-01-08T16:00:00.000Z&end=2026-01-09T16:00:00Z&reg=my&dt=web&client=tm-unifitv-web&seg=cohort4&pf=regular&locale=en&pageSize=10&pageNumber=' . $k7

    $url7 = 'https://data-store-cdn.api.tmcms.quickplay.com/content/epg?start='.$dt1.'T16:00:00.000Z&end='.$dt2.'T16:00:00Z&reg=my&dt=web&client=tm-unifitv-web&seg=cohort4&pf=regular&locale=en&pageSize=10&pageNumber=' . $k7;

    $ch7 = curl_init();
    curl_setopt($ch7, CURLOPT_URL, $url7);
    curl_setopt($ch7, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch7, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch7, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch7, CURLOPT_TIMEOUT, 30);
    $re7 = curl_exec($ch7);
    curl_close($ch7);

    if ($re7 === false) {
        continue;
    }

    $re7 = str_replace('&', '&amp;', $re7);
    $json7 = json_decode($re7);

    if (!isset($json7->data)) {
        continue;
    }

    $data7 = $json7->data;
    $tuu7 = count($data7);

    // 首先收集所有频道
    $channels = [];
    for ($i7 = 0; $i7 < $tuu7; $i7++) {
        $cid = $data7[$i7]->cid; // 频道ID
        $cs = $data7[$i7]->cs; // 电视台名称
        
        // 存储频道信息
        $channels[$cid] = $cs;
        
        // 添加到XML
        $chn .= "<channel id=\"" . htmlspecialchars($cs, ENT_XML1, 'UTF-8') . "\">\n";
        $chn .= "<display-name lang=\"zh\">" . htmlspecialchars($cs, ENT_XML1, 'UTF-8') . "</display-name>\n";
        $chn .= "</channel>\n";
    }

    // 然后为每个频道获取节目表
    foreach ($channels as $cid => $cs) {

        $url71 = 'https://data-store-cdn.api.tmcms.quickplay.com/content/epg?start='.$dt1.'T00:00:00.000Z&end='.$dt2.'T00:00:00Z&reg=my&dt=web&client=tm-unifitv-web&seg=cohort4&pf=regular&locale=en&channel=' . $cid . '&pageSize=10&pageNumber=1';

        $ch71 = curl_init();
        curl_setopt($ch71, CURLOPT_URL, $url71);
        curl_setopt($ch71, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch71, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch71, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch71, CURLOPT_TIMEOUT, 30);
        $re71 = curl_exec($ch71);
        curl_close($ch71);

        if ($re71 === false) {
            continue;
                                         }

        $re71 = str_replace('&', '&amp;', $re71);
        $json71 = json_decode($re71);

        if (!isset($json71->data[0]->airing)) {
            continue;
                                                            }

        $data71 = $json71->data[0]->airing;
        $tuu71 = count($data71);

        for ($i71 = 0; $i71 < $tuu71; $i71++) {
            if (!isset($data71[$i71]->pgm->loepn[0]->n)) {
                continue;
                                                                                  }
            
            $programName = $data71[$i71]->pgm->loepn[0]->n; // 节目名称
            $sc_st_dt = $data71[$i71]->sc_st_dt; // 开始时间
            $sc_ed_dt = $data71[$i71]->sc_ed_dt; // 结束时间

            // 格式化时间（移除不需要的字符）
            $start_time = str_replace([':', '-', 'T', 'Z'], '', $sc_st_dt) . ' +0800';
            $stop_time = str_replace([':', '-', 'T', 'Z'], '', $sc_ed_dt) . ' +0800';

            $chn .= "<programme start=\"" . $start_time . "\" stop=\"" . $stop_time . "\" channel=\"" . htmlspecialchars($cs, ENT_XML1, 'UTF-8') . "\">\n";
            $chn .= "<title lang=\"zh\">" . htmlspecialchars($programName, ENT_XML1, 'UTF-8') . "</title>\n";
            $chn .= "<desc lang=\"zh\"></desc>\n";
            $chn .= "</programme>\n";
                                                                       }
 

   $url72 = 'https://data-store-cdn.api.tmcms.quickplay.com/content/epg?start='.$dt2.'T00:00:00.000Z&end='.$dt21.'T00:00:00Z&reg=my&dt=web&client=tm-unifitv-web&seg=cohort4&pf=regular&locale=en&channel=' . $cid . '&pageSize=10&pageNumber=1';
//https://data-store-cdn.api.tmcms.quickplay.com/content/epg?start=2026-01-09T16:00:00.000Z&end=2026-01-10T16:00:00Z&reg=my&dt=web&client=tm-unifitv-web&seg=cohort4&pf=regular&locale=en&channel=F01074B2-F6DD-441B-9477-1DF50C081978&pageSize=10&pageNumber=1
        $ch72 = curl_init();
        curl_setopt($ch72, CURLOPT_URL, $url72);
        curl_setopt($ch72, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch72, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch72, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch72, CURLOPT_TIMEOUT, 30);
        $re72 = curl_exec($ch72);
        curl_close($ch72);

        if ($re72 === false) {
            continue;
                                  }

        $re72 = str_replace('&', '&amp;', $re72);
        $json72 = json_decode($re72);

        if (!isset($json72->data[0]->airing)) {
            continue;
                                                        }

        $data72 = $json72->data[0]->airing;
        $tuu72 = count($data72);

        for ($i72 = 0; $i72 < $tuu72; $i72++) {
            if (!isset($data72[$i72]->pgm->loepn[0]->n)) {
                continue;
                                                                                 }
            
            $programName72 = $data72[$i72]->pgm->loepn[0]->n; // 节目名称
            $sc_st_dt72 = $data72[$i72]->sc_st_dt; // 开始时间
            $sc_ed_dt72 = $data72[$i72]->sc_ed_dt; // 结束时间

            // 格式化时间（移除不需要的字符）
            $start_time72 = str_replace([':', '-', 'T', 'Z'], '', $sc_st_dt72) . ' +0800';
            $stop_time72 = str_replace([':', '-', 'T', 'Z'], '', $sc_ed_dt72) . ' +0800';

            $chn .= "<programme start=\"" . $start_time72 . "\" stop=\"" . $stop_time72 . "\" channel=\"" . htmlspecialchars($cs, ENT_XML1, 'UTF-8') . "\">\n";
            $chn .= "<title lang=\"zh\">" . htmlspecialchars($programName72, ENT_XML1, 'UTF-8') . "</title>\n";
            $chn .= "<desc lang=\"zh\"></desc>\n";
            $chn .= "</programme>\n";
                                                           }

  } 








}

$chn .= "</tv>\n";

// 写入文件
if (file_put_contents($fp, $chn) === false) {
    echo "写入文件失败！";
} else {
    echo "EPG XML 文件已生成: " . $fp;
}