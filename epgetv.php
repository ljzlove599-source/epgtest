<?php
// ==========================================
// ETTV America EPG Scraper (XMLTV Format)
// ==========================================

header('Content-Type: text/xml; charset=utf-8');
set_time_limit(300); // 延長執行時間限制
date_default_timezone_set('UTC'); // 內部運算使用 UTC

class EttvScraper {
    private $baseUrl = "https://www.ettvamerica.com/Schedule/Schedule?Id=";
    
    // 來源時區：東森美洲通常顯示美西時間 (PST/PDT)
    // 如果您發現時間差 3 小時，請改為 'America/New_York'
    private $sourceTimezone = 'America/Los_Angeles';

    // 預定義頻道列表 (根據常見 ID 推測)
    private $channels = [
        '201' => 'ETTV America (東森美洲台)',
        '202' => 'ETTV Drama (東森戲劇台)',
        '203' => 'ETTV News (東森新聞台)',
        '204' => 'ETTV Financial (東森財經台)',
        '205' => 'ETTV Super (東森衛視台)',
        '206' => 'ETTV Yoyo (東森幼幼台)',
        // 若有其他頻道可自行追加，例如三立國際: '7' => 'SETI'
    ];

    public function run() {
        // 1. 初始化 XML
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tv></tv>');
        $xml->addAttribute('generator-info-name', 'ETTV Scraper 2.0');
        $xml->addAttribute('generator-info-url', 'https://www.ettvamerica.com/');

        // 2. 建立頻道資訊 (Channel Header)
        foreach ($this->channels as $id => $name) {
            $chan = $xml->addChild('channel');
            $chan->addAttribute('id', $id);
            $dn = $chan->addChild('display-name', htmlspecialchars($name));
            $dn->addAttribute('lang', 'zh');
        }

        // 3. 爬取每個頻道的節目
        foreach ($this->channels as $id => $name) {
            $this->scrapeChannel($id, $xml);
            // 禮貌性延遲，避免對伺服器造成負擔
            usleep(500000); // 0.5秒
        }

        // 4. 輸出結果
        echo $xml->asXML();
    }

    private function scrapeChannel($id, $xml) {
        $url = $this->baseUrl . $id;
        $html = $this->fetchUrl($url);

        if (empty($html)) return;

        // 使用 DOMDocument 解析
        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // 忽略 HTML 語法錯誤
        @$dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // --- 策略 A: 抓取網頁上的日期 ---
        // 嘗試尋找顯示日期的區塊，若找不到則使用「今天」
        // 假設網頁上有 <span id="lblDate"> 或類似結構
        $baseDate = date('Y-m-d'); 
        $dateNodes = $xpath->query("//span[contains(@id, 'Date')] | //div[contains(@class, 'date')]");
        if ($dateNodes->length > 0) {
            // 嘗試解析網頁上的日期文字
            $pageDateStr = trim($dateNodes->item(0)->nodeValue);
            // 這裡可能需要根據網頁實際顯示的格式 (如 "2026/02/10") 進行解析
            $parsedDate = strtotime($pageDateStr);
            if ($parsedDate) {
                $baseDate = date('Y-m-d', $parsedDate);
            }
        }

        // --- 策略 B: 模糊搜尋節目行 ---
        // 由於我們不知道確切的 class，我們搜尋所有包含「時間格式」的節點
        // 常見結構是 tr > td 或 div > div
        
        // 抓取所有可能是時間的節點 (格式如 09:00 PM, 9:30 AM)
        $timePattern = "/\d{1,2}:\d{2}\s?(?:AM|PM|am|pm)/";
        
        // 這是一個比較暴力的抓法：抓取所有文字節點
        $textNodes = $xpath->query("//text()");
        
        $programs = [];
        $lastTimeObj = null;

        // 用來暫存找到的數據
        $foundItems = [];

        foreach ($textNodes as $node) {
            $text = trim($node->nodeValue);
            if (empty($text)) continue;

            // 檢查這段文字是否像時間
            if (preg_match($timePattern, $text, $matches)) {
                // 找到時間了！
                $timeStr = $matches[0];
                
                // 嘗試找這個時間旁邊的標題 (通常是下一個 DOM 節點或父節點的下一個兄弟)
                // 這裡簡化處理：假設時間節點的「父元素的下一個兄弟元素」或「同層下一個文字」是標題
                $title = "";
                
                // 嘗試 1: 同一個父層的其他節點 (如 td 1 是時間, td 2 是標題)
                $parent = $node->parentNode;
                $sibling = $parent->nextSibling;
                while ($sibling) {
                    $sibText = trim($sibling->nodeValue);
                    if (!empty($sibText) && $sibText !== $text) {
                        $title = $sibText;
                        break;
                    }
                    $sibling = $sibling->nextSibling;
                }

                // 如果標題太短或無效，可能是格式不對，跳過
                if (strlen($title) < 2) continue;

                $foundItems[] = [
                    'time' => $timeStr,
                    'title' => $title
                ];
            }
        }

        // --- 處理時間與跨日 ---
        $currentDate = $baseDate;
        $prevTimestamp = 0;

        foreach ($foundItems as $item) {
            // 組合日期與時間
            $dateTimeStr = $currentDate . ' ' . $item['time'];
            
            try {
                $dt = new DateTime($dateTimeStr, new DateTimeZone($this->sourceTimezone));
                $timestamp = $dt->getTimestamp();

                // 跨日邏輯：如果目前節目的時間 比 上一個節目時間早 (例如 11PM 之後接 1AM)
                // 代表這已經是第二天了
                if ($prevTimestamp > 0 && $timestamp < $prevTimestamp) {
                    $dt->modify('+1 day');
                    $timestamp = $dt->getTimestamp();
                    // 更新基準日期，以防後續還有節目
                    $currentDate = $dt->format('Y-m-d');
                }
                
                $prevTimestamp = $timestamp;

                // 轉為 UTC
                $dt->setTimezone(new DateTimeZone('UTC'));
                // 格式化為 XMLTV 要求：YmdHis +0000
                $startFormatted = $dt->format('YmdHis +0000');

                // 寫入 XML
                $prog = $xml->addChild('programme');
                $prog->addAttribute('start', $startFormatted);
                // 結束時間通常需下一筆資料推算，這裡暫時留空或設為相同
                // $prog->addAttribute('stop', ...); 
                $prog->addAttribute('channel', $id);
                
                $t = $prog->addChild('title', htmlspecialchars($item['title']));
                $t->addAttribute('lang', 'zh');

            } catch (Exception $e) {
                continue;
            }
        }
    }

    private function fetchUrl($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 偽裝成一般瀏覽器
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}

// --- 執行爬蟲 ---
$scraper = new EttvScraper();
$scraper->run();
?>
