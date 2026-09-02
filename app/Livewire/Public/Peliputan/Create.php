<?php

namespace App\Livewire\Public\Peliputan;

use App\Models\Coverage;
use App\Support\CoverageReceiptAuthorization;
use App\Support\CoverageWhatsApp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class Create extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $event_name = '';

    public string $event_date = '';

    public string $event_time = '';

    public string $location = '';

    public string $description = '';

    public function mount(): void
    {
        $this->event_date = date('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => [
                'required',
                'string',
                'max:25',
                fn ($attribute, $value, $fail) => app(CoverageWhatsApp::class)->normalizePhone($value)
                    ? null
                    : $fail('Nomor Telepon / WhatsApp tidak valid.'),
            ],
            'event_name' => ['required', 'string', 'max:200'],
            'event_date' => ['required', 'date'],
            'event_time' => ['nullable', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:3000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'Nama Pemohon',
            'phone' => 'Nomor Telepon / WhatsApp',
            'event_name' => 'Nama Kegiatan',
            'event_date' => 'Tanggal Kegiatan',
            'event_time' => 'Waktu Kegiatan',
            'location' => 'Lokasi Kegiatan',
            'description' => 'Deskripsi Kegiatan',
        ];
    }

    public function submit()
    {
        $throttleKey = 'peliputan-submit:'.request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('general', "Terlalu banyak permintaan pengajuan. Silakan coba kembali dalam {$seconds} detik.");

            return;
        }

        RateLimiter::hit($throttleKey, 60);

        $validated = $this->validate();

        try {
            $code = Coverage::generateSubmissionCode();

            $coverage = Coverage::create([
                'submission_code' => $code,
                'name' => $validated['name'],
                'phone' => app(CoverageWhatsApp::class)->normalizePhone($validated['phone']),
                'event_name' => $validated['event_name'],
                'event_date' => $validated['event_date'],
                'event_time' => ! empty($validated['event_time']) ? $validated['event_time'] : null,
                'location' => $validated['location'],
                'description' => $validated['description'],
                'status' => Coverage::STATUS_PENDING,
            ]);

            app(CoverageReceiptAuthorization::class)->grant(app('session.store'), $coverage->submission_code);

            return redirect()->route('peliputan.success', $code);
        } catch (\Throwable $e) {
            Log::error('Peliputan submission failed.', [
                'exception_type' => $e::class,
            ]);
            $this->addError('general', 'Pengajuan belum berhasil dikirim karena kendala sistem. Silakan coba sesaat lagi.');
        }
    }

    public function render()
    {
        return view('livewire.public.peliputan.create')->layout('layouts.public', [
            'title' => 'Ajukan Peliputan | WPiTV — Televisi Digital Kampus',
            'description' => 'Formulir permohonan peliputan dan dokumentasi kegiatan kampus oleh tim WPiTV.',
        ]);
    }
}
