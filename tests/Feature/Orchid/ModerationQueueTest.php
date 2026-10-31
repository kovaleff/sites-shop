<?php

namespace Tests\Feature\Orchid;

use App\Enums\ModerationStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Site;
use App\Models\SiteReport;
use App\Models\User;

class ModerationQueueTest extends OrchidTestCase
{
    public function test_queue_lists_only_pending_sites(): void
    {
        $this->actingAs($this->admin());

        Site::factory()->paid()->pendingModeration()->create(['domain' => 'pending-site.test']);
        Site::factory()->paid()->create(['domain' => 'approved-site.test']);

        $this->get(route('platform.marketplace.moderation'))
            ->assertOk()
            ->assertSee('pending-site.test')
            ->assertDontSee('approved-site.test');
    }

    public function test_admin_can_approve_a_pending_site(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->pendingModeration()->create();

        $this->post(route('platform.marketplace.moderation', ['method' => 'approve']), [
            'site' => $site->id,
        ])->assertRedirect();

        $this->assertSame(ModerationStatus::Approved, $site->refresh()->moderation_status);
    }

    public function test_admin_can_reject_a_pending_site(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->pendingModeration()->create();

        $this->post(route('platform.marketplace.moderation', ['method' => 'reject']), [
            'site' => $site->id,
        ])->assertRedirect();

        $site->refresh();

        $this->assertSame(ModerationStatus::Rejected, $site->moderation_status);
        $this->assertNotNull($site->moderation_note);
    }

    public function test_accepting_a_report_unpublishes_the_site(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->paid()->create();
        $report = SiteReport::factory()->for($site)->create([
            'reason' => ReportReason::Fraud,
        ]);

        $this->post(route('platform.marketplace.sites.edit', [$site, 'resolveReport']), [
            'report' => $report->id,
        ])->assertRedirect();

        $this->assertSame(ReportStatus::Resolved, $report->refresh()->status);
        $this->assertSame(ModerationStatus::Rejected, $site->refresh()->moderation_status);
    }

    public function test_dismissing_a_report_keeps_the_site_published(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->paid()->create();
        $report = SiteReport::factory()->for($site)->create();

        $this->post(route('platform.marketplace.sites.edit', [$site, 'dismissReport']), [
            'report' => $report->id,
        ])->assertRedirect();

        $this->assertSame(ReportStatus::Dismissed, $report->refresh()->status);
        $this->assertSame(ModerationStatus::Approved, $site->refresh()->moderation_status);
    }

    public function test_moderation_queue_requires_permission(): void
    {
        $this->actingAs(User::factory()->create([
            'permissions' => ['platform.index' => true],
        ]));

        $this->get(route('platform.marketplace.moderation'))->assertForbidden();
    }
}
