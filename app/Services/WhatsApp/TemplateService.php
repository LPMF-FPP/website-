<?php

namespace App\Services\WhatsApp;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class TemplateService
{
    /**
     * Template categories and their settings keys.
     */
    private const CATEGORY_KEYS = [
        'milestone' => 'notifications.whatsapp.templates',
        'command' => 'notifications.whatsapp.command_templates',
        'auto_reply' => 'notifications.whatsapp.auto_reply_templates',
        'system' => 'notifications.whatsapp.system_templates',
        'task' => 'notifications.whatsapp.task_templates',
    ];

    /**
     * Default templates for each category.
     */
    private const DEFAULTS = [
        'milestone' => [
            'REQUEST_RECEIVED' => "{greetings}, {pangkat} {nama}.\n\nKami informasikan bahwa permintaan Anda dengan:\n📄 Nomor Surat: {nomor surat}\n👤 Tersangka: {tersangka}\n🔖 Kode Resi: {resi}\n\ntelah kami terima dan segera kami tindak lanjuti. ✅\n\nApabila ingin melacak bisa mengetikan /resi {resi}\n\nTerima kasih atas kepercayaan Anda.\n\nSalam Presisi 🙏\nStaff Laboratorium Farmapol Pusdokkes Polri",
            'REQUEST_REJECTED' => "{greetings} {pangkat} {nama},\n\nMohon maaf, permintaan pengujian dengan nomor surat {nomor surat} a.n. TSK {tersangka} tidak dapat kami proses.\n\nAlasan: {reason}\n\nSilakan hubungi kami untuk informasi lebih lanjut.\n\nTerima kasih.",
            'READY_FOR_PICKUP' => "{greetings} {pangkat} {nama},\n\nKami informasikan bahwa hasil pengujian dengan nomor resi *{resi}* a.n. TSK {tersangka} telah selesai dan siap untuk diambil.\n\nSilakan datang ke Laboratorium Farmapol Pusdokkes Polri pada jam kerja (08.00 - 16.00 WIB).\n\nTerima kasih.",
            'HANDOVER_COMPLETED' => "{greetings} {pangkat} {nama},\n\nSerah terima hasil pengujian dengan nomor resi *{resi}* a.n. TSK {tersangka} telah selesai.\n\nTerima kasih telah menggunakan layanan kami.\n\n_Laboratorium Farmapol Pusdokkes Polri_",
        ],
        'command' => [
            'HELP' => "🤖 *BOT LPMF TRACKING*\n\nDaftar command yang tersedia:\n\n📦 */resi {nomor_resi}*\n   Cek status perjalanan permintaan pengujian\n   Contoh: /resi LPMF/001/2026\n\n❓ */help* atau */bantuan*\n   Tampilkan menu bantuan ini\n\n📝 *INPUT MANUAL*\n🌡️ *Suhu*: `/suhu {lokasi} {suhu} {kelembaban} {am/pm}`\n   Contoh: /suhu R01 24.5 60.0 am\n📦 *Stok*: `/stok {masuk/keluar} {nama} {jml}`\n   Contoh: /stok masuk alkohol 5\n\n📊 *INFORMASI*\n🔍 `/stok` (tanpa parameter): Lihat daftar stok item.\n🌡️ `/suhu` (tanpa parameter): Lihat daftar sensor.\n📈 `/status` : Lihat statistik permintaan.\n\n🔔 *FITUR OTOMATIS*\nBot ini juga akan mengirim notifikasi untuk:\n• 🌡️ *Peringatan Suhu* (Real-time)\n• 📦 *Stok Menipis & Kadaluarsa* (Setiap 08:00)\n\n─────────────────\n💬 Hubungi kami jika butuh bantuan lebih lanjut.",
            'HELP_ADMIN' => "\n\n🛠️ *ADMIN TOOLS*\n`/restart` : Restart System/Queue\n`/whitelist` : Kelola Admin Whitelist",
            'RESI_NOT_FOUND' => "❌ Nomor resi tidak ditemukan: {resi}\n\nPastikan nomor resi benar.",
            'RESI_FORMAT_ERROR' => "❌ Format salah!\n\nGunakan: /resi {nomor_resi}\n\nContoh: /resi LPMF/001/2026",
            'RESI_TRACKING' => "📋 *PELACAKAN RESI PERMINTAAN*\n\n🔖 *Kode Resi:* {resi}\n📄 *Nomor Permintaan:* {request_number}\n👮 *Penyidik:* {investigator}\n📦 *Jumlah Sampel:* {sample_count}\n\n🧭 *Tahapan Proses (1-5)*\n{milestones}\n\n📌 *Status Terkini*\n*{current_status}*\n\nKeterangan: ✅ selesai | 🟡 sedang berjalan | ⚪️ menunggu\n🕒 Pada Tahap 3, waktu menunjukkan kapan tahap pengujian dimulai.\n─────────────────\nℹ️ Cek ulang kapan saja dengan ketik:\n*/resi {resi}*",
            'UNKNOWN_COMMAND' => "Command tidak dikenal: {command}\n\nKetik /help untuk melihat daftar command.",
            'COMMAND_ERROR' => "❌ Terjadi kesalahan saat memproses command.\n\nSilakan coba lagi nanti.",
            'STATUS_REPORT' => "📊 *STATISTIK PERMINTAAN*\n\n📥 Bulan Ini: {this_month} permintaan\n📥 Tahun Ini: {this_year} permintaan\n\n📌 Status:\n• Diproses: {in_progress}\n• Siap Ambil: {ready}\n• Selesai: {completed}\n\n─────────────────\n_Update: {timestamp}_",
            'RESTART_SUCCESS' => "✅ Sistem berhasil di-restart.\n\nQueue worker telah dimulai ulang.",
            'RESTART_UNAUTHORIZED' => '❌ Anda tidak memiliki izin untuk menjalankan command ini.',
            'WHITELIST_ADDED' => '✅ Nomor {phone} ({name}) berhasil ditambahkan ke whitelist.',
            'WHITELIST_REMOVED' => '✅ Nomor {phone} berhasil dihapus dari whitelist.',
            'WHITELIST_NOT_FOUND' => '❌ Nomor {phone} tidak ditemukan di whitelist.',
            'WHITELIST_UNAUTHORIZED' => '❌ Hanya Super Admin yang dapat mengelola whitelist.',
            'ACCESS_DENIED' => "❌ Maaf, Anda tidak memiliki izin untuk menggunakan command ini.\n\nHubungi admin untuk didaftarkan.",
        ],
        'auto_reply' => [
            'WELCOME' => "👋 Selamat datang di *Bot LPMF Tracking*!\n\nBot ini membantu Anda melacak status permintaan pengujian di Laboratorium Farmapol Pusdokkes Polri.\n\nKetik /help untuk melihat daftar command yang tersedia.",
            'NO_COMMAND' => "Maaf, saya hanya memahami perintah yang dimulai dengan tanda /\n\nKetik /help untuk melihat daftar command yang tersedia.",
        ],
        'system' => [
            'TEMPERATURE_ALERT' => "🚨 *PERINGATAN SUHU*\n\n📍 Lokasi: {location}\n🌡️ Suhu: *{temperature}°C*\n⚠️ Batas: {threshold}°C\n\nSegera periksa kondisi ruangan!\n\n_Waktu: {timestamp}_",
            'STOCK_LOW' => "⚠️ *STOK MENIPIS*\n\n📦 Item: {item}\n📊 Stok: *{qty} {unit}*\n📍 Lokasi: {location}\n\nSegera lakukan pengadaan!\n\n_Update: {timestamp}_",
            'STOCK_EXPIRING' => "⏰ *STOK KADALUARSA*\n\n📦 Item: {item}\n📅 Kadaluarsa: *{expiry_date}*\n📊 Qty: {qty} {unit}\n📍 Lokasi: {location}\n\nSegera gunakan atau buang!\n\n_Update: {timestamp}_",
            'CONSOLIDATED_BIWEEKLY' => "📊 *LAPORAN BI-WEEKLY*\n\nLaporan periode *{period_label}* telah di-generate.\n\n📅 Periode: {period_start} - {period_end}\n⏰ Waktu Generate: {generated_at}\n📈 Total Permintaan: {total_requests}\n🧪 Total Sampel: {total_samples}\n\nSilakan akses laporan di:\n{report_url}\n\n—\nStaff Laboratorium Farmapol Pusdokkes Polri",
            'CONSOLIDATED_MONTHLY' => "📊 *LAPORAN BULANAN*\n\nLaporan periode *{period_label}* telah di-generate.\n\n📅 Periode: {period_start} - {period_end}\n⏰ Waktu Generate: {generated_at}\n📈 Total Permintaan: {total_requests}\n🧪 Total Sampel: {total_samples}\n\nSilakan akses laporan di:\n{report_url}\n\n—\nStaff Laboratorium Farmapol Pusdokkes Polri",
            'CONSOLIDATED_QUARTERLY' => "📊 *LAPORAN TRIWULAN*\n\nLaporan periode *{period_label}* telah di-generate.\n\n📅 Periode: {period_start} - {period_end}\n⏰ Waktu Generate: {generated_at}\n📈 Total Permintaan: {total_requests}\n🧪 Total Sampel: {total_samples}\n\nSilakan akses laporan di:\n{report_url}\n\n—\nStaff Laboratorium Farmapol Pusdokkes Polri",
        ],
        'task' => [
            'TASK_ASSIGNED' => "📋 *TUGAS BARU*\n\n{greetings} {assignee_name},\n\nAnda mendapat tugas baru dari {assigner_name}:\n\n📝 *{title}*\n📄 {description}\n\n⚡ Prioritas: *{priority}*\n⏰ Deadline: *{due_at}*\n📦 Terkait: {request_number}\n\n─────────────────\n_Laboratorium Farmapol Pusdokkes Polri_",
            'TASK_STATUS_CHANGED' => "📋 *UPDATE TUGAS*\n\nTugas \"{title}\" telah diperbarui.\n\n📌 Status: *{status}*\n✅ Selesai pada: {completed_at}\n\n─────────────────\n_Laboratorium Farmapol Pusdokkes Polri_",
        ],
    ];

