<?php

namespace App\Livewire\Public\Peliputan;

use App\Models\Coverage;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class Create extends Component
{
    public string $name = '';

    public string $email = '';

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
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:25'],
            'event_name' => ['required', 'string', 'max:200'],
            'event_date' => ['required', 'date'],
            'event_time' => ['nullable', 'string', 'max:8'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:3000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'Nama Pemohon / Organisasi',
            'email' => 'Alamat Email',
            'phone' => 'Nomor Telepon / WhatsApp',
            'event_name' => 'Nama Kegiatan / Acara',
            'event_date' => 'Tanggal Kegiatan',
            'event_time' => 'Waktu Acara',
            'location' => 'Lokasi Acara',
            'description' => 'Deskripsi Kebutuhan Peliputan',
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

        $validated = $this->validate();

        try {
            $code = Coverage::generateSubmissionCode();

            Coverage::create([
                'submission_code' => $code,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'event_name' => $validated['event_name'],
                'event_date' => $validated['event_date'],
                'event_time' => ! empty($validated['event_time']) ? $validated['event_time'] : null,
                'location' => $validated['location'],
                'description' => $validated['description'],
                'status' => Coverage::STATUS_PENDING,
            ]);

            RateLimiter::hit($throttleKey, 60);

            return redirect()->route('peliputan.success', $code);
        } catch (\Throwable $e) {
            report($e);
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
