<?php
/** Monedas históricas de APPU: MN = PEN y ME = USD. No convierte importes. */
function app_currency_code(string $currency): string
{
    $currency=strtoupper(trim($currency));
    return ['MN'=>'PEN','ME'=>'USD'][$currency]??$currency;
}

function app_currency_sql(string $column): string
{
    if(!preg_match('/^[a-z_][a-z0-9_.]*$/i',$column))throw new InvalidArgumentException('Columna de moneda inválida.');
    return "CASE UPPER(TRIM({$column})) WHEN 'MN' THEN 'PEN' WHEN 'ME' THEN 'USD' ELSE UPPER(TRIM({$column})) END";
}
