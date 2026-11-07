<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$path='db/migrations/20261102_repair_sunat_series_scope.sql';
$bytes=file_get_contents($root.'/'.$path);
if($bytes===false||hash('sha256',$bytes)!=='c1082b5c01f374308d01750003c6bd78ebe5a197a9a6897ab51aaca090a02934')throw new RuntimeException('Historical migration must preserve the exact bytes recorded in production.');
$lf=str_replace("\r\n","\n",$bytes);
if(strlen($bytes)!==1805||strlen($lf)!==1769||hash('sha256',$lf)!=='aaea0ea7af196a5aa4138dcc5c4f8d9c1656ea93f2c19d41243bf58dc30ce6cd')throw new RuntimeException('Restoration must change line endings only, preserving the original Git SQL content.');
$attributes=file_get_contents($root.'/.gitattributes');
if($attributes===false||!preg_match('#^/?'.preg_quote($path,'#').'\s+-text\s*$#m',$attributes))throw new RuntimeException('Git must preserve the historical migration byte sequence.');
echo "PASS immutable migration exact production checksum; LF content unchanged; Git byte preservation guard\n";
