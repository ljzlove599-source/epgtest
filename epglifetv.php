<?php

/**
 * EPG Extractor for lifetvprogramme.weebly.com
 * Format: YYYYMMDDHHMMSS +0800
 */

function fetch_html($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

function parse_epg($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $tables = $xpath->query("//table");
    if ($tables->length == 0) return null;

    $table = $tables->item(0);
    $rows = $table->getElementsByTagName('tr');
    if ($rows->length < 2) return null;

    $epg_data = [];
    $dates = [];

    // Extract dates from the second row (index 1)
    // Format in HTML is like "1/26", "1/27" ... "2/1"
    $date_cells = $rows->item(1)->getElementsByTagName('td');
    $current_year = 2026; // Current year based on context
    
    for ($i = 0; $i < $date_cells->length; $i++) {
        $date_str = trim($date_cells->item($i)->textContent);
        if (preg_match('/(\d+)\/(\d+)/', $date_str, $matches)) {
            $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $dates[] = $current_year . $month . $day;
        }
    }

    // Iterate through program rows starting from index 2
    for ($i = 2; $i < $rows->length; $i++) {
        $cells = $rows->item($i)->getElementsByTagName('td');
        if ($cells->length < 8) continue;

        $time_range = trim($cells->item(0)->textContent); // e.g., "00:00-01:00"
        if (preg_match('/(\d{2}):(\d{2})-(\d{2}):(\d{2})/', $time_range, $time_matches)) {
            $start_time = $time_matches[1] . $time_matches[2] . "00";
            $end_time = $time_matches[3] . $time_matches[4] . "00";
            
            for ($j = 1; $j <= 7; $j++) {
                if (isset($dates[$j-1]) && $cells->item($j)) {
                    $program_name = trim($cells->item($j)->textContent);
                    $program_name = preg_replace('/\s+/', ' ', $program_name);
                    
                    if (!empty($program_name)) {
                        $epg_data[] = [
                            'start' => $dates[$j-1] . $start_time . " +0800",
                            'stop' => $dates[$j-1] . $end_time . " +0800",
                            'title' => $program_name
                        ];
                    }
                }
            }
        }
    }

    return $epg_data;
}

function generate_xml($epg_data) {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tv></tv>');
    $xml->addAttribute('generator-info-name', 'Manus EPG Extractor');

    foreach ($epg_data as $item) {
        $programme = $xml->addChild('programme');
        $programme->addAttribute('start', $item['start']);
        $programme->addAttribute('stop', $item['stop']);
        $programme->addAttribute('channel', 'LifeTV');
        
        $title = $programme->addChild('title', htmlspecialchars($item['title']));
        $title->addAttribute('lang', 'zh');
    }

    // Format XML for better readability
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    return $dom->saveXML();
}

// Main execution
$url = 'https://lifetvprogramme.weebly.com/';
$html = fetch_html($url);
if ($html) {
    $epg_data = parse_epg($html);
    if ($epg_data) {
        $xml_string = generate_xml($epg_data);
        header('Content-Type: application/xml; charset=utf-8');
        echo $xml_string;
        
        // 保存XML到文件
        file_put_contents('epglifetv.xml', $xml_string);
    } else {
        echo "Error: Could not parse EPG data.";
    }
} else {
    echo "Error: Could not fetch website content.";
}    // Format in HTML is like "1/26", "1/27" ... "2/1"
    $date_cells = $rows->item(1)->getElementsByTagName('td');
    $current_year = 2026; // Current year based on context
    
    for ($i = 0; $i < $date_cells->length; $i++) {
        $date_str = trim($date_cells->item($i)->textContent);
        if (preg_match('/(\d+)\/(\d+)/', $date_str, $matches)) {
            $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $dates[] = $current_year . $month . $day;
        }
    }

    // Iterate through program rows starting from index 2
    for ($i = 2; $i < $rows->length; $i++) {
        $cells = $rows->item($i)->getElementsByTagName('td');
        if ($cells->length < 8) continue;

        $time_range = trim($cells->item(0)->textContent); // e.g., "00:00-01:00"
        if (preg_match('/(\d{2}):(\d{2})-(\d{2}):(\d{2})/', $time_range, $time_matches)) {
            $start_time = $time_matches[1] . $time_matches[2] . "00";
            $end_time = $time_matches[3] . $time_matches[4] . "00";
            
            for ($j = 1; $j <= 7; $j++) {
                if (isset($dates[$j-1]) && $cells->item($j)) {
                    $program_name = trim($cells->item($j)->textContent);
                    $program_name = preg_replace('/\s+/', ' ', $program_name);
                    
                    if (!empty($program_name)) {
                        $epg_data[] = [
                            'start' => $dates[$j-1] . $start_time . " +0800",
                            'stop' => $dates[$j-1] . $end_time . " +0800",
                            'title' => $program_name
                        ];
                    }
                }
            }
        }
    }

    return $epg_data;
}

function generate_xml($epg_data) {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tv></tv>');
    $xml->addAttribute('generator-info-name', 'Manus EPG Extractor');

    foreach ($epg_data as $item) {
        $programme = $xml->addChild('programme');
        $programme->addAttribute('start', $item['start']);
        $programme->addAttribute('stop', $item['stop']);
        $programme->addAttribute('channel', 'LifeTV');
        
        $title = $programme->addChild('title', htmlspecialchars($item['title']));
        $title->addAttribute('lang', 'zh');
    }

    // Format XML for better readability
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    return $dom->saveXML();
}

// Main execution
$url = 'https://lifetvprogramme.weebly.com/';
$html = fetch_html($url);
if ($html) {
    $epg_data = parse_epg($html);
    if ($epg_data) {
        header('Content-Type: application/xml; charset=utf-8');
        echo generate_xml($epg_data);
        
         $file_put_contents('epglifetv.xml', $epg_data);
    } else {
        echo "Error: Could not parse EPG data.";
    }
} else {
    echo "Error: Could not fetch website content.";
}
