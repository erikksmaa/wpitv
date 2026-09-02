<?php

namespace App\Support;

use Illuminate\Session\Store;

class CoverageReceiptAuthorization
{
    public const SESSION_KEY = 'peliputan.receipts';

    public const LIFETIME_MINUTES = 60;

    public const MAX_RECEIPTS = 5;

    public function grant(Store $session, string $submissionCode): void
    {
        $receipts = $this->activeReceipts($session);
        unset($receipts[$submissionCode]);
        $receipts[$submissionCode] = now()->timestamp;

        $session->put(self::SESSION_KEY, array_slice($receipts, -self::MAX_RECEIPTS, null, true));
    }

    public function allows(Store $session, string $submissionCode): bool
    {
        $receipts = $this->activeReceipts($session);
        $session->put(self::SESSION_KEY, $receipts);

        return isset($receipts[$submissionCode]);
    }

    private function activeReceipts(Store $session): array
    {
        $receipts = $session->get(self::SESSION_KEY, []);
        if (! is_array($receipts)) {
            return [];
        }

        $cutoff = now()->subMinutes(self::LIFETIME_MINUTES)->timestamp;

        return array_filter(
            $receipts,
            static fn ($timestamp, $code): bool => is_string($code)
                && is_int($timestamp)
                && $timestamp > $cutoff
                && $timestamp <= now()->timestamp,
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
