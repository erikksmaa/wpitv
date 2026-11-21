<?php

namespace Tests\Support;

use App\Services\EquipmentPhotos;
use Illuminate\Http\UploadedFile;

final class EquipmentPhotoFixtures
{
    public static function evidence(array $input): array
    {
        $photos = [];
        if (EquipmentPhotos::enabled()) {
            foreach ($input['items'] as $item) {
                $photos[$item['item_id']] = app(EquipmentPhotos::class)->stage(UploadedFile::fake()->image('unit.png', 8, 8));
            }
        }

        return $photos;
    }
}
