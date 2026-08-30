<?php

namespace App\Livewire\Public\Peliputan;

use App\Models\Coverage;
use App\Support\CoverageWhatsApp;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class Status extends Component
{
    public string $submission_code = '';

    public string $contact = '';

    public ?Coverage $coverageResult = null;

    protected $queryString = [
        'submission_code' => ['except' => ''],
    ];

    protected function rules(): array
    {
        return [
            'submission_code' => ['required', 'string', 'max:30'],
            'contact' => ['required', 'string', 'max:150'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'submission_code' => 'Kode Pengajuan',
            'contact' => 'Nomor Telepon / WhatsApp / Email',
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

        $this->validate();

        $code = trim($this->submission_code);
        $contactInput = strtolower(trim($this->contact));

        $coverage = Coverage::where('submission_code', $code)->first();

        RateLimiter::hit($throttleKey, 60);

        if (! $coverage) {
            $this->coverageResult = null;
            $this->addError('lookup', 'Pengajuan dengan kode dan kontak tersebut tidak ditemukan. Pastikan data yang dimasukkan sesuai saat pengajuan.');

            return;
        }

        $whatsApp = app(CoverageWhatsApp::class);
        $recordPhone = $whatsApp->normalizePhone($coverage->phone);
        $inputPhone = $whatsApp->normalizePhone($contactInput);
        $recordEmail = strtolower(trim($coverage->email));

        $phoneMatches = $inputPhone && $recordPhone && hash_equals($recordPhone, $inputPhone);

        $emailMatches = ! empty($contactInput) && $contactInput === $recordEmail;

        if (! $phoneMatches && ! $emailMatches) {
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

    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }
        $name = $parts[0];
        $domain = $parts[1];
        $maskedName = substr($name, 0, 1).str_repeat('*', max(3, strlen($name) - 1));

        return $maskedName.'@'.$domain;
    }

    public function render()
    {
        return view('livewire.public.peliputan.status', [
            'whatsAppUrl' => $this->coverageResult
                ? app(CoverageWhatsApp::class)->publicToAdminUrl($this->coverageResult)
                : null,
        ])->layout('layouts.public', [
            'title' => 'Cek Status Peliputan | WPiTV — Televisi Digital Kampus',
            'description' => 'Periksa perkembangan status pengajuan peliputan kegiatan Anda oleh tim redaksi WPiTV.',
        ]);
    }
}
