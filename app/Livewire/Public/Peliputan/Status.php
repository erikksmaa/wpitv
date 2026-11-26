<?php

namespace App\Livewire\Public\Peliputan;

use App\Models\Coverage;
use App\Support\CoverageWhatsApp;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class Status extends Component
{
    public string $submission_code = '';

    public string $phone = '';

    public ?Coverage $coverageResult = null;

    protected $queryString = [
        'submission_code' => ['except' => ''],
    ];

    protected function rules(): array
    {
        return [
            'submission_code' => ['required', 'string', 'max:30'],
            'phone' => [
                'required',
                'string',
                'max:25',
                fn ($attribute, $value, $fail) => app(CoverageWhatsApp::class)->normalizePhone($value)
                    ? null
                    : $fail('Nomor WhatsApp / Telepon tidak valid.'),
            ],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'submission_code' => 'Kode Pengajuan',
            'phone' => 'Nomor WhatsApp / Telepon',
        ];
    }

    protected function messages(): array
    {
        return [
            'submission_code.required' => 'Kode pengajuan harus diisi.',
            'submission_code.max' => 'Kode pengajuan maksimal 30 karakter.',
            'phone.required' => 'Nomor WhatsApp / Telepon harus diisi.',
            'phone.max' => 'Nomor WhatsApp / Telepon maksimal 25 karakter.',
        ];
    }

    public function check(): void
    {
        $throttleKey = 'peliputan-status:'.request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('lookup', "Terlalu banyak percobaan pencarian. Silakan coba kembali dalam {$seconds} detik.");

            return;
        }

        RateLimiter::hit($throttleKey, 60);

        $this->validate();

        $code = trim($this->submission_code);
        $inputPhone = app(CoverageWhatsApp::class)->normalizePhone($this->phone);

        $coverage = Coverage::where('submission_code', $code)->first();

        if (! $coverage) {
            $this->coverageResult = null;
            $this->addError('lookup', 'Pengajuan dengan kode dan kontak tersebut tidak ditemukan. Pastikan data yang dimasukkan sesuai saat pengajuan.');

            return;
        }

        $whatsApp = app(CoverageWhatsApp::class);
        $recordPhone = $whatsApp->normalizePhone($coverage->phone);
        $phoneMatches = $inputPhone && $recordPhone && hash_equals($recordPhone, $inputPhone);

        if (! $phoneMatches) {
            $this->coverageResult = null;
            $this->addError('lookup', 'Pengajuan dengan kode dan kontak tersebut tidak ditemukan. Pastikan data yang dimasukkan sesuai saat pengajuan.');

            return;
        }

        $this->resetErrorBag('lookup');
        $this->coverageResult = $coverage;
    }

    public function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) {
            return $phone;
        }
        $start = substr($phone, 0, 4);
        $end = substr($phone, -2);

        return $start.str_repeat('*', max(3, $len - 6)).$end;
    }

    public function render()
    {
        return view('livewire.public.peliputan.status', [
            'whatsAppUrl' => $this->coverageResult
                ? app(CoverageWhatsApp::class)->publicToAdminUrl($this->coverageResult)
                : null,
        ])->layout('layouts.public', [
            'title' => 'Cek Status Peliputan | WPiTV — Televisi Digital Kampus',
            'description' => 'Pantau perkembangan status pengajuan peliputan kegiatan oleh tim WPiTV.',
        ]);
    }
}
