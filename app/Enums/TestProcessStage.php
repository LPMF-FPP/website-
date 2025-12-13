<?php

namespace App\Enums;

enum TestProcessStage: string
{
    case ADMINISTRATION = 'administration';
    case PREPARATION = 'preparation';
    case INSTRUMENTATION = 'instrumentation';
    case INTERPRETATION = 'interpretation';

    public function label(): string
    {
        return match($this) {
            self::ADMINISTRATION => 'Administrasi',
            self::PREPARATION => 'Preparasi Sampel',
            self::INSTRUMENTATION => 'Pengujian Instrumen',
            self::INTERPRETATION => 'Interpretasi Hasil'
        };
    }

    public function getRequiredStatus(): SampleStatus
    {
        return match($this) {
            self::ADMINISTRATION => SampleStatus::ADMIN_PENDING,
            self::PREPARATION => SampleStatus::PREPARATION_PENDING,
            self::INSTRUMENTATION => SampleStatus::INSTRUMENTATION_PENDING,
            self::INTERPRETATION => SampleStatus::INTERPRETATION_PENDING
        };
    }

    public function getInProgressStatus(): SampleStatus
    {
        return match($this) {
            self::ADMINISTRATION => SampleStatus::ADMIN_IN_PROGRESS,
            self::PREPARATION => SampleStatus::PREPARATION_IN_PROGRESS,
            self::INSTRUMENTATION => SampleStatus::INSTRUMENTATION_IN_PROGRESS,
            self::INTERPRETATION => SampleStatus::INTERPRETATION_IN_PROGRESS
        };
    }

    public function getCompletedStatus(): SampleStatus
    {
        return match($this) {
            self::ADMINISTRATION => SampleStatus::ADMIN_DONE,
            self::PREPARATION => SampleStatus::PREPARATION_DONE,
            self::INSTRUMENTATION => SampleStatus::INSTRUMENTATION_DONE,
            self::INTERPRETATION => SampleStatus::INTERPRETATION_DONE
        };
    }
}