    /**
     * Available placeholders for each template.
     */
    private const PLACEHOLDERS = [
        'milestone' => [
            'REQUEST_RECEIVED' => ['greetings', 'pangkat', 'nama', 'nomor surat', 'tersangka', 'resi'],
            'REQUEST_REJECTED' => ['greetings', 'pangkat', 'nama', 'nomor surat', 'tersangka', 'reason'],
            'READY_FOR_PICKUP' => ['greetings', 'pangkat', 'nama', 'tersangka', 'resi'],
            'HANDOVER_COMPLETED' => ['greetings', 'pangkat', 'nama', 'tersangka', 'resi'],
        ],
        'command' => [
            'HELP' => [],
            'HELP_ADMIN' => [],
            'RESI_NOT_FOUND' => ['resi'],
            'RESI_FORMAT_ERROR' => [],
            'RESI_TRACKING' => ['resi', 'request_number', 'investigator', 'milestones', 'current_status', 'sample_count'],
            'UNKNOWN_COMMAND' => ['command'],
            'COMMAND_ERROR' => [],
            'STATUS_REPORT' => ['this_month', 'this_year', 'in_progress', 'ready', 'completed', 'timestamp'],
            'RESTART_SUCCESS' => [],
            'RESTART_UNAUTHORIZED' => [],
            'WHITELIST_ADDED' => ['phone', 'name'],
            'WHITELIST_REMOVED' => ['phone'],
            'WHITELIST_NOT_FOUND' => ['phone'],
            'WHITELIST_UNAUTHORIZED' => [],
            'ACCESS_DENIED' => [],
        ],
        'auto_reply' => [
            'WELCOME' => [],
            'NO_COMMAND' => [],
        ],
        'system' => [
            'TEMPERATURE_ALERT' => ['location', 'temperature', 'threshold', 'timestamp'],
            'STOCK_LOW' => ['item', 'qty', 'unit', 'location', 'timestamp'],
            'STOCK_EXPIRING' => ['item', 'expiry_date', 'qty', 'unit', 'location', 'timestamp'],
            'CONSOLIDATED_BIWEEKLY' => ['period_label', 'period_start', 'period_end', 'generated_at', 'total_requests', 'total_samples', 'report_url'],
            'CONSOLIDATED_MONTHLY' => ['period_label', 'period_start', 'period_end', 'generated_at', 'total_requests', 'total_samples', 'report_url'],
            'CONSOLIDATED_QUARTERLY' => ['period_label', 'period_start', 'period_end', 'generated_at', 'total_requests', 'total_samples', 'report_url'],
        ],
        'task' => [
            'TASK_ASSIGNED' => ['greetings', 'assignee_name', 'assigner_name', 'title', 'description', 'priority', 'due_at', 'request_number'],
            'TASK_STATUS_CHANGED' => ['title', 'status', 'completed_at'],
        ],
    ];

