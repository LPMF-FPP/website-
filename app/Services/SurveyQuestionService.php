<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;

class SurveyQuestionService
{
    public const DEFAULT_QUESTIONS = [
        'persyaratan' => [
            'key' => 'persyaratan',
            'label' => 'Persyaratan pelayanan',
            'scale' => ['Tidak Mudah', 'Kurang Mudah', 'Mudah', 'Sangat Mudah'],
            'enabled' => true,
            'order' => 1,
        ],
        'prosedur' => [
            'key' => 'prosedur',
            'label' => 'Prosedur/tata cara',
            'scale' => ['Tidak Mudah', 'Kurang Mudah', 'Mudah', 'Sangat Mudah'],
            'enabled' => true,
            'order' => 2,
        ],
        'ketepatan_waktu' => [
            'key' => 'ketepatan_waktu',
            'label' => 'Ketepatan waktu',
            'scale' => ['Tidak Cepat', 'Kurang Cepat', 'Cepat', 'Sangat Cepat'],
            'enabled' => true,
            'order' => 3,
        ],
        'kesesuaian_hasil' => [
            'key' => 'kesesuaian_hasil',
            'label' => 'Kesesuaian hasil',
            'scale' => ['Tidak Bermanfaat', 'Kurang Bermanfaat', 'Bermanfaat', 'Sangat Bermanfaat'],
            'enabled' => true,
            'order' => 4,
        ],
        'kompetensi' => [
            'key' => 'kompetensi',
            'label' => 'Kompetensi personel',
            'scale' => ['Tidak Kompeten', 'Kurang Kompeten', 'Kompeten', 'Sangat Kompeten'],
            'enabled' => true,
            'order' => 5,
        ],
        'sikap' => [
            'key' => 'sikap',
            'label' => 'Sikap/keramahan',
            'scale' => ['Tidak Baik', 'Kurang Baik', 'Baik', 'Sangat Baik'],
            'enabled' => true,
            'order' => 6,
        ],
        'pengaduan' => [
            'key' => 'pengaduan',
            'label' => 'Penanganan pengaduan',
            'scale' => ['Tidak Baik', 'Kurang Baik', 'Baik', 'Sangat Baik'],
            'enabled' => true,
            'order' => 7,
        ],
        'fasilitas' => [
            'key' => 'fasilitas',
            'label' => 'Fasilitas/sarana',
            'scale' => ['Tidak Baik', 'Kurang Baik', 'Baik', 'Sangat Baik'],
            'enabled' => true,
            'order' => 8,
        ],
    ];

    public function __construct(
        private readonly SettingsRepository $settings
    ) {}

    public function getQuestions(): array
    {
        $questions = $this->settings->get('survey.questions', self::DEFAULT_QUESTIONS);

        // Ensure it's an array
        if (is_string($questions)) {
            $questions = json_decode($questions, true) ?? self::DEFAULT_QUESTIONS;
        }

        // Sort by order and filter enabled
        return collect($questions)
            ->filter(fn ($q) => $q['enabled'] ?? true)
            ->sortBy('order')
            ->values()
            ->toArray();
    }

    public function getAllQuestions(): array
    {
        $questions = $this->settings->get('survey.questions', self::DEFAULT_QUESTIONS);

        if (is_string($questions)) {
            $questions = json_decode($questions, true) ?? self::DEFAULT_QUESTIONS;
        }

        return collect($questions)->sortBy('order')->values()->toArray();
    }

    public function updateQuestions(array $questions, ?int $userId = null): void
    {
        // Re-index by key for easy lookup
        $indexed = [];
        foreach ($questions as $i => $q) {
            $key = $q['key'] ?? 'question_'.($i + 1);
            $indexed[$key] = array_merge($q, ['key' => $key, 'order' => $i + 1]);
        }

        $this->settings->put('survey.questions', $indexed, $userId);
        settings_forget_cache();
    }

    public function addQuestion(array $question, ?int $userId = null): void
    {
        $questions = $this->getAllQuestions();
        $key = $question['key'] ?? 'q_'.time();

        $questions[] = array_merge($question, [
            'key' => $key,
            'order' => count($questions) + 1,
            'enabled' => true,
        ]);

        $this->updateQuestions($questions, $userId);
    }

    public function removeQuestion(string $key, ?int $userId = null): void
    {
        $questions = $this->getAllQuestions();
        $questions = array_filter($questions, fn ($q) => ($q['key'] ?? '') !== $key);
        $this->updateQuestions(array_values($questions), $userId);
    }

    public function toggleQuestion(string $key, bool $enabled, ?int $userId = null): void
    {
        $questions = $this->getAllQuestions();
        foreach ($questions as &$q) {
            if (($q['key'] ?? '') === $key) {
                $q['enabled'] = $enabled;
                break;
            }
        }
        $this->updateQuestions($questions, $userId);
    }
}
