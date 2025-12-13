<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class GenerateBeritaAcara extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'berita-acara:generate 
                            {request_number : Nomor permintaan (e.g., REQ-2025-0001)}
                            {--pdf : Generate PDF output}
                            {--api=http://127.0.0.1:8000/api/requests : Base URL API}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Berita Acara Penerimaan dokumen (HTML/PDF)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $requestNumber = $this->argument('request_number');
        $generatePdf = $this->option('pdf');
        $apiUrl = $this->option('api');

        $this->info("Generating Berita Acara untuk: {$requestNumber}");

        // Path ke Python script
        $scriptPath = base_path('scripts/generate_berita_acara.py');
        
        if (!file_exists($scriptPath)) {
            $this->error("Script generator tidak ditemukan: {$scriptPath}");
            return Command::FAILURE;
        }

        // Build command
        $command = [
            'python',
            $scriptPath,
            '--id', $requestNumber,
            '--api', $apiUrl,
            '--outdir', base_path('output'),
            '--template', base_path('templates/berita_acara_penerimaan.html.j2'),
            '--logo-tribrata', public_path('assets/logo-tribrata-polri.png'),
            '--logo-pusdokkes', public_path('assets/logo-pusdokkes-polri.png'),
        ];

        if ($generatePdf) {
            $command[] = '--pdf';
        }

        // Execute Python script
        try {
            $this->line('Running generator...');
            
            $process = new Process($command);
            $process->setTimeout(60); // 60 seconds timeout
            $process->run(function ($type, $buffer) {
                if (Process::OUT === $type) {
                    $this->line($buffer);
                } else {
                    $this->error($buffer);
                }
            });

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->newLine();
            $this->info('✓ Berita Acara berhasil dibuat!');
            $this->line("Output folder: " . base_path('output'));

            return Command::SUCCESS;

        } catch (ProcessFailedException $e) {
            $this->error('Gagal menjalankan generator:');
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn('Pastikan Python 3 sudah terinstall dan tersedia di PATH');
            $this->warn('Install dependencies: pip install jinja2');
            $this->warn('Untuk PDF: pip install weasyprint (butuh GTK3 runtime di Windows)');
            
            return Command::FAILURE;
        }
    }
}
