<?php

namespace Tests\Browser;

use Illuminate\Support\Facades\DB;

/** Loopback browser fixture only, with inherited identity guards and exact cleanup. */
class PublicLiveStreamingBrowserTest extends LiveStreamingBrowserTest
{
    protected function setUpEquipmentFixtures(): void
    {
        parent::setUpEquipmentFixtures();
        $ids = $this->owned['live_agendas'];
        DB::table('wpitv_agendas')->whereIn('id', $ids)->update(['visibility' => 'public']);
        DB::table('live_streams')->whereIn('agenda_id', $ids)->update(['youtube_id' => 'AbC_dE-1234']);
    }
}
