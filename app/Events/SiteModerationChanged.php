<?php

namespace App\Events;

use App\Enums\ModerationStatus;
use App\Models\Site;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Модератор изменил решение по объявлению.
 */
class SiteModerationChanged
{
    use Dispatchable;

    public function __construct(
        public Site $site,
        public ModerationStatus $status,
        public ?string $note = null,
    ) {}
}
