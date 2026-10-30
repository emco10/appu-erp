<?php

/** Shared API contract; keep field paths independent of HTML selectors. */
class AppValidationException extends InvalidArgumentException
{
    public array $errors;

    public function __construct(array $errors, string $message = 'Corrige los campos indicados antes de guardar.')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }
}

function app_field_error(string $path, string $code, string $message): array
{
    return compact('path', 'code', 'message');
}

function app_valid_date($value): bool
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

/** Rules/callbacks belong to the server. true = valid, false/string = error. */
function app_validate_fields(array $values, array $schema): array
{
    $errors = [];
    foreach ($schema as $path => $rules) {
        $value = $values[$path] ?? null;
        $label = $rules['label'] ?? $path;
        $empty = $value === null || $value === '' || (is_string($value) && trim($value) === '');
        $required = ($rules['required'] ?? false) || (isset($rules['conditionalRequired']) && ($rules['conditionalRequired'])($values));
        if ($empty) {
            if ($required) $errors[] = app_field_error($path, 'REQUIRED', $rules['messages']['required'] ?? ($label . ' es obligatorio.'));
            continue;
        }
        if (!is_scalar($value)) {
            $errors[] = app_field_error($path, 'INVALID_TYPE', $label . ': valor inválido.');
            continue;
        }
        foreach ($rules as $rule => $constraint) {
            $valid = true; $message = null;
            switch ($rule) {
                case 'min': $valid = is_numeric($value) && is_finite((float) $value) && (float) $value >= $constraint; break;
                case 'max': $valid = is_numeric($value) && is_finite((float) $value) && (float) $value <= $constraint; break;
                case 'minLength': $valid = mb_strlen((string) $value) >= $constraint; break;
                case 'maxLength': $valid = mb_strlen((string) $value) <= $constraint; break;
                case 'regex':
                    $result = @preg_match($constraint, (string) $value);
                    if ($result === false) throw new LogicException('Patrón de validación mal configurado: ' . $path);
                    $valid = $result === 1;
                    break;
                case 'validValues': $valid = in_array($value, $constraint, true); break;
                case 'unique': case 'businessRule': case 'customValidator':
                    $result = $constraint($value, $values);
                    $valid = $result === true;
                    if (is_string($result)) $message = $result;
                    break;
                default: continue 2;
            }
            if (!$valid) $errors[] = app_field_error($path, strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $rule)), $rules['messages'][$rule] ?? $message ?? ($label . ': el valor no cumple la regla ' . $rule . '.'));
        }
    }
    return $errors;
}

function app_api_error(Throwable $exception, string $fallback): void
{
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    if ($exception instanceof AppValidationException) {
        http_response_code(422);
        echo json_encode(['error' => true, 'code' => 'VALIDATION_ERROR', 'message' => $exception->getMessage(), 'errors' => $exception->errors], JSON_UNESCAPED_UNICODE);
        return;
    }
    $known = $exception instanceof DomainException || $exception instanceof InvalidArgumentException;
    http_response_code($exception->getCode() === 409 ? 409 : ($known ? 422 : 500));
    echo json_encode(['error' => true, 'code' => $exception->getCode() === 409 ? 'CONFLICT' : ($known ? 'BUSINESS_RULE' : 'INTERNAL_ERROR'), 'message' => $known ? $exception->getMessage() : $fallback], JSON_UNESCAPED_UNICODE);
}