    /**
     * Cache key for templates.
     */
    private const CACHE_KEY = 'whatsapp_templates';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get a template by category and key.
     */
    public function get(string $category, string $key): string
    {
        $templates = $this->getCategory($category);

        return $templates[$key] ?? $this->getDefault($category, $key);
    }

    /**
     * Render a template with replacements.
     */
    public function render(string $category, string $key, array $replacements = []): string
    {
        $template = $this->get($category, $key);

        foreach ($replacements as $placeholder => $value) {
            $template = str_replace("{{$placeholder}}", $value ?? '', $template);
        }

        return $template;
    }

    /**
     * Get all templates grouped by category.
     */
    public function getAll(): array
    {
        $result = [];

        foreach (self::CATEGORY_KEYS as $category => $settingsKey) {
            $stored = settings($settingsKey, []);
            $defaults = self::DEFAULTS[$category] ?? [];

            // Merge stored with defaults (stored takes precedence)
            $result[$category] = array_merge($defaults, $stored);
        }

        return $result;
    }

    /**
     * Get templates for a specific category.
     */
    public function getCategory(string $category): array
    {
        $settingsKey = self::CATEGORY_KEYS[$category] ?? null;

        if (! $settingsKey) {
            return self::DEFAULTS[$category] ?? [];
        }

        $stored = settings($settingsKey, []);
        $defaults = self::DEFAULTS[$category] ?? [];

        return array_merge($defaults, $stored);
    }

