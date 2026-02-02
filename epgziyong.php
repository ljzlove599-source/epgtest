<?php
// 定义要合并的XML文件列表
$xmlFiles = [
    './epgcctv.xml',
    './epgzhongtianam.xml',
    './epgmbcae.xml',
    './epgdubaione.xml',
    './epgkbsworld.xml',
    './epgasia.xml',
    './epgdiyicaijing.xml',
    './epgnewshanghai.xml',
    './epgkai1.xml',
    './epglifetv.xml',
    './epganywhere.xml',  // 这个文件有XML格式问题
    './epgmytvsuper.xml',
    './epg4gtv2.xml', 
    './epgmacocable.xml',
    './epgtvsou.xml',
    './epgofiii.xml', 
    './epgnewhebei.xml',
    './epgnewguangdong.xml',
     './epgastro.xml',
     './epgmncvision.xml',
     './epgunifi.xml'
 ];

// 创建新的SimpleXMLElement对象作为根元素
$mergedXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tv></tv>');

// 增强版XML修复函数
function fixXmlContent($content) {
    // 1. 移除BOM (Byte Order Mark)
    $content = str_replace("\xEF\xBB\xBF", '', $content);
    
    // 2. 转换编码为UTF-8（如果可能）
    if (!mb_check_encoding($content, 'UTF-8')) {
        $encodings = ['GBK', 'GB2312', 'BIG5', 'ISO-8859-1'];
        foreach ($encodings as $enc) {
            if (mb_check_encoding($content, $enc)) {
                $content = mb_convert_encoding($content, 'UTF-8', $enc);
                break;
            }
        }
    }
    
    // 3. 移除或转义非法字符（XML 1.0允许的字符）
    $content = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $content);
    
    // 4. 修复属性引号
    $content = preg_replace_callback(
        '/<(\w+)([^>]*?)(\w+)=([\'"]?)([^\'"\s>]*?)\4([^>]*)>/',
        function($matches) {
            $tag = $matches[1];
            $before = $matches[2];
            $attrName = $matches[3];
            $quote = $matches[4];
            $attrValue = $matches[5];
            $after = $matches[6];
            
            // 如果属性值没有引号，添加双引号
            if ($quote === '') {
                $quote = '"';
            }
            
            return "<{$tag}{$before}{$attrName}={$quote}{$attrValue}{$quote}{$after}>";
        },
        $content
    );
    
    // 5. 转义未转义的 & 符号（除了已有的实体）
    $content = preg_replace('/&(?!amp;|lt;|gt;|apos;|quot;|#x?[0-9a-fA-F]+;)/', '&amp;', $content);
    
    // 6. 修复未闭合的标签（简单修复）
    $content = preg_replace('/<(\w+)[^>]*>(?!.*<\/\1>)/s', '<$1></$1>', $content);
    
    // 7. 移除无效的XML声明（如果有多个）
    $content = preg_replace('/<\?xml[^>]*>\s*/', '', $content, -1, $count);
    
    // 8. 确保只有一个XML声明
    if ($count > 1) {
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . trim($content);
    }
    
    // 9. 修复未闭合的CDATA
    $content = preg_replace_callback(
        '/<!\[CDATA\[(.*?)\]\]>/s',
        function($matches) {
            $data = $matches[1];
            // 移除可能的嵌套CDATA
            $data = str_replace('<![CDATA[', '&lt;![CDATA[', $data);
            $data = str_replace(']]>', ']]&gt;', $data);
            return '<![CDATA[' . $data . ']]>';
        },
        $content
    );
    
    // 10. 确保根元素是tv
    if (!preg_match('/<tv\b/', $content)) {
        $content = '<tv>' . $content . '</tv>';
    }
    
    return $content;
}

// 加载并清理XML文件的函数
function loadAndCleanXml($file) {
    if (!file_exists($file)) {
        error_log("文件不存在: $file");
        return false;
    }
    
    // 读取文件内容
    $content = file_get_contents($file);
    if ($content === false) {
        error_log("无法读取文件: $file");
        return false;
    }
    
    // 记录原始内容长度
    $originalLength = strlen($content);
    
    // 修复XML内容
    $cleanContent = fixXmlContent($content);
    
    // 如果内容被大量修改，记录日志
    if (abs(strlen($cleanContent) - $originalLength) > 1000) {
        error_log("文件 $file 被大量修改，可能包含严重格式问题");
    }
    
    // 尝试加载清理后的XML
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    
    // 设置libxml选项以更好地处理错误
    $xmlOptions = LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT;
    
    $xml = simplexml_load_string($cleanContent, 'SimpleXMLElement', $xmlOptions);
    
    if ($xml === false) {
        $errors = libxml_get_errors();
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = sprintf("行 %d, 列 %d: %s", $error->line, $error->column, $error->message);
        }
        error_log("XML解析失败: $file - " . implode("; ", $errorMessages));
        
        // 尝试更激进的清理
        $cleanContent = preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $cleanContent);
        $cleanContent = mb_convert_encoding($cleanContent, 'UTF-8', 'UTF-8');
        $cleanContent = iconv('UTF-8', 'UTF-8//IGNORE', $cleanContent);
        
        // 再次尝试解析
        $xml = simplexml_load_string($cleanContent, 'SimpleXMLElement', $xmlOptions);
        
        if ($xml === false) {
            error_log("重试解析也失败: $file");
            
            // 尝试手动创建基本的XML结构
            $cleanContent = '<?xml version="1.0" encoding="UTF-8"?><tv>' . 
                           preg_replace('/<tv[^>]*>|<\/tv>/i', '', $cleanContent) . 
                           '</tv>';
            $xml = simplexml_load_string($cleanContent, 'SimpleXMLElement', $xmlOptions);
            
            if ($xml === false) {
                error_log("最终解析失败: $file");
                return false;
            }
        }
    }
    
    libxml_clear_errors();
    return $xml;
}

