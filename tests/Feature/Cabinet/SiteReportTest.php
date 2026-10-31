<?php

namespace Tests\Feature\Cabinet;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_report_a_site(): void
    {
        $site = Site::factory()->paid()->create();

        $this->postJson("/api/v1/sites/{$site->id}/reports", [
            'reason' => ReportReason::Spam->value,
        ])->assertUnauthorized();
    }

    public function test_user_can_report_a_published_site(): void
    {
        $reporter = User::factory()->create();
        $site = Site::factory()->paid()->create();

        $this->actingAs($reporter)
            ->postJson("/api/v1/sites/{$site->id}/reports", [
                'reason' => ReportReason::Fraud->value,
                'comment' => 'Продавец просит предоплату вне площадки.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', ReportStatus::New->value);

        $this->assertDatabaseHas('site_reports', [
            'site_id' => $site->id,
            'user_id' => $reporter->id,
            'reason' => ReportReason::Fraud->value,
            'status' => ReportStatus::New->value,
        ]);
    }

    public function test_user_cannot_report_own_site(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->paid()->create();

        $this->actingAs($owner)
            ->postJson("/api/v1/sites/{$site->id}/reports", [
                'reason' => ReportReason::Spam->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('site');
    }

    public function test_site_outside_the_catalog_cannot_be_reported(): void
    {
        $reporter = User::factory()->create();
        $site = Site::factory()->paid()->pendingModeration()->create();

        $this->actingAs($reporter)
            ->postJson("/api/v1/sites/{$site->id}/reports", [
                'reason' => ReportReason::Spam->value,
            ])
            ->assertNotFound();
    }

    public function test_repeated_report_is_rejected(): void
    {
        $reporter = User::factory()->create();
        $site = Site::factory()->paid()->create();

        $this->actingAs($reporter)
            ->postJson("/api/v1/sites/{$site->id}/reports", [
                'reason' => ReportReason::Spam->value,
            ])
            ->assertCreated();

        $this->actingAs($reporter)
            ->postJson("/api/v1/sites/{$site->id}/reports", [
                'reason' => ReportReason::Other->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('site');

        $this->assertDatabaseCount('site_reports', 1);
    }

    public function test_reason_must_be_known(): void
    {
        $reporter = User::factory()->create();
        $site = Site::factory()->paid()->create();

        $this->actingAs($reporter)
            ->postJson("/api/v1/sites/{$site->id}/reports", [
                'reason' => 'unknown-reason',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }
}