    /**
     * Update a single template.
     */
    public function update(string $category, string $key, string $template): void
    {
        $settingsKey = self::CATEGORY_KEYS[$category] ?? null;

        if (! $settingsKey) {
            throw new \InvalidArgumentException("Invalid template category: {$category}");
        }

        $existing = settings($settingsKey, []);
        $existing[$key] = $template;

        SystemSetting::updateOrCreate(
            ['key' => $settingsKey],
            ['value' => $existing]
        );

        $this->clearCache();
    }

    /**
     * Update multiple templates in a category.
     */
    public function updateCategory(string $category, array $templates): void
    {
        $settingsKey = self::CATEGORY_KEYS[$category] ?? null;

        if (! $settingsKey) {
            throw new \InvalidArgumentException("Invalid template category: {$category}");
        }

        $existing = settings($settingsKey, []);
        $merged = array_merge($existing, $templates);

        SystemSetting::updateOrCreate(
            ['key' => $settingsKey],
            ['value' => $merged]
        );

        $this->clearCache();
    }

    /**
     * Reset a template to default.
     */
    public function resetToDefault(string $category, string $key): string
    {
        $default = $this->getDefault($category, $key);

        $settingsKey = self::CATEGORY_KEYS[$category] ?? null;

        if ($settingsKey) {
            $existing = settings($settingsKey, []);
            unset($existing[$key]);
            SystemSetting::updateOrCreate(
                ['key' => $settingsKey],
                ['value' => $existing]
            );
            $this->clearCache();
        }

        return $default;
    }

