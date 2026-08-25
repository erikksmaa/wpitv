<?php

namespace App\Livewire\Admin\Videos;

use App\Models\Program;
use App\Models\Video;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?Video $video = null;

    public ?int $videoId = null;

    public ?int $program_id = null;

    public string $title = '';

    public string $youtube_input = '';

    public string $youtube_id = '';

    public ?string $description = '';

    public string $air_date = '';

    public string $tag = '0';

    public ?int $user_id = null;

    // Read-only metadata on edit
    public ?string $uploaderName = null;

    public ?string $createdAtFormatted = null;

    public ?string $updatedAtFormatted = null;

    public function mount(?Video $video = null): void
    {
        if ($video && $video->exists) {
            $this->authorize('update', $video);

            $this->video = $video;
            $this->videoId = $video->id;
            $this->program_id = $video->program_id;
            $this->title = $video->title;
            $this->youtube_id = $video->youtube_id;
            $this->youtube_input = $video->youtube_id;
            $this->description = $video->description ?? '';
            $this->air_date = $video->air_date ? $video->air_date->format('Y-m-d') : '';
            $this->tag = $video->tag ?? '0';
            $this->user_id = $video->user_id;

            $this->uploaderName = $video->user ? $video->user->name : 'Legacy / System';
            $this->createdAtFormatted = $video->created_at ? $video->created_at->format('d M Y H:i') : '-';
            $this->updatedAtFormatted = $video->updated_at ? $video->updated_at->format('d M Y H:i') : '-';
        } else {
            $this->authorize('create', Video::class);

            $this->air_date = now()->toDateString();
            $this->tag = '0';
            $this->user_id = Auth::id();
        }
    }

    public static function extractYouTubeId(string $input): ?string
    {
        $input = trim($input);
        if (empty($input)) {
            return null;
        }

        // Direct 11-char ID
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $input)) {
            return $input;
        }

        // Standard watch URL: v=XXXXXXXXXXX
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $input, $matches)) {
            return $matches[1];
        }

        // youtu.be / embed / shorts: /XXXXXXXXXXX
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed|shorts|v|e)\/)([a-zA-Z0-9_-]{11})/', $input, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function updatedYoutubeInput(string $value): void
    {
        $extracted = self::extractYouTubeId($value);
        $this->youtube_id = $extracted ?? '';
    }

    protected function rules(): array
    {
        return [
            'program_id' => ['nullable', 'exists:ms_program,id'],
            'title' => ['required', 'string', 'max:200'],
            'youtube_input' => ['required', 'string'],
            'air_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'tag' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'program_id' => 'Program Acara',
            'title' => 'Judul Video',
            'youtube_input' => 'Tautan / ID YouTube',
            'air_date' => 'Tanggal Tayang',
            'description' => 'Deskripsi Video',
            'tag' => 'Tag',
        ];
    }

    public function save()
    {
        if ($this->videoId) {
            $this->authorize('update', $this->video);
        } else {
            $this->authorize('create', Video::class);
        }

        $this->validate();

        // Extract and validate YouTube ID
        $extractedId = self::extractYouTubeId($this->youtube_input);
        if (! $extractedId) {
            $this->addError('youtube_input', 'Format tautan atau ID YouTube tidak valid. Masukkan URL video YouTube lengkap (contoh: https://www.youtube.com/watch?v=XYZ) atau 11 karakter ID YouTube langsung.');

            return;
        }

        $this->youtube_id = $extractedId;

        $data = [
            'program_id' => $this->program_id ?: null,
            'title' => $this->title,
            'youtube_id' => $this->youtube_id,
            'description' => $this->description ?: null,
            'air_date' => $this->air_date,
            'tag' => $this->tag !== '' ? $this->tag : '0',
        ];

        if ($this->videoId) {
            // Preserve original user_id on edit
            $this->video->update($data);
            session()->flash('success', "Video \"{$this->title}\" berhasil diperbarui.");
        } else {
            $data['user_id'] = Auth::id();
            Video::create($data);
            session()->flash('success', "Video \"{$this->title}\" berhasil ditambahkan.");
        }

        return redirect()->route('admin.videos.index');
    }

    public function render()
    {
        $isEdit = (bool) $this->videoId;
        $programs = Program::orderBy('name')->get(['id', 'name']);

        return view('livewire.admin.videos.form', [
            'isEdit' => $isEdit,
            'programs' => $programs,
        ])->title(($isEdit ? 'Edit Tayangan: '.$this->title : 'Tambah Tayangan Baru').' - Admin WPiTV');
    }
}
