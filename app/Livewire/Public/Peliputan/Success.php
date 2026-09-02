<?php

namespace App\Livewire\Public\Peliputan;

use App\Models\Coverage;
use App\Support\CoverageReceiptAuthorization;
use App\Support\CoverageWhatsApp;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Success extends Component
{
    #[Locked]
    public string $code;

    public function mount(string $code, CoverageReceiptAuthorization $receipts): void
    {
        abort_unless($receipts->allows(app('session.store'), $code), 404);
        $this->code = $code;
    }

    public function render(CoverageReceiptAuthorization $receipts)
    {
        abort_unless($receipts->allows(app('session.store'), $this->code), 404);
        $coverage = Coverage::where('submission_code', $this->code)->firstOrFail();

        return view('livewire.public.peliputan.success', [
            'coverage' => $coverage,
            'whatsAppUrl' => app(CoverageWhatsApp::class)->publicToAdminUrl($coverage),
        ])->layout('layouts.public', [
            'title' => 'Pengajuan Berhasil | Peliputan WPiTV',
            'description' => 'Permohonan peliputan berhasil dikirimkan ke tim WPiTV.',
        ]);
    }
}
