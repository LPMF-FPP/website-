<?php

declare(strict_types=1);

namespace App\Support;

final class QmhFormSchemaValidator
{
    /**
     * @return array<int, string>
     */
    public static function errors(mixed $schema): array
    {
        if (! is_array($schema)) {
            return ['Schema pertanyaan harus berupa object JSON.'];
        }

        $errors = [];

        $version = $schema['version'] ?? null;
        if (! is_int($version) || $version < 1) {
            $errors[] = 'Schema pertanyaan wajib memiliki versi (integer) yang valid.';
        }

        $docType = $schema['doc_type'] ?? null;
        if ($docType !== null && (! is_string($docType) || trim($docType) === '')) {
            $errors[] = 'Schema pertanyaan memiliki doc_type yang tidak valid.';
        }

        $questions = $schema['questions'] ?? null;
        if (! is_array($questions)) {
            $errors[] = 'Schema pertanyaan wajib memiliki questions (array).';

            return $errors;
        }

        $allowedTypes = array_flip([
            'section',
            'text',
            'textarea',
            'list',
            'select',
            'checkbox',
            'date',
            'number',
        ]);

        $seenIds = [];

        foreach ($questions as $idx => $q) {
            if (! is_array($q)) {
                $errors[] = "Question #{$idx} harus berupa object.";

                continue;
            }

            $id = $q['id'] ?? '';
            if (! is_string($id) || trim($id) === '') {
                $errors[] = "Question #{$idx} wajib memiliki id (string).";
            } else {
                $id = trim($id);
                if (! preg_match('/^[a-z0-9_]{1,64}$/', $id)) {
                    $errors[] = "Question id '{$id}' tidak valid. Gunakan huruf kecil/angka/_ maksimal 64 karakter.";
                }
                if (isset($seenIds[$id])) {
                    $errors[] = "Duplikasi question id: '{$id}'.";
                }
                $seenIds[$id] = true;
            }

            $label = $q['label'] ?? '';
            if (! is_string($label) || trim($label) === '') {
                $errors[] = "Question '{$id}' wajib memiliki label (string).";
            }

            $type = $q['type'] ?? 'text';
            if (! is_string($type) || ! isset($allowedTypes[$type])) {
                $errors[] = "Question '{$id}' memiliki type yang tidak didukung.";
            }

            $required = $q['required'] ?? false;
            if (! is_bool($required) && ! (is_int($required) && ($required === 0 || $required === 1))) {
                $errors[] = "Question '{$id}' memiliki required yang tidak valid.";
            }

            if ($type === 'select') {
                $options = $q['options'] ?? null;
                if (! is_array($options) || count($options) === 0) {
                    $errors[] = "Question '{$id}' tipe select wajib memiliki options.";

                    continue;
                }

                $seenValues = [];
                foreach ($options as $optIdx => $opt) {
                    if (! is_array($opt)) {
                        $errors[] = "Question '{$id}' options #{$optIdx} tidak valid.";

                        continue;
                    }

                    $value = $opt['value'] ?? '';
                    $optLabel = $opt['label'] ?? '';
                    if (! is_string($value) || trim($value) === '') {
                        $errors[] = "Question '{$id}' options #{$optIdx} wajib memiliki value.";

                        continue;
                    }

                    if (! is_string($optLabel) || trim($optLabel) === '') {
                        $errors[] = "Question '{$id}' options #{$optIdx} wajib memiliki label.";
                    }

                    $value = trim($value);
                    if (isset($seenValues[$value])) {
                        $errors[] = "Question '{$id}' memiliki option value duplikat: '{$value}'.";
                    }
                    $seenValues[$value] = true;
                }
            }
        }

        return $errors;
    }
}