// 处理所有XML文件
$processedFiles = 0;
$failedFiles = [];

foreach ($xmlFiles as $file) {
    // 检查文件是否存在
    if (!file_exists($file)) {
        error_log("跳过不存在的文件: $file");
        $failedFiles[] = $file;
        continue;
    }

    echo "处理文件: $file\n";
    
    // 加载XML文件（使用清理函数）
    $xml = loadAndCleanXml($file);
    if ($xml === false) {
        error_log("加载失败: $file");
        $failedFiles[] = $file;
        continue;
    }

    // 合并<channel>元素
    if (isset($xml->channel)) {
        foreach ($xml->channel as $channel) {
            $channelId = (string)$channel['id'];
            if (empty($channelId)) {
                error_log("警告: 跳过没有ID的channel: $file");
                continue;
            }
            
            // 检查是否已存在相同id的channel
            $existingChannel = $mergedXml->xpath("//channel[@id='" . htmlspecialchars($channelId) . "']");
            if (empty($existingChannel)) {
                $mergedChannel = $mergedXml->addChild('channel');
                $mergedChannel->addAttribute('id', $channelId);
                
                if (isset($channel->{'display-name'})) {
                    $displayName = htmlspecialchars((string)$channel->{'display-name'}, ENT_XML1, 'UTF-8');
                    $displayNameElement = $mergedChannel->addChild('display-name', $displayName);
                    
                    // 添加lang属性（如果存在）
                    if (isset($channel->{'display-name'}['lang'])) {
                        $displayNameElement->addAttribute('lang', (string)$channel->{'display-name'}['lang']);
                    }
                }
            }
        }
    }
    
    $processedFiles++;
}

echo "已处理 " . count($xmlFiles) . " 个文件，成功: $processedFiles 个\n";

// 再次遍历处理programme元素
foreach ($xmlFiles as $file) {
    if (!file_exists($file)) {
        continue;
    }
    
    echo "处理节目表: $file\n";
    
    $xml = loadAndCleanXml($file);
    if ($xml === false) {
        continue;
    }
    
    // 合并<programme>元素
    if (isset($xml->programme)) {
        foreach ($xml->programme as $programme) {
            $mergedProgramme = $mergedXml->addChild('programme');
            
            // 添加属性
            $mergedProgramme->addAttribute('start', (string)$programme['start']);
            $mergedProgramme->addAttribute('stop', (string)$programme['stop']);
            $mergedProgramme->addAttribute('channel', (string)$programme['channel']);
            
            // 添加title
            if (isset($programme->title)) {
                $title = htmlspecialchars((string)$programme->title, ENT_XML1, 'UTF-8');
                $titleElement = $mergedProgramme->addChild('title', $title);
                
                // 添加lang属性（如果存在）
                if (isset($programme->title['lang'])) {
                    $titleElement->addAttribute('lang', (string)$programme->title['lang']);
                }
            }
            
            // 添加desc（如果存在）
            if (isset($programme->desc)) {
                $desc = htmlspecialchars((string)$programme->desc, ENT_XML1, 'UTF-8');
                $descElement = $mergedProgramme->addChild('desc', $desc);
                
                // 添加lang属性（如果存在）
                if (isset($programme->desc['lang'])) {
                    $descElement->addAttribute('lang', (string)$programme->desc['lang']);
                }
            }
        }
    }
}

// 使用DOMDocument进行格式化输出
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;

// 设置XML声明和编码
$xmlString = $mergedXml->asXML();
$dom->loadXML($xmlString);

// 保存XML文件
$outputFile = 'epgziyong.xml';
if ($dom->save($outputFile)) {
    echo "\nXML文件合并完成，已保存为 $outputFile\n";
    
    // 创建压缩版本
    $xmlContent = file_get_contents($outputFile);
    if ($xmlContent !== false) {
        $gz = gzopen($outputFile . '.gz', 'w9');
        if ($gz !== false) {
            gzwrite($gz, $xmlContent);
            gzclose($gz);
            echo "压缩文件已保存为 " . $outputFile . ".gz\n";
        } else {
            echo "创建 gz 文件失败\n";
        }
    } else {
        echo "无法读取生成的XML文件\n";
    }
} else {
    echo "保存XML文件失败\n";
}

// 显示统计信息
$channelCount = count($mergedXml->xpath('//channel'));
$programmeCount = count($mergedXml->xpath('//programme'));
echo "\n合并统计:\n";
echo "- 频道数量: $channelCount\n";
echo "- 节目数量: $programmeCount\n";

if (!empty($failedFiles)) {
    echo "\n失败的文件:\n";
    foreach ($failedFiles as $failedFile) {
        echo "- $failedFile\n";
    }
}
?>
