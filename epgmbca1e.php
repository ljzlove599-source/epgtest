<?php

/**
 * Multi-Channel EPG Generator for elcinema.com
 * Supports channels: 1130 (MBC Action), 1132 (MBC Max), 1128 (MBC 2)
 * Handles PM to AM transition for date increment.
 */

// Set header for XML output if running in a web server
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/xml; charset=utf-8');
}

/**
 * Fetch HTML content from URL
 */
function fetch_epg($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $html = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);
    return $html;
}

/**
 * Parse EPG data from HTML
 */
function parse_epg($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    // Try to find the base date from the page
    $base_date = new DateTime();
    $date_header = $xpath->query("//h3[contains(text(), '202')] | //div[contains(@class, 'day-header')]");
    if ($date_header->length > 0) {
        try {
            $parsed_date = new DateTime(trim($date_header->item(0)->textContent));
            if ($parsed_date) $base_date = $parsed_date;
        } catch (Exception $e) {}
    }

    $nodes = $xpath->query("//div[contains(@class, 'boxed-category-')]");
    $programs = [];
    $last_hour = -1;
    $day_offset = 0;

    foreach ($nodes as $node) {
        // Extract Title
        $title = "";
        $title_paths = [
            ".//ul[contains(@class, 'no-margin')]/li[1]",
            ".//div[contains(@class, 'small-7')]//li[1]",
            ".//div[contains(@class, 'small-6')]//li[1]",
            ".//a[contains(@href, '/work/')]"
        ];
        foreach ($title_paths as $path) {
            $t_nodes = $xpath->query($path, $node);
            if ($t_nodes->length > 0) {
                $title = trim($t_nodes->item(0)->textContent);
                if (!empty($title)) break;
            }
        }

        // Extract Time
        $time_str = "";
        $time_paths = [
            ".//ul[contains(@class, 'text-center')]/li[1]",
            ".//ul[contains(@class, 'no-margin')]/li[2]",
            ".//li[contains(text(), 'AM') or contains(text(), 'PM')]"
        ];
        foreach ($time_paths as $path) {
            $tm_nodes = $xpath->query($path, $node);
            foreach ($tm_nodes as $tm_node) {
                if (preg_match('/(\d{2}:\d{2}\s*(?:AM|PM))/i', $tm_node->textContent, $matches)) {
                    $time_str = $matches[1];
                    break 2;
                }
            }
        }

        // Extract Duration
        $duration = 60;
        $dur_nodes = $xpath->query(".//span[contains(@class, 'subheader')]", $node);
        if ($dur_nodes->length > 0) {
            if (preg_match('/(\d+)/', $dur_nodes->item(0)->textContent, $matches)) {
                $duration = (int)$matches[1];
            }
        }

        if (empty($title) || empty($time_str)) continue;

        // Handle PM to AM transition
        $time_obj = DateTime::createFromFormat('h:i A', $time_str);
        if (!$time_obj) continue;
        
        $hour_24 = (int)$time_obj->format('G');
        if ($last_hour >= 12 && $hour_24 < 12) {
            $day_offset++;
        }
        $last_hour = $hour_24;

        $program_date = clone $base_date;
        if ($day_offset > 0) $program_date->modify("+$day_offset days");
        
        $start_dt = new DateTime($program_date->format('Y-m-d') . ' ' . $time_obj->format('H:i:s'));
        $end_dt = clone $start_dt;
        $end_dt->modify("+$duration minutes");

        $programs[] = [
            'title' => $title,
            'start' => $start_dt->format('YmdHis O'),
            'stop' => $end_dt->format('YmdHis O'),
        ];
    }
    return $programs;
}

// Define channels to scrape
$channels = [
    '1130' => ['id' => 'mbc action', 'name' => 'mbc action'],
    '1132' => ['id' => 'mbc max', 'name' => 'mbc max'],
    '1128' => ['id' => 'mbc 2', 'name' => 'mbc 2'],
];

// Initialize XML
$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tv/>');
$xml->addAttribute('generator-info-name', 'Manus Multi-Channel EPG Generator');

$all_programmes = [];

foreach ($channels as $code => $info) {
    // Add channel info to XML
    $channel_node = $xml->addChild('channel');
    $channel_node->addAttribute('id', $info['id']);
    $channel_node->addChild('display-name', htmlspecialchars($info['name']));

    // Fetch and parse
    $url = "https://elcinema.com/en/tvguide/$code/";
    $html = fetch_epg($url);
    if (!empty($html)) {
        $programs = parse_epg($html);
        foreach ($programs as $prog) {
            $prog['channel_id'] = $info['id'];
            $all_programmes[] = $prog;
        }
    }
}

// Add all programmes to XML
foreach ($all_programmes as $prog) {
    $programme = $xml->addChild('programme');
    $programme->addAttribute('start', $prog['start']);
    $programme->addAttribute('stop', $prog['stop']);
    $programme->addAttribute('channel', $prog['channel_id']);
    
    $title = $programme->addChild('title', htmlspecialchars($prog['title']));
    $title->addAttribute('lang', 'en');
}

// Output formatted XML
$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());
//echo $dom->saveXML();

// 輸出 XML
$xml_output = $dom->saveXML();
file_put_contents('epgmbcae.xml', $xml_output);
//echo $xml_output ;
// 输出成功信息
//echo "EPG XML generated successfully in epg.xml\n";
//echo "Generated EPG for " . count($channels) . " channels:\n";
/*
foreach ($channels as $channel) {
    echo "- " . $channel[1] . " (ID: " . $channel[0] . ")\n";
}
*/
?>
