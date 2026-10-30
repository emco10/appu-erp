<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../global/connection.php';require_once __DIR__.'/qa-http-client.php';
if(!str_ends_with(DB,'_qa'))throw new RuntimeException('Use una base _qa.');
$http=new AppQaHttpClient(getenv('APPU_QA_URL')?:'http://127.0.0.1:8765',DB);
try{
 [$status,,$html]=$http->request('/views/home');if($status!==200)throw new RuntimeException('No carga Inicio.');
 preg_match_all('#href="(/views/[a-zA-Z0-9/-]+)"#',$html,$matches);$routes=array_values(array_unique($matches[1]));if(count($routes)<25)throw new RuntimeException('Inventario de rutas de menú incompleto.');
 foreach($routes as $route){[$status,,$view]=$http->request($route);if($status!==200||!str_contains($view,'</html>')||str_contains($view,'Fatal error:')||!str_contains($view,'js/footer-functions.js?v='))throw new RuntimeException('Vista incompleta o inaccesible '.$route.' HTTP '.$status);}
 $types=['quotation','purchase_request','order','purchase','sales_order','invoice','receipt','credit_note','goods_receipt','remission_guide','incoming_payment','outgoing_payment','stock_transfer','inventory_document','landed_cost'];
 foreach($types as $type){[$status,$result]=$http->request('/modules/trazabilidad/listar-historial.php?document_type='.$type.'&document_id=1');if($status!==200||!is_array($result['history']??null))throw new RuntimeException('Historial no responde para '.$type);}
 echo 'PASS HTTP '.count($routes).' menu routes and '.count($types).' document history actions'.PHP_EOL;
}catch(Throwable $error){fwrite(STDERR,'FAIL navigation HTTP: '.$error->getMessage().PHP_EOL);exit(1);}
