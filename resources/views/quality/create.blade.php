@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Buat Dokumen QMH</h1>
            <p class="text-sm text-slate-600">Draft awal akan dibuat sebagai versi E1-R0.</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('quality.documents.store') }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="doc_code">Kode Dokumen</label>
                        <input id="doc_code" name="doc_code" type="text" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-900 focus:ring-slate-900" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="clause">Klausul</label>
                        <select id="clause" name="clause" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-900 focus:ring-slate-900" required>
                            @foreach([4,5,6,7,8] as $clause)
                                <option value="{{ $clause }}">{{ $clause }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="title">Judul</label>
                    <input id="title" name="title" type="text" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-900 focus:ring-slate-900" required>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="doc_type">Jenis Dokumen</label>
                    <select id="doc_type" name="doc_type" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-900 focus:ring-slate-900" required>
                        <option value="sop">SOP</option>
                        <option value="ik">IK</option>
                        <option value="formulir">Formulir</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="change_summary">Ringkasan Perubahan</label>
                    <textarea id="change_summary" name="change_summary" rows="4" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-900 focus:ring-slate-900"></textarea>
                </div>

                <div class="flex justify-end">
                    <a href="{{ route('quality.documents.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
