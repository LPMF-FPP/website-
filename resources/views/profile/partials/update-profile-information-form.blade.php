<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="phone" :value="__('Nomor WhatsApp')" />
            <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $user->phone)" placeholder="08123456789" autocomplete="tel" />
            <p class="mt-1 text-xs text-gray-500">Format: 08xxx atau +628xxx (untuk notifikasi tugas)</p>
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="title_prefix" :value="__('Gelar Depan')" />
                <x-text-input id="title_prefix" name="title_prefix" type="text" class="mt-1 block w-full" :value="old('title_prefix', $user->title_prefix)" placeholder="Dr." autocomplete="off" />
                <x-input-error class="mt-2" :messages="$errors->get('title_prefix')" />
            </div>

            <div>
                <x-input-label for="title_suffix" :value="__('Gelar Belakang')" />
                <x-text-input id="title_suffix" name="title_suffix" type="text" class="mt-1 block w-full" :value="old('title_suffix', $user->title_suffix)" placeholder="S.Farm., Apt." autocomplete="off" />
                <x-input-error class="mt-2" :messages="$errors->get('title_suffix')" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="rank" :value="__('Pangkat')" />
                <x-text-input id="rank" name="rank" type="text" class="mt-1 block w-full" :value="old('rank', $user->rank)" placeholder="AKP" autocomplete="off" />
                <x-input-error class="mt-2" :messages="$errors->get('rank')" />
            </div>

            <div>
                <x-input-label for="nrp" :value="__('NRP')" />
                <x-text-input id="nrp" name="nrp" type="text" class="mt-1 block w-full" :value="old('nrp', $user->nrp)" placeholder="70040687" autocomplete="off" />
                <x-input-error class="mt-2" :messages="$errors->get('nrp')" />
            </div>
        </div>

        <div>
            <x-input-label for="nip" :value="__('NIP')" />
            <x-text-input id="nip" name="nip" type="text" class="mt-1 block w-full" :value="old('nip', $user->nip)" placeholder="198001012006041001" autocomplete="off" />
            <x-input-error class="mt-2" :messages="$errors->get('nip')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p id="profile-updated-flash" class="text-sm text-gray-600">{{ __('Saved.') }}</p>
                @push('scripts')
                <script>
                    setTimeout(()=>{
                        const el = document.getElementById('profile-updated-flash');
                        if(el) el.remove();
                    }, 2000);
                </script>
                @endpush
            @endif
        </div>
    </form>
</section>
