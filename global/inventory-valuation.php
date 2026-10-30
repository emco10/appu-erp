<?php
require_once __DIR__.'/currency.php';

/** Valuation rate is units of the company's base currency per document unit. */
function app_inventory_valuation_context(PDO $pdo,string $documentCurrency,$requestedRate=null,array $source=[]):array
{
    $companyCurrency=app_currency_code((string)$pdo->query('SELECT currency FROM tbl_company_settings WHERE id=1')->fetchColumn());
    $documentCurrency=app_currency_code($documentCurrency);
    if(!in_array($companyCurrency,['PEN','USD','EUR'],true)||!in_array($documentCurrency,['PEN','USD','EUR'],true))throw new DomainException('La moneda del documento o la moneda base de la empresa no es válida para valorizar inventario.');
    if($companyCurrency===$documentCurrency)return ['document_currency'=>$documentCurrency,'valuation_currency'=>$companyCurrency,'valuation_exchange_rate'=>1.0];
    if($requestedRate===null||$requestedRate===''){
        // The existing purchase quotation is PEN for one USD. Other pairs
        // require an explicit valuation rate; do not infer or invert a quote.
        $requestedRate=$companyCurrency==='PEN'&&$documentCurrency==='USD'?($source['exchange_rate_purchase']??null):null;
    }
    $rate=filter_var($requestedRate,FILTER_VALIDATE_FLOAT);
    if($rate===false||$rate===null||!is_finite((float)$rate)||$rate<=0||$rate>9999999999||abs((float)$rate-round((float)$rate,8))>0.0000000001)throw new DomainException('Indica un tipo de cambio de valorización válido: unidades '.$companyCurrency.' por cada 1 '.$documentCurrency.'.');
    return ['document_currency'=>$documentCurrency,'valuation_currency'=>$companyCurrency,'valuation_exchange_rate'=>(float)$rate];
}
