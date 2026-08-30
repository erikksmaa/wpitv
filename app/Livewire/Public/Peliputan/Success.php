<?php

namespace App\Livewire\Public\Peliputan;

use App\Models\Coverage;
use App\Support\CoverageWhatsApp;
use Livewire\Component;

class Success extends Component
{
    public Coverage $coverage;

    public function mount(string $code): void
    {
        $this->coverage = Coverage::where('submission_code', $code)->firstOrFail();
    }

    public function render()
    {
        return view('livewire.public.peliputan.success', [
            'whatsAppUrl' => app(CoverageWhatsApp::class)->publicToAdminUrl($this->coverage),
        ])->layout('layouts.public', [
            'title' => 'Pengajuan Berhasil | Peliputan WPiTV',
            'description' => 'Permohonan peliputan berhasil dikirimkan ke tim WPiTV.',
        ]);
    }
}
