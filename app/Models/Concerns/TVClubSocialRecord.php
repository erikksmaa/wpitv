<?php

namespace App\Models\Concerns;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class TVClubSocialRecord extends Model
{
    use HasUtcTimestamps;

    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['created_at' => UtcDateTime::class, 'updated_at' => UtcDateTime::class];
    }

    public function delete()
    {
        throw new LogicException('TVClub social records are retained.');
    }

    public function forceDelete()
    {
        return $this->delete();
    }
}
