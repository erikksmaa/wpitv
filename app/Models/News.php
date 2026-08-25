<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class News extends Model
{
    use HasFactory;

    protected $table = 'ms_news';

    protected $fillable = [
        'user_id',
        'author_name',
        'title',
        'slug',
        'content',
        'image',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the display name of the author.
     */
    protected function authorDisplayName(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->user) {
                    return $this->user->name;
                }

                if (! empty($this->author_name)) {
                    return $this->author_name;
                }

                return 'Redaksi WPiTV';
            }
        );
    }

    /**
     * Get the full URL for the news cover image with fallback.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (empty($this->image)) {
                    return asset('images/placeholders/program-placeholder.svg');
                }

                if (Storage::disk('public')->exists('news/'.$this->image)) {
                    return asset('storage/news/'.$this->image);
                }

                return asset('images/placeholders/program-placeholder.svg');
            }
        );
    }
}
