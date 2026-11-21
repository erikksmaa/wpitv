<?php

namespace Tests\Browser;

use App\Models\MonthlyActivityReport;
use App\Models\User;
use App\Services\MonthlyActivityLifecycle;

class MonthlyActivityBrowserTest extends EquipmentBrowserTest
{
    protected function setUpEquipmentFixtures(): void
    {
        parent::setUpEquipmentFixtures();
        $service = app(MonthlyActivityLifecycle::class);
        $service->createDraft($this->actor(), 'production', '2026-11');
        $news = $this->actor(User::ROLE_NEWS);
        $report = $service->createDraft($news, 'news', '2026-11');
        $service->updateDraft($news, $report->id, $report->version, 'Data sintetis QA browser.');
        $submitted = $service->submit($news, $report->id, $report->version + 1);
        if (getenv('WPITV_MONTHLY_DOCUMENT_QA') === 'true') {
            $service->approve($this->actor(User::ROLE_SUPER_ADMIN), $report->id, $submitted->version);
        }
    }

    protected function cleanUpEquipmentFixtures(): void
    {
        $ids = MonthlyActivityReport::whereIn('created_by', $this->owned['users'])->pluck('id');
        $this->primary->transaction(function () use ($ids) {
            foreach (['events', 'versions', 'attachments', 'items'] as $table) {
                $this->primary->table('monthly_activity_'.$table)->whereIn('report_id', $ids)->delete();
            }
            $this->primary->table('monthly_activity_reports')->whereIn('id', $ids)->delete();
        });
        parent::cleanUpEquipmentFixtures();
    }
}
