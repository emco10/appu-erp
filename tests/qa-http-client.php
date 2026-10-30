<?php
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
final class AppQaHttpClient
{
    private string $cookie='';
    private string $csrf='';
    private string $database='';
    public function __construct(private string $base, string $expectedDatabase)
    {
        if (!str_ends_with($expectedDatabase,'_qa') || !in_array(parse_url($base,PHP_URL_HOST),['127.0.0.1','localhost','::1'],true)) throw new RuntimeException('Use una base y servidor QA locales.');
        [$status,,$html]=$this->request('/views/ventas/resumen-ordenes-venta');
        if ($status!==200 || $this->database!==$expectedDatabase || !preg_match('/name="csrf-token" content="([^"]+)"/',$html,$match)) throw new RuntimeException('El servidor no acredita la base QA y CSRF esperados.');
        $this->csrf=$match[1];
    }
    public function request(string $path, ?array $data=null):array
    {
        $context=stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>implode("\r\n",['Cookie: '.$this->cookie,'X-CSRF-Token: '.$this->csrf,'X-Requested-With: XMLHttpRequest','Content-Type: application/x-www-form-urlencoded']),'content'=>$data===null?'':http_build_query($data),'ignore_errors'=>true,'timeout'=>15]]);
        $body=file_get_contents($this->base.$path,false,$context);
        if ($body===false) throw new RuntimeException('El servidor QA no respondió.');
        foreach ($http_response_header as $header) {
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i',$header,$match) && !str_contains($header,'Max-Age=0') && !str_ends_with($match[1],'=deleted')) $this->cookie=$match[1];
            if (preg_match('/^X-APPU-QA-Database:\s*(\S+)/i',$header,$match)) $this->database=$match[1];
        }
        preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);
        return [(int)($match[1]??0),json_decode($body,true),$body];
    }
}
