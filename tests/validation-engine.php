<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../global/validation.php';
$schema = [
    'customer' => ['required' => true],
    'warehouse' => ['conditionalRequired' => fn ($values) => $values['inventory'] === true],
    'quantity' => ['min' => 1, 'max' => 10],
    'code' => ['minLength' => 3, 'maxLength' => 5, 'regex' => '/^[A-Z]+$/', 'unique' => fn ($value) => $value !== 'USED'],
    'currency' => ['validValues' => ['PEN', 'USD']],
    'date' => ['customValidator' => fn ($value) => app_valid_date($value)],
    'price' => ['businessRule' => fn ($value) => $value >= 0 ? true : 'El precio no puede ser negativo.'],
];
$invalid = app_validate_fields(['inventory' => true, 'quantity' => 11, 'code' => 'ab', 'currency' => 'BAD', 'date' => '2026-02-30', 'price' => -1], $schema);
$expected = ['customer:REQUIRED', 'warehouse:REQUIRED', 'quantity:MAX', 'code:MIN_LENGTH', 'code:REGEX', 'currency:VALID_VALUES', 'date:CUSTOM_VALIDATOR', 'price:BUSINESS_RULE'];
$actual = array_map(fn ($error) => $error['path'] . ':' . $error['code'], $invalid);
if ($actual !== $expected) throw new RuntimeException('No se acumularon todas las reglas: ' . json_encode($actual));
if (count(app_validate_fields(['code' => 'USED'], ['code' => $schema['code']])) !== 1) throw new RuntimeException('No se validó unicidad.');
if (app_validate_fields(['inventory' => false, 'quantity' => 3, 'code' => 'ABC', 'currency' => 'PEN', 'date' => '2028-02-29', 'price' => 0, 'customer' => 'QA'], $schema)) throw new RuntimeException('Se rechazó un formulario válido.');
if (app_valid_date("2026-01-01\0") || app_valid_date([]) || app_valid_date('2026-02-29')) throw new RuntimeException('Se aceptó una fecha imposible o mal formada.');
echo "PASS shared validation required conditional range length regex uniqueness values business and custom rules\n";