    /**
     * Get default template.
     */
    public function getDefault(string $category, string $key): string
    {
        return self::DEFAULTS[$category][$key] ?? '';
    }

    /**
     * Get all defaults for a category.
     */
    public function getDefaults(string $category): array
    {
        return self::DEFAULTS[$category] ?? [];
    }

    /**
     * Get available placeholders for a template.
     */
    public function getPlaceholders(string $category, string $key): array
    {
        return self::PLACEHOLDERS[$category][$key] ?? [];
    }

    /**
     * Get all placeholders grouped by category.
     */
    public function getAllPlaceholders(): array
    {
        return self::PLACEHOLDERS;
    }

    /**
     * Get available categories.
     */
    public function getCategories(): array
    {
        return array_keys(self::CATEGORY_KEYS);
    }

    /**
     * Get magic variables grouped for Magic Insert Toolbar.
     *
     * @return array<string, array<int, string>>
     */
    public function getMagicVariables(): array
    {
        return [
            'Global' => [
                'greetings',
                'timestamp',
            ],
            'Penyidik' => [
                'nama',
                'pangkat',
                'phone',
                'name',
            ],
            'Perkara' => [
                'nomor surat',
                'tersangka',
                'resi',
                'request_number',
                'reason',
            ],
            'Sampel' => [
                'sample_count',
            ],
            'Status' => [
                'current_status',
                'milestones',
                'this_month',
                'this_year',
                'in_progress',
                'ready',
                'completed',
            ],
            'Laporan Periodik' => [
                'period_label',
                'period_start',
                'period_end',
                'generated_at',
                'total_requests',
                'total_samples',
                'report_url',
            ],
        ];
    }

    /**
     * Get category labels for UI.
     */
    public function getCategoryLabels(): array
    {
        return [
            'milestone' => 'Notifikasi Milestone',
            'command' => 'Respons Command',
            'auto_reply' => 'Auto Reply',
            'system' => 'Peringatan Sistem',
            'task' => 'Notifikasi Tugas',
        ];
    }

    /**
     * Get template labels for UI.
     */
    public function getTemplateLabels(): array
    {
        return [
            'milestone' => [
                'REQUEST_RECEIVED' => 'Permintaan Diterima',
                'REQUEST_REJECTED' => 'Permintaan Ditolak',
                'READY_FOR_PICKUP' => 'Siap Diambil',
                'HANDOVER_COMPLETED' => 'Serah Terima Selesai',
            ],
            'command' => [
                'HELP' => 'Menu Bantuan (/help)',
                'HELP_ADMIN' => 'Menu Admin (tambahan)',
                'RESI_NOT_FOUND' => 'Resi Tidak Ditemukan',
                'RESI_FORMAT_ERROR' => 'Format Resi Salah',
                'RESI_TRACKING' => 'Hasil Tracking Resi',
                'UNKNOWN_COMMAND' => 'Command Tidak Dikenal',
                'COMMAND_ERROR' => 'Error Saat Proses',
                'STATUS_REPORT' => 'Laporan Status (/status)',
                'RESTART_SUCCESS' => 'Restart Berhasil',
                'RESTART_UNAUTHORIZED' => 'Tidak Berwenang',
                'WHITELIST_ADDED' => 'Whitelist Ditambahkan',
                'WHITELIST_REMOVED' => 'Whitelist Dihapus',
                'WHITELIST_NOT_FOUND' => 'Whitelist Tidak Ditemukan',
                'WHITELIST_UNAUTHORIZED' => 'Whitelist Unauthorized',
                'ACCESS_DENIED' => 'Akses Ditolak',
            ],
            'auto_reply' => [
                'WELCOME' => 'Pesan Selamat Datang',
                'NO_COMMAND' => 'Bukan Command',
            ],
            'system' => [
                'TEMPERATURE_ALERT' => 'Peringatan Suhu',
                'STOCK_LOW' => 'Stok Menipis',
                'STOCK_EXPIRING' => 'Stok Kadaluarsa',
                'CONSOLIDATED_BIWEEKLY' => 'Laporan Bi-Weekly',
                'CONSOLIDATED_MONTHLY' => 'Laporan Monthly',
                'CONSOLIDATED_QUARTERLY' => 'Laporan Quarterly',
            ],
            'task' => [
                'TASK_ASSIGNED' => 'Tugas Baru Diberikan',
                'TASK_STATUS_CHANGED' => 'Status Tugas Berubah',
            ],
        ];
    }

