<?php
/** Read-only private CLI audit. Never prints credentials, executes migrations or modifies their ledger. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$application=$argv[1]??'/home/u623025990/domains/appucorp.com/public_html/erp';
$privateConfig=$argv[2]??'/home/u623025990/.appu-erp/database.php';
try {
    if(!is_dir($application.'/db/migrations')||!is_file($privateConfig))throw new RuntimeException('Audit paths are not available.');
    $config=require $privateConfig;
    if(!is_array($config))throw new RuntimeException('Private database config is invalid.');
    foreach(['host','user','password','database']as$key)if(!is_string($config[$key]??null))throw new RuntimeException('Private database config is incomplete.');
    $pdo=new PDO('mysql:host='.$config['host'].';dbname='.$config['database'].';charset=utf8mb4',$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    // The database itself rejects writes on this connection.
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $rows=$pdo->query('SELECT version,checksum,baseline FROM appu_schema_migrations ORDER BY version')->fetchAll();
    $output=[];$counts=[];
    foreach($rows as$row){
        $version=(string)$row['version'];
        if(!preg_match('/^[0-9]{8}_[a-z0-9][a-z0-9_-]*\.sql$/D',$version))throw new RuntimeException('Invalid recorded migration name.');
        $path=$application.'/db/migrations/'.$version;$recorded=(string)$row['checksum'];
        $entry=['version'=>$version,'recorded'=>$recorded,'baseline'=>(int)$row['baseline']];
        if(!is_file($path)){$entry['match']='MISSING';}else{
            $bytes=file_get_contents($path);if($bytes===false)throw new RuntimeException('Cannot read migration bytes.');
            $lf=str_replace("\r\n","\n",$bytes);$crlf=str_replace("\n","\r\n",$lf);
            $entry+=['bytes'=>strlen($bytes),'current'=>hash('sha256',$bytes),'lf'=>hash('sha256',$lf),'crlf'=>hash('sha256',$crlf),'lf_bytes'=>strlen($lf),'crlf_bytes'=>strlen($crlf)];
            $entry['match']=hash_equals($recorded,$entry['current'])?'EXACT':(hash_equals($recorded,$entry['lf'])?'LF':(hash_equals($recorded,$entry['crlf'])?'CRLF':'UNKNOWN'));
        }
        $counts[$entry['match']]=($counts[$entry['match']]??0)+1;$output[]=$entry;
    }
    $recordedNames=array_column($rows,'version');$unapplied=[];
    foreach(glob($application.'/db/migrations/*.sql')?:[]as$file)if(!in_array(basename($file),$recordedNames,true))$unapplied[]=basename($file);
    echo json_encode(['read_only'=>true,'summary'=>$counts,'applied'=>$output,'unapplied_files'=>$unapplied],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $error){fwrite(STDERR,'Read-only migration byte audit failed ('.get_class($error).').'.PHP_EOL);exit(1);}
