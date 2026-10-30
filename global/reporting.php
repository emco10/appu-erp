<?php
require_once __DIR__.'/currency.php';
/** Legacy document aliases retain the same monetary unit; no FX conversion. */
function app_report_currency(string $currency): string
{
    return app_currency_code($currency);
}

function app_report_currency_sql(string $column): string
{
    return app_currency_sql($column);
}

function app_report_date_range($from,$to,bool $allowEmpty=false): void
{
    if($allowEmpty&&$from===''&&$to==='')return;
    foreach([$from,$to] as $date){$parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Indica un rango de fechas válido.');}
    if($from>$to)throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la final.');
}

function app_report_error(string $message,int $status=422): void
{
    http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>$message],JSON_UNESCAPED_UNICODE);exit;
}
