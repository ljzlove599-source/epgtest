<?php
header( 'Content-Type: html/text; charset=UTF-8');
ini_set("max_execution_time", "3000000");
ini_set('date.timezone','Asia/Shanghai');
$fp="epgcctv.xml";//压缩版本的扩展名后加.gz
$dt1=date('Ymd');//獲取當前日期
$dt2=date('Ymd',time()+24*3600);//第二天日期
$dt21=date('Ymd',time()+48*3600);//第三天日期
$dt22=date('Ymd',time()-24*3600);//前天日期
$dt3=date('Ymd',time()+7*24*3600);
$dt11=date('Y-m-d');
$w1=date("w");//當前第幾周
if ($w1<'1') {$w1=7;}
$w2=$w1+1;
function compress_html($string) {
    $string = str_replace("\r", '', $string); //清除换行符
    $string = str_replace("\n", '', $string); //清除换行符
    $string = str_replace("\t", '', $string); //清除制表符
    return $string;
}

function escape($str) 
{ 
preg_match_all("/[\x80-\xff].|[\x01-\x7f]+/",$str,$r); 
$ar = $r[0]; 
foreach($ar as $k=>$v) 
{ 
if(ord($v[0]) < 128) 
$ar[$k] = rawurlencode($v); 
else 
$ar[$k] = "%u".bin2hex(iconv("UTF-8","UCS-2",$v)); 
} 
return join("",$ar); 
} 
//適合php7以上
function replace_unicode_escape_sequence($match)
{       
		return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');     
}          


$chn="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!DOCTYPE tv SYSTEM \"http://api.torrent-tv.ru/xmltv.dtd\">\n<tv generator-info-name=\"秋哥綜合\" generator-info-url=\"https://www.tdm.com.mo/c_tv/?ch=Satellite\">\n";

//1澳門電視台官網 10001-100005 正確
$id1=100000;//起始节目编号
$cid1=array(
    array('cctvamerica','CCTV-4美洲頻道'),

    array('cctveurope','CCTV-4歐洲頻道'),

);

$nid1=sizeof($cid1);
for ($idm1 = 1; $idm1 <= $nid1; $idm1++){
 $idd1=$id1+$idm1;
   $chn.="<channel id=\"".$cid1[$idm1-1][1]."\"><display-name lang=\"zh\">".$cid1[$idm1-1][1]."</display-name></channel>\n";
         
}

for ($idm1 = 1; $idm1 <= $nid1; $idm1++){
$idd1=$id1+$idm1;
  $url1='https://api.cntv.cn/epg/getEpgInfoByChannelNew?c='.$cid1[$idm1-1][0].'&serviceId=tvcctv&d='.$dt1.'&t=jsonp&cb=setItem1';
//https://api.cntv.cn/epg/getEpgInfoByChannelNew?c=cctveurope&serviceId=tvcctv&d=20260202&t=jsonp&cb=setItem1
//https://api.cntv.cn/epg/getEpgInfoByChannelNew?c=cctv1&serviceId=tvcctv&d=20260202&t=jsonp&cb=setItem1
//print  $url1;
$ch1 = curl_init ();
curl_setopt ( $ch1, CURLOPT_URL, $url1 );

//curl_setopt ( $ch1, CURLOPT_HEADER, $hea );
curl_setopt($ch1,CURLOPT_USERAGENT,'Mozilla/5.0 (Linux; Android 8.1.0; JKM-AL00b) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Mobile Safari/537.36):');
curl_setopt($ch1,CURLOPT_SSL_VERIFYPEER,false);
curl_setopt($ch1,CURLOPT_SSL_VERIFYHOST,false);
curl_setopt ( $ch1, CURLOPT_RETURNTRANSFER, 1 );
    //curl_setopt($ch1,CURLOPT_ENCODING,'Vary: Accept-Encoding');
    $re1 = curl_exec($ch1);
   curl_close($ch1);
preg_match_all('|"startTime":(.*?),|i',$re1,$um1,PREG_SET_ORDER);//播放開始時間
preg_match_all('|"endTime":(.*?),|i',$re1,$ul1,PREG_SET_ORDER);//播放結束時間
preg_match_all('|"title":"(.*?)",|i',$re1,$un1,PREG_SET_ORDER);//播放節目
$trm1=count($um1);
  for ($k1 = 0; $k1 <=$trm1-1 ; $k1++) {  
$chn.="<programme start=\"". date('YmdHis',$um1[$k1][1]).' +0800'."\" stop=\"". date('YmdHis',$ul1[$k1][1]).' +0800'."\" channel=\"".$cid1[$idm1-1][1]."\">\n<title lang=\"zh\">".trim($un1[$k1][1])."</title>\n<desc lang=\"zh\"> </desc>\n</programme>\n";
 
                                                                                                                    }                                                                                                                                           


  $url2='https://api.cntv.cn/epg/getEpgInfoByChannelNew?c='.$cid1[$idm1-1][0].'&serviceId=tvcctv&d='.$dt2.'&t=jsonp&cb=setItem1';
//https://api.cntv.cn/epg/getEpgInfoByChannelNew?c=cctveurope&serviceId=tvcctv&d=20260202&t=jsonp&cb=setItem1
//https://api.cntv.cn/epg/getEpgInfoByChannelNew?c=cctv1&serviceId=tvcctv&d=20260202&t=jsonp&cb=setItem1
//print  $url1;
$ch2 = curl_init ();
curl_setopt ( $ch2, CURLOPT_URL, $url2 );

//curl_setopt ( $ch1, CURLOPT_HEADER, $hea );
curl_setopt($ch2,CURLOPT_USERAGENT,'Mozilla/5.0 (Linux; Android 8.1.0; JKM-AL00b) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Mobile Safari/537.36):');
curl_setopt($ch2,CURLOPT_SSL_VERIFYPEER,false);
curl_setopt($ch2,CURLOPT_SSL_VERIFYHOST,false);
curl_setopt ( $ch2, CURLOPT_RETURNTRANSFER, 1 );
    //curl_setopt($ch1,CURLOPT_ENCODING,'Vary: Accept-Encoding');
    $re2 = curl_exec($ch2);
   curl_close($ch2);
preg_match_all('|"startTime":(.*?),|i',$re2,$um2,PREG_SET_ORDER);//播放開始時間
preg_match_all('|"endTime":(.*?),|i',$re2,$ul2,PREG_SET_ORDER);//播放結束時間
preg_match_all('|"title":"(.*?)",|i',$re2,$un2,PREG_SET_ORDER);//播放節目
$trm2=count($um2);
  for ($k2 = 0; $k2<=$trm2-1 ; $k2++) {  
$chn.="<programme start=\"". date('YmdHis',$um2[$k2][1]).' +0800'."\" stop=\"". date('YmdHis',$ul2[$k2][1]).' +0800'."\" channel=\"".$cid1[$idm1-1][1]."\">\n<title lang=\"zh\">".trim($un2[$k2][1])."</title>\n<desc lang=\"zh\"> </desc>\n</programme>\n";
 
                                                                                                                    }                                                                                                                                           
}
 $chn.="</tv>\n";

file_put_contents($fp, $chn);


 /*
//创建压缩版本
$fn = gzopen ($fp.'.gz', 'w9');
gzwrite($fn, file_get_contents($fp));
gzclose($fn);

*/

?>
