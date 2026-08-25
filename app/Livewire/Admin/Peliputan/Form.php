<?php

namespace App\Livewire\Admin\Peliputan;

use App\Models\Coverage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?Coverage $coverage = null;

    public ?int $coverageId = null;

    public string $submission_code = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $event_name = '';

    public string $event_date = '';

    public string $event_time = '';

    public string $location = '';

    public string $description = '';

    public string $status = 'pending';

    public ?string $admin_note = '';

    public function mount(?Coverage $coverage = null): void
    {
        if ($coverage && $coverage->exists) {
            $this->authorize('update', $coverage);

            $this->coverage = $coverage;
            $this->coverageId = $coverage->id;
            $this->submission_code = $coverage->submission_code;
            $this->name = $coverage->name;
            $this->email = $coverage->email;
            $this->phone = $coverage->phone;
            $this->event_name = $coverage->event_name;
            $this->event_date = $coverage->event_date ? $coverage->event_date->format('Y-m-d') : '';
            $this->event_time = $coverage->event_time ? substr($coverage->event_time, 0, 5) : '';
            $this->location = $coverage->location;
            $this->description = $coverage->description;
            $this->status = $coverage->status ?? 'pending';
            $this->admin_note = $coverage->admin_note ?? '';
        } else {
            $this->authorize('create', Coverage::class);

            $this->submission_code = Coverage::generateSubmissionCode();
            $this->status = 'pending';
            $this->event_date = date('Y-m-d');
        }
    }

    protected function rules(): array
    {
        return [
            'submission_code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('tr_perliputan', 'submission_code')->ignore($this->coverageId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:25'],
            'event_name' => ['required', 'string', 'max:200'],
            'event_date' => ['required', 'date'],
            'event_time' => ['nullable', 'string', 'max:8'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'status' => ['required', 'string', Rule::in(array_keys(Coverage::getStatusOptions()))],
            'admin_note' => ['nullable', 'string'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'submission_code' => 'Kode Pengajuan',
            'name' => 'Nama Pemohon',
            'email' => 'Email Pemohon',
            'phone' => 'Nomor Telepon / WhatsApp',
            'event_name' => 'Nama Agenda / Acara',
            'event_date' => 'Tanggal Acara',
            'event_time' => 'Waktu Acara',
            'location' => 'Lokasi Acara',
            'description' => 'Deskripsi Kebutuhan Peliputan',
            'status' => 'Status Permohonan',
            'admin_note' => 'Catatan Admin',
        ];
    }

    public function save()
    {
        if ($this->coverageId) {
            $this->authorize('update', $this->coverage);
        } else {
            $this->authorize('create', Coverage::class);
        }

        $validated = $this->validate();

        if (empty($validated['event_time'])) {
            $validated['event_time'] = null;
        }

        if ($this->coverageId) {
            $this->coverage->update($validated);
            session()->flash('success', "Permohonan peliputan \"{$this->submission_code}\" berhasil diperbarui.");
        } else {
            Coverage::create($validated);
            session()->flash('success', "Permohonan peliputan \"{$this->submission_code}\" berhasil ditambahkan.");
        }

        return redirect()->route('admin.peliputan.index');
    }

    public function render()
    {
        $isEdit = (bool) $this->coverageId;

        return view('livewire.admin.peliputan.form', [
            'isEdit' => $isEdit,
            'statusOptions' => Coverage::getStatusOptions(),
        ])->title(($isEdit ? 'Edit Peliputan: '.$this->submission_code : 'Tambah Permohonan Peliputan').' - WPiTV Admin');
    }
}
