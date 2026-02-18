<?php

declare(strict_types=1);

namespace App\Support;

final class QmhFormAnswersValidator
{
    public const REQUIRED_POLICY_ENFORCE = 'enforce';

    public const REQUIRED_POLICY_ALLOW_PARTIAL = 'allow_partial';

    /**
     * @param  array<string, mixed>  $schema
     * @return array{errors: array<string, string>, normalized: array<string, mixed>}
     */
    public static function validateAndNormalize(
        array $schema,
        mixed $answers,
        string $requiredPolicy = self::REQUIRED_POLICY_ENFORCE
    ): array {
        $enforceRequired = self::shouldEnforceRequired($requiredPolicy);
        $errors = [];
        $normalized = [];

        $questions = $schema['questions'] ?? [];
        if (! is_array($questions)) {
            return [
                'errors' => ['answers_json' => 'Schema pertanyaan tidak valid.'],
                'normalized' => is_array($answers) ? QmhAnswerSanitizer::sanitizeAnswersJson($answers) : [],
            ];
        }

        $answersArr = is_array($answers) ? $answers : [];

        // Preserve unknown keys (backward compatibility).
        foreach ($answersArr as $key => $val) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }
            $normalized[$key] = QmhAnswerSanitizer::sanitizeAnswerValue($val);
        }

        foreach ($questions as $q) {
            if (! is_array($q)) {
                continue;
            }

            $qid = isset($q['id']) && is_string($q['id']) ? trim($q['id']) : '';
            if ($qid === '') {
                continue;
            }

            $type = isset($q['type']) && is_string($q['type']) ? $q['type'] : 'text';
            $required = (bool) ($q['required'] ?? false);
            $keyPath = 'answers_json.'.$qid;

            if ($type === 'section') {
                unset($normalized[$qid]);

                continue;
            }

            $raw = $answersArr[$qid] ?? null;

            if ($type === 'checkbox') {
                $bool = self::coerceBoolean($raw);
                $normalized[$qid] = $bool;
                if ($enforceRequired && $required && $bool !== true) {
                    $errors[$keyPath] = 'Wajib dicentang.';
                }

                continue;
            }

            if ($type === 'list') {
                if (is_string($raw)) {
                    $sanitized = QmhAnswerSanitizer::sanitizeAnswerValue($raw);
                    $normalized[$qid] = $sanitized;

                    if ($enforceRequired && $required && (! is_string($sanitized) || QmhAnswerSanitizer::plainText($sanitized) === '')) {
                        $errors[$keyPath] = 'Wajib diisi.';
                    }

                    continue;
                }

                if (is_array($raw)) {
                    $items = [];
                    foreach ($raw as $item) {
                        if (! is_string($item)) {
                            continue;
                        }
                        $trimmed = trim($item);
                        if ($trimmed === '') {
                            continue;
                        }
                        $items[] = $trimmed;
                    }
                    $normalized[$qid] = $items;

                    if ($enforceRequired && $required && count($items) === 0) {
                        $errors[$keyPath] = 'Wajib diisi.';
                    }

                    continue;
                }

                $normalized[$qid] = [];
                if ($enforceRequired && $required) {
                    $errors[$keyPath] = 'Wajib diisi.';
                }

                continue;
            }

            // Default: scalar types stored as string.
            $val = is_string($raw) ? trim($raw) : (is_numeric($raw) ? (string) $raw : '');
            $val = is_string($val) ? $val : '';

            if ($type === 'select') {
                $options = is_array($q['options'] ?? null) ? $q['options'] : [];
                $allowed = [];
                foreach ($options as $opt) {
                    if (is_array($opt) && isset($opt['value']) && is_string($opt['value'])) {
                        $allowed[] = trim($opt['value']);
                    }
                }

                if ($val !== '' && ! in_array($val, $allowed, true)) {
                    $errors[$keyPath] = 'Pilihan tidak valid.';
                }
            }

            if ($type === 'date' && $val !== '' && ! self::isValidDate($val)) {
                $errors[$keyPath] = 'Tanggal tidak valid.';
            }

            if ($type === 'number' && $val !== '' && ! preg_match('/^[+-]?\d+(\.\d+)?$/', $val)) {
                $errors[$keyPath] = 'Angka tidak valid.';
            }

            if ($enforceRequired && $required && $val === '') {
                $errors[$keyPath] = 'Wajib diisi.';
            }

            $normalized[$qid] = QmhAnswerSanitizer::sanitizeAnswerValue($val);
        }

        return [
            'errors' => $errors,
            'normalized' => $normalized,
        ];
    }

    private static function shouldEnforceRequired(string $requiredPolicy): bool
    {
        return match ($requiredPolicy) {
            self::REQUIRED_POLICY_ENFORCE => true,
            self::REQUIRED_POLICY_ALLOW_PARTIAL => false,
            default => throw new \InvalidArgumentException('Required policy is not supported.'),
        };
    }

    private static function coerceBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['1', 'true', 'on', 'yes', 'y'], true)) {
                return true;
            }
            if (in_array($v, ['0', 'false', 'off', 'no', 'n', ''], true)) {
                return false;
            }
        }

        return false;
    }

    private static function isValidDate(string $value): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($dt === false) {
            return false;
        }

        return $dt->format('Y-m-d') === $value;
    }
}
