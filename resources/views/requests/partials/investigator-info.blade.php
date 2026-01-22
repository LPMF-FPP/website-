<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            Data Penyidik
        </h3>
        <dl class="space-y-3 text-sm text-gray-700 bg-gray-50 p-4 rounded-lg border border-gray-100">
            <div class="flex justify-between border-b border-gray-200 pb-2">
                <dt class="font-medium text-gray-500">Nama</dt>
                <dd class="font-semibold text-gray-900">{{ $request->investigator->name ?? '-' }}</dd>
            </div>
            <div class="flex justify-between border-b border-gray-200 pb-2">
                <dt class="font-medium text-gray-500">NRP</dt>
                <dd class="text-gray-900">{{ $request->investigator->nrp ?? '-' }}</dd>
            </div>
            <div class="flex justify-between border-b border-gray-200 pb-2">
                <dt class="font-medium text-gray-500">Pangkat</dt>
                <dd class="text-gray-900">{{ $request->investigator->rank ?? '-' }}</dd>
            </div>
            <div class="flex justify-between border-b border-gray-200 pb-2">
                <dt class="font-medium text-gray-500">Satuan</dt>
                <dd class="text-gray-900">{{ $request->investigator->jurisdiction ?? '-' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="font-medium text-gray-500">Kontak</dt>
                <dd class="text-gray-900">{{ $request->investigator->phone ?? '-' }}</dd>
            </div>
        </dl>
    </div>

    <div>
        <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Informasi Kasus & Tersangka
        </h3>
        <dl class="space-y-3 text-sm text-gray-700 bg-gray-50 p-4 rounded-lg border border-gray-100">
             <div class="flex justify-between border-b border-gray-200 pb-2">
                <dt class="font-medium text-gray-500">Nomor Surat</dt>
                <dd class="font-semibold text-gray-900">{{ $request->case_number ?? '-' }}</dd>
            </div>
             <div class="flex justify-between border-b border-gray-200 pb-2">
                <dt class="font-medium text-gray-500">Tanggal Surat</dt>
                <dd class="text-gray-900">{{ $request->letter_date ? $request->letter_date->format('d M Y') : '-' }}</dd>
            </div>
            <div class="pt-2">
                <dt class="font-medium text-gray-500 mb-2">Daftar Tersangka:</dt>
                <dd>
                    @if($request->suspects->count() > 0)
                        <ul class="list-decimal list-inside space-y-1">
                            @foreach($request->suspects as $suspect)
                                <li class="text-gray-900">
                                    <span class="font-semibold">{{ $suspect->name }}</span>
                                    @if($suspect->age || $suspect->gender)
                                        <span class="text-gray-500 text-xs ml-1">
                                            ({{ $suspect->gender === 'male' ? 'L' : ($suspect->gender === 'female' ? 'P' : '-') }}, {{ $suspect->age ?? '-' }} th)
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                         <div class="flex justify-between border-b border-gray-200 pb-2">
                            <span class="text-gray-900">{{ $request->suspect_name ?? '-' }}</span>
                             <span class="text-gray-500 text-xs">
                                ({{ $request->suspect_gender === 'male' ? 'L' : ($request->suspect_gender === 'female' ? 'P' : '-') }}, {{ $request->suspect_age !== null ? $request->suspect_age . ' th' : '-' }})
                            </span>
                        </div>
                    @endif
                </dd>
            </div>
        </dl>
    </div>
</div>
