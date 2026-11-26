<?php

namespace App\Services;

use App\Models\User;
use App\Support\CoverageWhatsApp;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WebsiteSettings
{
    public const KEY_COVERAGE_WHATSAPP = 'coverage_whatsapp_number';

    public const KEY_CONTACT_EMAIL = 'contact_email';

    public const KEY_CONTACT_PHONE = 'contact_phone';

    public const KEY_CONTACT_ADDRESS = 'contact_address';

    public const DEFAULT_CONTACT_EMAIL = 'wpitv@stmik-wp.ac.id';

    public const DEFAULT_CONTACT_PHONE = '(0285) 427817';

    public const DEFAULT_CONTACT_ADDRESS = "Jl. Patriot 25 Lt. 2, Pekalongan\nJawa Tengah, Indonesia";

    public const CACHE_KEY = 'wpitv.website_settings.v1';

    public function tableReady(): bool
    {
        try {
            return Schema::hasTable('wpitv_settings');
        } catch (QueryException) {
            return false;
        }
    }

    public function auditTableReady(): bool
    {
        try {
            return Schema::hasTable('wpitv_setting_events');
        } catch (QueryException) {
            return false;
        }
    }

    /**
     * Load stored settings from database cleanly, returning an empty map if
     * the settings table has not been imported yet.
     *
     * @return array<string, ?string>
     */
    public function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $values = DB::table('wpitv_settings')
                ->pluck('setting_value', 'setting_key')
                ->map(fn ($value) => $value === null ? null : (string) $value)
                ->all();

            Cache::put(self::CACHE_KEY, $values, now()->addMinutes(10));

            return $values;
        } catch (QueryException $error) {
            if (! $this->isMissingSchemaException($error)) {
                report($error);
            }

            return [];
        }
    }

    public function coverageWhatsAppNumber(): ?string
    {
        $stored = $this->all()[self::KEY_COVERAGE_WHATSAPP] ?? null;
        if (is_string($stored) && trim($stored) !== '') {
            $normalized = $this->normalizePhone($stored);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $this->normalizePhone(config('wpitv.whatsapp.admin_number'));
    }

    /**
     * @return 'database'|'env'|'none'
     */
    public function coverageWhatsAppSource(): string
    {
        $stored = $this->all()[self::KEY_COVERAGE_WHATSAPP] ?? null;
        if (is_string($stored) && $this->normalizePhone($stored) !== null) {
            return 'database';
        }

        if ($this->normalizePhone(config('wpitv.whatsapp.admin_number')) !== null) {
            return 'env';
        }

        return 'none';
    }

    public function contactEmail(): ?string
    {
        $all = $this->all();
        if (array_key_exists(self::KEY_CONTACT_EMAIL, $all)) {
            return $this->normalizeEmail($all[self::KEY_CONTACT_EMAIL]);
        }

        return self::DEFAULT_CONTACT_EMAIL;
    }

    public function contactPhone(): ?string
    {
        $all = $this->all();
        if (array_key_exists(self::KEY_CONTACT_PHONE, $all)) {
            return $this->normalizeOfficePhone($all[self::KEY_CONTACT_PHONE]);
        }

        return self::DEFAULT_CONTACT_PHONE;
    }

    public function contactPhoneTelHref(?string $phone = null): ?string
    {
        $target = $phone ?? $this->contactPhone();
        if (! is_string($target) || trim($target) === '') {
            return null;
        }

        $trimmed = trim($target);
        $digits = preg_replace('/[^0-9]/', '', $trimmed) ?? '';
        if ($digits === '') {
            return null;
        }

        $prefix = str_starts_with($trimmed, '+') ? '+' : '';

        return 'tel:'.$prefix.$digits;
    }

    public function contactAddress(): ?string
    {
        $all = $this->all();
        if (array_key_exists(self::KEY_CONTACT_ADDRESS, $all)) {
            return $this->normalizeAddress($all[self::KEY_CONTACT_ADDRESS]);
        }

        return self::DEFAULT_CONTACT_ADDRESS;
    }

    /**
     * @return array{email: ?string, phone: ?string, phone_href: ?string, address: ?string, has_any: bool}
     */
    public function publicContact(): array
    {
        $email = $this->contactEmail();
        $phone = $this->contactPhone();
        $address = $this->contactAddress();

        return [
            'email' => $email,
            'phone' => $phone,
            'phone_href' => $this->contactPhoneTelHref($phone),
            'address' => $address,
            'has_any' => $email !== null || $phone !== null || $address !== null,
        ];
    }

    public function normalizeEmail(?string $email): ?string
    {
        $clean = trim((string) $email);

        return $clean === '' ? null : Str::lower($clean);
    }

    public function normalizeOfficePhone(?string $phone): ?string
    {
        $collapsed = trim((string) preg_replace('/\s+/', ' ', (string) $phone));
        if ($collapsed === '') {
            return null;
        }

        if (preg_match('/^[+]?[0-9\s\-()]{5,30}$/', $collapsed) !== 1) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $collapsed) ?? '';
        $digitLength = strlen($digits);

        if ($digitLength < 5 || $digitLength > 15) {
            return null;
        }

        return $collapsed;
    }

    public function normalizeAddress(?string $address): ?string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", (string) $address);
        $lines = collect(explode("\n", $normalized))
            ->map(fn (string $line): string => trim((string) preg_replace('/[ \t]+/', ' ', $line)))
            ->filter(fn (string $line): bool => $line !== '')
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        return $lines->implode("\n");
    }

    /**
     * @param  array{email?: ?string, phone?: ?string, address?: ?string}  $input
     * @return array{email: ?string, phone: ?string, address: ?string}
     */
    public function saveContactInformation(User $actor, array $input): array
    {
        if (! $this->tableReady()) {
            throw ValidationException::withMessages([
                'contactEmail' => 'Tabel pengaturan website belum tersedia pada database. Terapkan skrip SQL terlebih dahulu.',
            ]);
        }

        $email = $this->normalizeEmail($input['email'] ?? null);
        $rawPhone = trim((string) ($input['phone'] ?? ''));
        $phone = $this->normalizeOfficePhone($rawPhone);

        if ($rawPhone !== '' && $phone === null) {
            throw ValidationException::withMessages([
                'contactPhone' => 'Nomor telepon tidak valid.',
            ]);
        }

        $address = $this->normalizeAddress($input['address'] ?? null);

        $this->persistSettingChanges($actor, [
            self::KEY_CONTACT_EMAIL => $email ?? '',
            self::KEY_CONTACT_PHONE => $phone ?? '',
            self::KEY_CONTACT_ADDRESS => $address ?? '',
        ]);

        return [
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
        ];
    }

    public function saveCoverageWhatsApp(User $actor, string $rawNumber): string
    {
        if (! $this->tableReady()) {
            throw ValidationException::withMessages([
                'whatsappNumber' => 'Tabel pengaturan website belum tersedia pada database. Terapkan skrip SQL terlebih dahulu.',
            ]);
        }

        $normalized = $this->normalizePhone($rawNumber);
        if ($normalized === null) {
            throw ValidationException::withMessages([
                'whatsappNumber' => 'Nomor WhatsApp tidak valid. Gunakan awalan 08, 62, atau +62.',
            ]);
        }

        $this->persistSettingChanges($actor, [
            self::KEY_COVERAGE_WHATSAPP => $normalized,
        ]);

        return $normalized;
    }

    /**
     * Persist changed setting keys atomically with append-only audit rows.
     *
     * @param  array<string, string>  $settings
     */
    private function persistSettingChanges(User $actor, array $settings): void
    {
        $now = now('UTC');
        $auditReady = $this->auditTableReady();

        DB::transaction(function () use ($actor, $settings, $now, $auditReady): void {
            foreach ($settings as $key => $value) {
                $existing = DB::table('wpitv_settings')
                    ->where('setting_key', $key)
                    ->lockForUpdate()
                    ->first(['id', 'setting_value']);

                $beforeValue = $existing?->setting_value;

                if ($existing) {
                    DB::table('wpitv_settings')
                        ->where('id', $existing->id)
                        ->update([
                            'setting_value' => $value,
                            'updated_by' => $actor->id,
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('wpitv_settings')->insert([
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'updated_by' => $actor->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if ($auditReady && ($existing === null || (string) ($beforeValue ?? '') !== $value)) {
                    DB::table('wpitv_setting_events')->insert([
                        'setting_key' => $key,
                        'action' => $existing ? 'updated' : 'created',
                        'actor_id' => $actor->id,
                        'actor_name_snapshot' => $actor->name ?: $actor->username,
                        'before_value' => $beforeValue,
                        'after_value' => $value,
                        'created_at' => $now,
                    ]);
                }
            }
        });

        $this->forgetCache();

        Log::info('Website settings updated.', [
            'setting_keys' => array_keys($settings),
            'actor_id' => $actor->id,
        ]);
    }

    public function recentAuditEvents(int $limit = 10): Collection
    {
        if (! $this->auditTableReady()) {
            return collect();
        }

        try {
            return DB::table('wpitv_setting_events')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(['id', 'setting_key', 'action', 'actor_name_snapshot', 'before_value', 'after_value', 'created_at']);
        } catch (QueryException $error) {
            report($error);

            return collect();
        }
    }

    public function settingLabel(string $key): string
    {
        return match ($key) {
            self::KEY_CONTACT_EMAIL => 'Email WPiTV',
            self::KEY_CONTACT_PHONE => 'Nomor Telepon',
            self::KEY_CONTACT_ADDRESS => 'Alamat',
            self::KEY_COVERAGE_WHATSAPP => 'Nomor WhatsApp Tujuan',
            default => $key,
        };
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function normalizePhone(?string $phone): ?string
    {
        return (new CoverageWhatsApp)->normalizePhone($phone);
    }

    private function isMissingSchemaException(QueryException $error): bool
    {
        $code = (string) ($error->errorInfo[0] ?? $error->getCode());

        return in_array($code, ['42S02', '42S22', 'HY000'], true);
    }
}
