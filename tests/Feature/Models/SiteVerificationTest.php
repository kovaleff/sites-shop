<?php

namespace Tests\Feature\Models;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_filename_is_generated_on_creation(): void
    {
        $site = Site::factory()->create();

        $this->assertMatchesRegularExpression('/^[a-z0-9]{32}\.txt$/', $site->verification_filename);
        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'verification_filename' => $site->verification_filename,
        ]);
    }

    public function test_verification_filename_is_unique_per_site(): void
    {
        $first = Site::factory()->create();
        $second = Site::factory()->create();

        $this->assertNotSame($first->verification_filename, $second->verification_filename);
    }

    public function test_verification_filename_does_not_change_on_update(): void
    {
        $site = Site::factory()->create();
        $filename = $site->verification_filename;

        $site->update(['title' => 'Новое название сайта']);

        $this->assertSame($filename, $site->fresh()->verification_filename);
    }

    public function test_verified_defaults_to_false(): void
    {
        $site = Site::factory()->create();

        $this->assertFalse($site->verified);
    }

    public function test_verified_and_filename_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();

        $site = $user->sites()->create([
            'title' => 'Сайт для проверки',
            'domain' => 'mass-assignment.example',
            'description' => str_repeat('Описание сайта для проверки массового заполнения. ', 2),
            'verified' => true,
            'verification_filename' => 'hacked.txt',
        ]);

        $this->assertFalse($site->verified);
        $this->assertNotSame('hacked.txt', $site->verification_filename);

        $site->fill(['verified' => true, 'verification_filename' => 'hacked.txt']);

        $this->assertFalse($site->verified);
        $this->assertNotSame('hacked.txt', $site->verification_filename);
    }

    public function test_mark_verified_sets_and_removes_the_flag_idempotently(): void
    {
        $site = Site::factory()->create();

        $site->markVerified();

        $this->assertTrue($site->verified);
        $this->assertTrue($site->fresh()->verified);

        $site->markVerified();

        $this->assertTrue($site->fresh()->verified);

        $site->markVerified(false);

        $this->assertFalse($site->verified);
        $this->assertFalse($site->fresh()->verified);
    }

    public function test_verified_does_not_affect_public_visibility(): void
    {
        $site = Site::factory()->paid()->create();

        $this->assertTrue($site->isPubliclyVisible());
        $this->assertSame(1, $this->publiclyVisibleCount());

        $site->markVerified();

        $this->assertTrue($site->isPubliclyVisible());
        $this->assertSame(1, $this->publiclyVisibleCount());

        $unpaidSite = Site::factory()->unpaid()->create();
        $unpaidSite->markVerified();

        $this->assertFalse($unpaidSite->isPubliclyVisible());
        $this->assertSame(1, $this->publiclyVisibleCount());
    }

    private function publiclyVisibleCount(): int
    {
        return Site::query()->get()->filter->isPubliclyVisible()->count();
    }
}
