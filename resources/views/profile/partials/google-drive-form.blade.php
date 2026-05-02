<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Google Drive
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Hubungkan akun Google Drive pribadi untuk uji coba penyimpanan file melalui Drive API v3.
        </p>
    </header>

    <div class="mt-6 space-y-4">
        @php
            $googleDriveStatus = session()->pull('google_drive_status');
            $googleDriveError = session()->pull('google_drive_error');
        @endphp

        @if ($googleDriveStatus)
            <p class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ $googleDriveStatus }}
            </p>
        @endif

        @if ($googleDriveError)
            <p class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $googleDriveError }}
            </p>
        @endif

        @if ($user->googleDriveToken)
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                Google Drive sudah terhubung.
                @if ($user->googleDriveToken->expires_at)
                    Token aktif sampai {{ $user->googleDriveToken->expires_at->timezone(config('app.timezone'))->format('d M Y H:i') }}.
                @endif
            </div>

            <form method="post" action="{{ route('google-drive.disconnect') }}">
                @csrf
                @method('delete')
                <x-danger-button type="submit">
                    Putuskan Google Drive
                </x-danger-button>
            </form>
        @else
            <a href="{{ route('google-drive.connect') }}" class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                Hubungkan Google Drive
            </a>
        @endif
    </div>
</section>
