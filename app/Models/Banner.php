<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    use HasFactory;

    protected $table = 'ms_banner';

    protected $fillable = [
        'title',
        'content',
        'image',
    ];

    /**
     * Get the full URL for the banner image with fallback.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (empty($this->image)) {
                    return asset('images/placeholders/program-placeholder.svg');
                }

                if (Storage::disk('public')->exists('banners/'.$this->image)) {
                    return asset('storage/banners/'.$this->image);
                }

                return asset('images/placeholders/program-placeholder.svg');
            }
        );
    }
}
