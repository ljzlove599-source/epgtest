<?php
header('Content-Type: text/plain; charset=UTF-8');
ini_set("max_execution_time", "900");
ini_set('date.timezone','Asia/Shanghai');
ini_set('memory_limit', '512M');
$dt1=date('Y-m-d');//獲取當前日期
$dt18=date('Y');//獲取當前日期
$dt2=date('Y-m-d',time()+24*3600);//第二天日期
$dt21=date('Ymd',time()+48*3600);//第三天日期
$dt3=date('Ymd',time()+6*24*3600);

$dt11=date('Ymd');//獲取當前日期
$dt12=date('Ymd',time()+24*3600);//第二天日期
$dt13=date('Ymd',time()+48*3600);//第二天日期
/**
 * 生命電視台 EPG 抓取工具 (PHP 7.4)
 * 來源: https://lifetvprogramme.weebly.com/
 */

//header('Content-Type: application/xml; charset=utf-8');

class LifeTV_EPG {
    private $url = 'https://lifetvprogramme.weebly.com/';

    public function fetch() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) {
            return $this->errorXML("無法獲取網頁內容");
        }

        return $this->parse($html);
    }

    private function parse($html) {
        $doc = new DOMDocument();
        // 忽略 HTML5 標籤錯誤
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        // 尋找包含節目表的表格
        $tables = $xpath->query("//table[contains(@class, 'wsite-multicol-table')] | //table");
        
        if ($tables->length === 0) {
            return $this->errorXML("找不到節目表表格");
        }

        // 通常第一個或特定的表格是節目表
        $table = $tables->item(0);
        $rows = $table->getElementsByTagName('tr');

        $epgData = [];
        $dates = [];

        // 解析日期行 (通常在第2行)
        if ($rows->length > 1) {
            $dateRow = $rows->item(1);
            $dateCols = $dateRow->getElementsByTagName('td');
            for ($i = 0; $i < $dateCols->length; $i++) {
                $dateText = trim($dateCols->item($i)->textContent);
                if (preg_match('/\d+\/\d+/', $dateText)) {
                    $dates[] = $dateText;
                }
            }
        }

        // 如果沒抓到日期，嘗試從標題或預設
        if (empty($dates)) {
            $currentYear = date('Y');
            for ($i = 0; $i < 7; $i++) {
                $dates[] = date('n/j', strtotime("+$i days"));
            }
        }

        // 解析節目行
        for ($i = 2; $i < $rows->length; $i++) {
            $row = $rows->item($i);
            $cols = $row->getElementsByTagName('td');
            
            if ($cols->length < 2) continue;

            $timeRange = trim($cols->item(0)->textContent);
            if (!preg_match('/\d{2}:\d{2}-\d{2}:\d{2}/', $timeRange)) continue;

            list($startStr, $endStr) = explode('-', $timeRange);

            for ($j = 1; $j < $cols->length - 1; $j++) {
                $programName = trim($cols->item($j)->textContent);
                if (empty($programName) || $programName == $timeRange) continue;

                $dayIndex = $j - 1;
                if (isset($dates[$dayIndex])) {
                    $date = $dates[$dayIndex];
                    $epgData[] = [
                        'date' => $date,
                        'start' => $startStr,
                        'end' => $endStr,
                        'title' => str_replace(["\n", "\r", "\t"], " ", $programName)
                    ];
                }
            }
        }

        return $this->generateXML($epgData);
    }

    private function generateXML($data) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tv/>');
        $xml->addAttribute('generator-info-name', 'LifeTV EPG Scraper');
        
        $channel = $xml->addChild('channel');
        $channel->addAttribute('id', 'lifetv');
        $channel->addChild('display-name', '生命電視台');

        $currentYear = date('Y');

        foreach ($data as $item) {
            $programme = $xml->addChild('programme');
            $programme->addAttribute('channel', 'lifetv');
            
            // 格式化時間為 XMLTV 格式: YYYYMMDDHHMMSS +0800
            $start = $this->formatTime($currentYear, $item['date'], $item['start']);
            $end = $this->formatTime($currentYear, $item['date'], $item['end']);
            
            $programme->addAttribute('start', $start . ' +0800');
            $programme->addAttribute('stop', $end . ' +0800');
            
            $title = $programme->addChild('title', htmlspecialchars($item['title']));
            $title->addAttribute('lang', 'zh');
        }

        return $xml->asXML();
    }

    private function formatTime($year, $dateStr, $timeStr) {
        // dateStr: "1/5", timeStr: "00:00"
        list($month, $day) = explode('/', $dateStr);
        list($hour, $min) = explode(':', $timeStr);
        return sprintf("%04d%02d%02d%02d%02d00", $year, $month, $day, $hour, $min);
    }

    private function errorXML($msg) {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?><error>$msg</error>";
    }
}

$scraper = new LifeTV_EPG();
$xml_string =  $scraper->fetch();
 file_put_contents('epglifetv.xml', $xml_string);
