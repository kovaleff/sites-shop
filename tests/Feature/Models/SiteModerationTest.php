<?php

namespace Tests\Feature\Models;

use App\Enums\ModerationStatus;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_site_is_pending_and_hidden_from_catalog(): void
    {
        $site = Site::factory()->paid()->pendingModeration()->create();

        $this->assertSame(ModerationStatus::Pending, $site->moderation_status);
        $this->assertFalse($site->isPubliclyVisible());
        $this->assertSame(0, Site::query()->publiclyVisible()->count());
    }

    public function test_approved_paid_site_is_publicly_visible(): void
    {
        $site = Site::factory()->paid()->create();

        $this->assertTrue($site->isPubliclyVisible());
        $this->assertSame(1, Site::query()->publiclyVisible()->count());
    }

    public function test_rejected_site_is_not_publicly_visible(): void
    {
        Site::factory()->paid()->rejectedModeration()->create();

        $this->assertSame(0, Site::query()->publiclyVisible()->count());
    }

    public function test_successful_domain_verification_approves_pending_site(): void
    {
        $site = Site::factory()->pendingModeration()->create();

        $site->markDomainVerified();

        $site->refresh();

        $this->assertTrue($site->verified);
        $this->assertSame(ModerationStatus::Approved, $site->moderation_status);
        $this->assertNotNull($site->verification_checked_at);
        $this->assertNull($site->verification_error);
    }

    public function test_failed_verification_keeps_site_pending_and_records_reason(): void
    {
        $site = Site::factory()->pendingModeration()->create();

        $site->markDomainVerificationFailed('Файл подтверждения не найден на сайте.');

        $site->refresh();

        $this->assertFalse($site->verified);
        $this->assertSame(ModerationStatus::Pending, $site->moderation_status);
        $this->assertSame('Файл подтверждения не найден на сайте.', $site->verification_error);
    }

    public function test_manual_moderation_records_note_and_timestamp(): void
    {
        $site = Site::factory()->create();

        $site->setModeration(ModerationStatus::Rejected, 'Реклама сторонних сервисов.');

        $site->refresh();

        $this->assertSame(ModerationStatus::Rejected, $site->moderation_status);
        $this->assertSame('Реклама сторонних сервисов.', $site->moderation_note);
        $this->assertNotNull($site->moderated_at);
    }

    public function test_domain_change_resets_verification_and_moderation(): void
    {
        $site = Site::factory()->paid()->verified()->create();

        $site->resetDomainVerification();

        $site->refresh();

        $this->assertFalse($site->verified);
        $this->assertSame(ModerationStatus::Pending, $site->moderation_status);
        $this->assertNull($site->verification_checked_at);
    }
}
