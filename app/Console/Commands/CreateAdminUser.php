<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create 
                            {--name= : Nama lengkap admin}
                            {--email= : Email admin}
                            {--password= : Password admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membuat user admin baru untuk akses sistem';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Membuat User Admin Baru ===');
        $this->newLine();

        // Get input atau gunakan default
        $name = $this->option('name') ?: $this->ask('Nama Lengkap', 'Administrator');
        $email = $this->option('email') ?: $this->ask('Email', 'admin@pusdokkes.com');
        $password = $this->option('password') ?: $this->secret('Password (minimal 8 karakter)');

        // Validation
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            $this->error('Validasi gagal:');
            foreach ($validator->errors()->all() as $error) {
                $this->error('- ' . $error);
            }
            return Command::FAILURE;
        }

        // Cek apakah email sudah ada
        if (User::where('email', $email)->exists()) {
            $this->error("Email '{$email}' sudah terdaftar!");
            
            if ($this->confirm('Update user yang sudah ada?')) {
                $user = User::where('email', $email)->first();
                $user->update([
                    'name' => $name,
                    'password' => Hash::make($password),
                    'role' => 'admin',
                    'email_verified_at' => now(),
                ]);
                
                $this->info("✓ User admin '{$name}' berhasil diupdate!");
            } else {
                return Command::FAILURE;
            }
        } else {
            // Create user baru
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);

            $this->newLine();
            $this->info('✓ User admin berhasil dibuat!');
        }

        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $user->id],
                ['Nama', $user->name],
                ['Email', $user->email],
                ['Role', $user->role],
            ]
        );

        $this->newLine();
        $this->info('Sekarang Anda bisa login dengan kredensial di atas.');
        $this->info('URL Login: http://127.0.0.1:8000/login');

        return Command::SUCCESS;
    }
}
