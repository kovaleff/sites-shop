<?php

namespace App\Events;

use App\Enums\ReportStatus;
use App\Models\SiteReport;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Жалоба на объявление рассмотрена (принята или отклонена).
 */
class SiteReportResolved
{
    use Dispatchable;

    public function __construct(
        public SiteReport $report,
        public ReportStatus $status,
    ) {}
}
