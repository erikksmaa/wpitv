<?php

namespace App\Livewire\Admin\Settings;

use App\Services\WebsiteSettings;
use App\Support\CoverageWhatsApp;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.admin')]
#[Title('Pengaturan Website - WPiTV')]
class Index extends Component
{
    public string $contactEmail = '';

    public string $contactPhone = '';

    public string $contactAddress = '';

    public string $whatsappNumber = '';

    public function mount(WebsiteSettings $settings): void
    {
        Gate::authorize('manage-website-settings');

        $this->contactEmail = (string) ($settings->contactEmail() ?? '');
        $this->contactPhone = (string) ($settings->contactPhone() ?? '');
        $this->contactAddress = (string) ($settings->contactAddress() ?? '');
        $this->whatsappNumber = (string) ($settings->coverageWhatsAppNumber() ?? '');
    }

    protected function contactRules(): array
    {
        return [
            'contactEmail' => ['nullable', 'string', 'max:150', 'email:rfc'],
            'contactPhone' => [
                'nullable',
                'string',
                'max:30',
                function ($attribute, $value, $fail): void {
                    if (trim((string) $value) !== '' && (new WebsiteSettings)->normalizeOfficePhone((string) $value) === null) {
                        $fail('Nomor telepon tidak valid.');
                    }
                },
            ],
            'contactAddress' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function coverageRules(): array
    {
        return [
            'whatsappNumber' => [
                'required',
                'string',
                'max:30',
                fn ($attribute, $value, $fail) => (new CoverageWhatsApp)->normalizePhone($value) !== null
                    ? null
                    : $fail('Nomor WhatsApp tidak valid.'),
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'contactEmail.email' => 'Format email tidak valid.',
            'contactEmail.max' => 'Format email tidak valid.',
            'contactPhone.max' => 'Nomor telepon tidak valid.',
            'contactAddress.max' => 'Alamat terlalu panjang.',
            'whatsappNumber.required' => 'Nomor WhatsApp harus diisi.',
            'whatsappNumber.max' => 'Nomor WhatsApp maksimal 30 karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'contactEmail' => 'Email WPiTV',
            'contactPhone' => 'Nomor Telepon',
            'contactAddress' => 'Alamat',
            'whatsappNumber' => 'Nomor WhatsApp Tujuan',
        ];
    }

    public function saveContact(WebsiteSettings $settings): void
    {
        Gate::authorize('manage-website-settings');

        $this->contactEmail = $settings->normalizeEmail($this->contactEmail) ?? '';
        $this->contactPhone = trim((string) preg_replace('/\s+/', ' ', $this->contactPhone));
        $this->contactAddress = $settings->normalizeAddress($this->contactAddress) ?? '';

        $this->validate($this->contactRules());

        try {
            $saved = $settings->saveContactInformation(auth()->user(), [
                'email' => $this->contactEmail,
                'phone' => $this->contactPhone,
                'address' => $this->contactAddress,
            ]);
        } catch (ValidationException $error) {
            throw $error;
        } catch (Throwable $error) {
            report($error);
            session()->flash('settings_error', 'Pengaturan gagal disimpan.');

            return;
        }

        $this->contactEmail = (string) ($saved['email'] ?? '');
        $this->contactPhone = (string) ($saved['phone'] ?? '');
        $this->contactAddress = (string) ($saved['address'] ?? '');

        session()->flash('settings_notice', 'Informasi kontak berhasil diperbarui.');
    }

    public function save(WebsiteSettings $settings): void
    {
        Gate::authorize('manage-website-settings');

        $this->whatsappNumber = trim($this->whatsappNumber);
        $this->validate($this->coverageRules());

        try {
            $normalized = $settings->saveCoverageWhatsApp(auth()->user(), $this->whatsappNumber);
        } catch (ValidationException $error) {
            throw $error;
        } catch (Throwable $error) {
            report($error);
            session()->flash('settings_error', 'Pengaturan gagal disimpan.');

            return;
        }

        $this->whatsappNumber = $normalized;

        session()->flash('settings_notice', 'Pengaturan berhasil disimpan.');
    }

    public function render(WebsiteSettings $settings)
    {
        Gate::authorize('manage-website-settings');

        $helper = new CoverageWhatsApp;
        $previewNormalized = $helper->normalizePhone($this->whatsappNumber);
        $activeNumber = $settings->coverageWhatsAppNumber();

        return view('livewire.admin.settings.index', [
            'schemaReady' => $settings->tableReady(),
            'activeNumber' => $activeNumber,
            'activeSource' => $settings->coverageWhatsAppSource(),
            'previewUrl' => $previewNormalized ? 'https://wa.me/'.$previewNormalized : null,
            'previewDisplay' => $previewNormalized ? 'wa.me/'.$previewNormalized : null,
            'events' => $settings->recentAuditEvents(10),
            'settingsService' => $settings,
        ]);
    }
}
