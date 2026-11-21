<?php

namespace Tests\Browser;

use App\Models\Video;
use Illuminate\Support\Facades\DB;

/** User-supplied public YouTube ID, isolated test records only. */
class LiveFinalBrowserTest extends LiveHighlightsBrowserTest
{
    private ?int $videoId = null;

    protected function setUpEquipmentFixtures(): void
    {
        parent::setUpEquipmentFixtures();
        DB::table('live_streams')->whereIn('agenda_id', $this->owned['live_agendas'])->update(['youtube_id' => 'BmSjbLwq-VE']);
        $this->videoId = Video::create(['title' => $this->prefix.' archive', 'youtube_id' => 'BmSjbLwq-VE', 'air_date' => today(), 'user_id' => $this->actor()->id])->id;
    }

    protected function cleanUpEquipmentFixtures(): void
    {
        if ($this->videoId !== null) {
            DB::table('ms_video')->where('id', $this->videoId)->where('title', $this->prefix.' archive')->where('user_id', $this->actor()->id)->delete();
        }
        parent::cleanUpEquipmentFixtures();
    }
}
