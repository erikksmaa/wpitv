<?php

namespace App\Livewire\Public\News;

use App\Models\News;
use Illuminate\Support\Str;
use Livewire\Component;

class Show extends Component
{
    public News $news;

    public function mount(News $news): void
    {
        $this->news = $news->load('user:id,name');
    }

    public function render()
    {
        $relatedNews = News::with('user:id,name')
            ->where('id', '!=', $this->news->id)
            ->latest('created_at')
            ->take(4)
            ->get();

        $prevNews = News::where('id', '!=', $this->news->id)
            ->where('created_at', '<', $this->news->created_at)
            ->latest('created_at')
            ->first(['id', 'title', 'slug']);

        $nextNews = News::where('id', '!=', $this->news->id)
            ->where('created_at', '>', $this->news->created_at)
            ->oldest('created_at')
            ->first(['id', 'title', 'slug']);

        $metaDescription = Str::limit(strip_tags($this->news->content ?? ''), 160);

        return view('livewire.public.news.show', [
            'relatedNews' => $relatedNews,
            'prevNews' => $prevNews,
            'nextNews' => $nextNews,
            'metaDescription' => $metaDescription,
        ])->layout('layouts.public', [
            'title' => "{$this->news->title} | WPiTV — Televisi Digital Kampus",
        ]);
    }
}
