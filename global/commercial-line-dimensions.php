<?php

/**
 * Normaliza datos contables y de trazabilidad opcionales de una línea.
 * Su ausencia nunca invalida el documento porque no son obligatorios SUNAT.
 */
function app_commercial_line_dimensions(array $line, string $label = 'Línea'): array
{
    $read = static function (array $source, string $key, int $maximum, string $fieldLabel) use ($label): string {
        $value = $source[$key] ?? '';
        if (!is_scalar($value) && $value !== null) {
            throw new DomainException($label . ': ' . $fieldLabel . ' no es válido.');
        }
        $value = trim((string) $value);
        if (mb_strlen($value, 'UTF-8') > $maximum) {
            throw new DomainException($label . ': ' . $fieldLabel . ' admite hasta ' . $maximum . ' caracteres.');
        }
        return $value;
    };

    $expiration = $read($line, 'expiration_date', 10, 'la fecha de vencimiento');
    if ($expiration !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expiration);
        if (!$date || $date->format('Y-m-d') !== $expiration) {
            throw new DomainException($label . ': la fecha de vencimiento debe usar el formato AAAA-MM-DD.');
        }
    }

    return [
        'project_code' => $read($line, 'project_code', 50, 'el proyecto'),
        'cost_center_code' => $read($line, 'cost_center_code', 50, 'el centro de costo'),
        'lot_number' => $read($line, 'lot_number', 80, 'el lote'),
        'serial_number' => $read($line, 'serial_number', 100, 'la serie'),
        'expiration_date' => $expiration !== '' ? $expiration : null,
    ];
}
