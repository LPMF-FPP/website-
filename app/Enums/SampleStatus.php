<?php

namespace App\Enums;

enum SampleStatus: string
{
    case FORM_SUBMITTED = 'form_submitted';

    // Administration Stage
    case ADMIN_PENDING = 'admin_pending';
    case ADMIN_IN_PROGRESS = 'admin_in_progress';
    case ADMIN_DONE = 'admin_done';

    // Preparation Stage
    case PREPARATION_PENDING = 'preparation_pending';
    case PREPARATION_IN_PROGRESS = 'preparation_in_progress';
    case PREPARATION_DONE = 'preparation_done';

    // Instrumentation Stage
    case INSTRUMENTATION_PENDING = 'instrumentation_pending';
    case INSTRUMENTATION_IN_PROGRESS = 'instrumentation_in_progress';
    case INSTRUMENTATION_DONE = 'instrumentation_done';

    // Interpretation Stage
    case INTERPRETATION_PENDING = 'interpretation_pending';
    case INTERPRETATION_IN_PROGRESS = 'interpretation_in_progress';
    case INTERPRETATION_DONE = 'interpretation_done';

    // Delivery Stage
    case READY_FOR_DELIVERY = 'ready_for_delivery';

    public function canTransitionTo(self $newStatus): bool
    {
        return match($this) {
            self::FORM_SUBMITTED => $newStatus === self::ADMIN_PENDING,

            self::ADMIN_PENDING => $newStatus === self::ADMIN_IN_PROGRESS,
            self::ADMIN_IN_PROGRESS => $newStatus === self::ADMIN_DONE,
            self::ADMIN_DONE => $newStatus === self::PREPARATION_PENDING,

            self::PREPARATION_PENDING => $newStatus === self::PREPARATION_IN_PROGRESS,
            self::PREPARATION_IN_PROGRESS => $newStatus === self::PREPARATION_DONE,
            self::PREPARATION_DONE => $newStatus === self::INSTRUMENTATION_PENDING,

            self::INSTRUMENTATION_PENDING => $newStatus === self::INSTRUMENTATION_IN_PROGRESS,
            self::INSTRUMENTATION_IN_PROGRESS => $newStatus === self::INSTRUMENTATION_DONE,
            self::INSTRUMENTATION_DONE => $newStatus === self::INTERPRETATION_PENDING,

            self::INTERPRETATION_PENDING => $newStatus === self::INTERPRETATION_IN_PROGRESS,
            self::INTERPRETATION_IN_PROGRESS => $newStatus === self::INTERPRETATION_DONE,
            self::INTERPRETATION_DONE => $newStatus === self::READY_FOR_DELIVERY,

            default => false
        };
    }

    public function getNextStatus(): ?self
    {
        return match($this) {
            self::FORM_SUBMITTED => self::ADMIN_PENDING,

            self::ADMIN_PENDING => self::ADMIN_IN_PROGRESS,
            self::ADMIN_IN_PROGRESS => self::ADMIN_DONE,
            self::ADMIN_DONE => self::PREPARATION_PENDING,

            self::PREPARATION_PENDING => self::PREPARATION_IN_PROGRESS,
            self::PREPARATION_IN_PROGRESS => self::PREPARATION_DONE,
            self::PREPARATION_DONE => self::INSTRUMENTATION_PENDING,

            self::INSTRUMENTATION_PENDING => self::INSTRUMENTATION_IN_PROGRESS,
            self::INSTRUMENTATION_IN_PROGRESS => self::INSTRUMENTATION_DONE,
            self::INSTRUMENTATION_DONE => self::INTERPRETATION_PENDING,

            self::INTERPRETATION_PENDING => self::INTERPRETATION_IN_PROGRESS,
            self::INTERPRETATION_IN_PROGRESS => self::INTERPRETATION_DONE,
            self::INTERPRETATION_DONE => self::READY_FOR_DELIVERY,

            default => null
        };
    }

    public function label(): string
    {
        return match($this) {
            self::FORM_SUBMITTED => 'Form Diajukan',

            self::ADMIN_PENDING => 'Menunggu Administrasi',
            self::ADMIN_IN_PROGRESS => 'Sedang Diproses Admin',
            self::ADMIN_DONE => 'Administrasi Selesai',

            self::PREPARATION_PENDING => 'Menunggu Preparasi',
            self::PREPARATION_IN_PROGRESS => 'Sedang Preparasi',
            self::PREPARATION_DONE => 'Preparasi Selesai',

            self::INSTRUMENTATION_PENDING => 'Menunggu Pengujian',
            self::INSTRUMENTATION_IN_PROGRESS => 'Sedang Diuji',
            self::INSTRUMENTATION_DONE => 'Pengujian Selesai',

            self::INTERPRETATION_PENDING => 'Menunggu Interpretasi',
            self::INTERPRETATION_IN_PROGRESS => 'Sedang Interpretasi',
            self::INTERPRETATION_DONE => 'Interpretasi Selesai',

            self::READY_FOR_DELIVERY => 'Siap Diserahkan'
        };
    }
}
