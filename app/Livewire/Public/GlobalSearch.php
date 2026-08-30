<?php

namespace App\Livewire\Public;

use App\Models\News;
use App\Models\Program;
use App\Models\Video;
use Illuminate\Support\Collection;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public function clear(): void
    {
        $this->query = '';
    }

    public function render()
    {
        $term = trim($this->query);
        $programs = collect();
        $videos = collect();
        $news = collect();

        if (mb_strlen($term) >= 2) {
            $like = '%'.$this->escapeLike($term).'%';

            $programs = Program::query()
                ->active()
                ->where(function ($builder) use ($like) {
                    $builder->where('name', 'like', $like)
                        ->orWhere('description', 'like', $like);
                })
                ->orderBy('name')
                ->limit(5)
                ->get();

            $videos = Video::query()
                ->with('program:id,name')
                ->where('title', 'like', $like)
                ->latest('air_date')
                ->latest('id')
                ->limit(5)
                ->get();

            $news = News::query()
                ->where(function ($builder) use ($like) {
                    $builder->where('title', 'like', $like)
                        ->orWhere('author_name', 'like', $like);
                })
                ->latest('id')
                ->limit(5)
                ->get();
        }

        return view('livewire.public.global-search', [
            'term' => $term,
            'programs' => $programs,
            'videos' => $videos,
            'news' => $news,
            'hasResults' => $this->hasResults($programs, $videos, $news),
        ]);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function hasResults(Collection $programs, Collection $videos, Collection $news): bool
    {
        return $programs->isNotEmpty() || $videos->isNotEmpty() || $news->isNotEmpty();
    }
}
