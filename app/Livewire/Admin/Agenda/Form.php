<?php

namespace App\Livewire\Admin\Agenda;

use App\Models\Coverage;
use App\Models\Program;
use App\Models\TVClubGroup;
use App\Models\WpitvAgenda;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Form Agenda WPiTV')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?WpitvAgenda $agenda = null;

    public ?int $agendaId = null;

    public string $title = '';

    public string $type = 'other';

    public ?int $program_id = null;

    public ?int $coverage_id = null;

    public ?int $group_id = null;

    public string $agenda_date = '';

    public string $start_time = '';

    public string $end_time = '';

    public string $location = '';

    public string $description = '';

    public string $status = 'draft';

    public string $visibility = 'internal';

    public bool $fromCoverage = false;

    public function mount(?WpitvAgenda $agenda = null, ?Coverage $coverage = null): void
    {
        if ($agenda?->exists) {
            $this->authorize('update', $agenda);
            $this->agenda = $agenda;
            $this->agendaId = $agenda->id;
            foreach (['title', 'type', 'program_id', 'coverage_id', 'group_id', 'location', 'description', 'status', 'visibility'] as $field) {
                $this->{$field} = $agenda->{$field} ?? ($this->{$field});
            }
            $this->agenda_date = $agenda->agenda_date->format('Y-m-d');
            $this->start_time = $agenda->start_time ? substr($agenda->start_time, 0, 5) : '';
            $this->end_time = $agenda->end_time ? substr($agenda->end_time, 0, 5) : '';
        } else {
            $this->authorize('create', WpitvAgenda::class);
            $this->agenda_date = request('date', now()->format('Y-m-d'));
            if ($coverage?->exists) {
                $this->fromCoverage = true;
                $this->coverage_id = $coverage->id;
                $this->type = 'coverage';
                $this->title = 'Peliputan '.$coverage->event_name;
                $this->agenda_date = $coverage->event_date->format('Y-m-d');
                $this->start_time = $coverage->event_time ? substr($coverage->event_time, 0, 5) : '';
                $this->location = $coverage->location;
            }
        }
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(array_keys(WpitvAgenda::TYPES))],
            'program_id' => ['nullable', 'integer', 'exists:ms_program,id'],
            'coverage_id' => ['nullable', 'integer', 'exists:tr_perliputan,id'],
            'group_id' => ['nullable', 'integer', 'exists:tvclub_groups,id'],
            'agenda_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_keys(WpitvAgenda::STATUSES))],
            'visibility' => ['required', Rule::in(array_keys(WpitvAgenda::VISIBILITIES))],
        ];
    }

    protected function messages(): array
    {
        return ['end_time.after_or_equal' => 'Waktu selesai tidak boleh lebih awal dari waktu mulai.'];
    }

    public function save()
    {
        $this->authorize($this->agenda?->exists ? 'update' : 'create', $this->agenda?->exists ? $this->agenda : WpitvAgenda::class);

        $data = $this->validate();
        foreach (['program_id', 'coverage_id', 'group_id'] as $field) {
            $data[$field] = $data[$field] ?: null;
        }
        foreach (['start_time', 'end_time', 'location', 'description'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        if ($this->agendaId) {
            $agenda = WpitvAgenda::findOrFail($this->agendaId);
            $this->authorize('update', $agenda);
            $agenda->update($data);
            session()->flash('success', 'Data agenda berhasil diubah.');
        } else {
            $this->authorize('create', WpitvAgenda::class);
            $data['created_by'] = auth()->id();
            WpitvAgenda::create($data);
            session()->flash('success', 'Data agenda berhasil ditambahkan.');
        }

        return redirect()->route('admin.agenda.index');
    }

    public function render()
    {
        $this->authorize($this->agenda?->exists ? 'update' : 'create', $this->agenda?->exists ? $this->agenda : WpitvAgenda::class);

        return view('livewire.admin.agenda.form', [
            'programs' => Program::orderBy('name')->get(['id', 'name']),
            'coverages' => Coverage::orderByDesc('event_date')->limit(200)->get(['id', 'submission_code', 'event_name', 'event_date']),
            'groups' => TVClubGroup::with(['program:id,name', 'generation:id,name'])->orderBy('name')->get(),
        ]);
    }
}