    /**
     * Preview a template with sample data.
     */
    public function preview(string $category, string $key, ?string $customTemplate = null): string
    {
        $template = $customTemplate ?? $this->get($category, $key);
        $sampleData = $this->getSampleData($category, $key);

        return $this->renderWithReplacements($template, $sampleData);
    }

    /**
     * Get sample data for preview.
     */
    private function getSampleData(string $category, string $key): array
    {
        $samples = [
            'greetings' => 'Selamat Pagi',
            'pangkat' => 'IPDA',
            'nama' => 'Budi Santoso',
            'nomor surat' => 'B/001/I/2026/Reskrim',
            'tersangka' => 'Tersangka ABC',
            'resi' => 'LPMF/001/2026',
            'reason' => 'Sampel tidak memenuhi syarat',
            'command' => '/unknown',
            'request_number' => 'REQ-2026-0001',
            'investigator' => 'IPDA Budi Santoso',
            'milestones' => "✅ 1. Permintaan\n   🕒 15 Jan 2026, 10:00\n\n✅ 2. Kaji Ulang Permintaan\n   🕒 16 Jan 2026, 09:00\n\n🟡 3. Pengujian\n   ▪️ 3.1 Preparasi sampel: ✅ Selesai - selesai 17 Jan 2026, 07:40\n   ▪️ 3.2 Pengujian pada instrumen: *🟡 Sedang berjalan* - mulai 17 Jan 2026, 08:00\n   ▪️ 3.3 Interpretasi hasil: ⚪️ Menunggu\n   🕒 Waktu mulai tahap 3: 17 Jan 2026, 08:00\n\n⚪️ 4. Siap Diserahkan\n⚪️ 5. Selesai",
            'current_status' => '🟡 Tahap 3 dari 5 - Pengujian sedang berjalan',
            'sample_count' => '3',
            'this_month' => '25',
            'this_year' => '150',
            'in_progress' => '10',
            'ready' => '5',
            'completed' => '135',
            'timestamp' => now()->format('d M Y H:i'),
            'period_label' => 'Bi-weekly 01-15 Februari 2026',
            'period_start' => '01/02/2026',
            'period_end' => '15/02/2026',
            'generated_at' => now()->format('d/m/Y H:i'),
            'total_requests' => '42',
            'total_samples' => '87',
            'report_url' => 'https://lpmf.web.id/statistics?tab=reports',
            'location' => 'Ruang Lab 1',
            'temperature' => '28.5',
            'threshold' => '25.0',
            'item' => 'Alkohol 96%',
            'qty' => '5',
            'unit' => 'Liter',
            'expiry_date' => '30 Jan 2026',
            // Task placeholders
            'assignee_name' => 'Analis Budi',
            'assigner_name' => 'Kepala Lab',
            'title' => 'Pengujian Sampel A-001',
            'description' => 'Lakukan pengujian kualitatif untuk sampel A-001',
            'priority' => 'Tinggi',
            'due_at' => '25 Jan 2026 16:00',
            'status' => 'Selesai',
            'completed_at' => '24 Jan 2026 15:30',
            // Whitelist placeholders
            'phone' => '628123456789',
        ];

        return $samples;
    }

    /**
     * Render template with replacements.
     */
    private function renderWithReplacements(string $template, array $replacements): string
    {
        foreach ($replacements as $placeholder => $value) {
            $template = str_replace("{{$placeholder}}", $value ?? '', $template);
        }

        return $template;
    }

    /**
     * Clear template cache.
     */
    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
