<?php

namespace Tests\Browser;

use App\Models\Program;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LiveHighlightsBrowserTest extends PublicLiveStreamingBrowserTest
{
    private ?int $programId = null;

    private array $counterIds = [];

    private bool $counterBaselineCaptured = false;

    protected function setUpEquipmentFixtures(): void
    {
        parent::setUpEquipmentFixtures();
        $this->counterIds = DB::table('tr_usercounter')->pluck('id')->all();
        $this->counterBaselineCaptured = true;
        if (DB::table('tr_usercounter')->where('ip', '127.0.0.1')->where('tanggal', now()->toDateString())->exists()) {
            throw new RuntimeException('LIVE_QA_EXISTING_VISITOR');
        }
        $program = Program::create(['name' => $this->prefix.' Live Program', 'slug' => strtolower($this->prefix).'-live', 'status' => true]);
        $this->programId = $program->id;
        DB::table('wpitv_agendas')->whereIn('id', $this->owned['live_agendas'])->update(['program_id' => $program->id]);
    }

    protected function cleanUpEquipmentFixtures(): void
    {
        // Homepage's existing visitor tracker writes a loopback QA fixture.
        // Never update/delete any counter that existed before this run.
        $created = $this->counterBaselineCaptured
            ? DB::table('tr_usercounter')->whereNotIn('id', $this->counterIds)->get()
            : collect();
        foreach ($created as $counter) {
            if ($counter->ip !== '127.0.0.1' || $counter->tanggal !== now()->toDateString()) {
                throw new RuntimeException('LIVE_QA_COUNTER_SCOPE');
            }
        }
        DB::table('tr_usercounter')->whereIn('id', $created->pluck('id'))->delete();
        parent::cleanUpEquipmentFixtures();
        if ($this->programId !== null) {
            Program::where('id', $this->programId)->where('name', $this->prefix.' Live Program')->delete();
        }
    }
}
