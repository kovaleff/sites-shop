<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Conversation;

use App\Orchid\Filters\ConversationParticipantFilter;
use App\Orchid\Filters\ConversationSiteFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class ConversationFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            ConversationSiteFilter::class,
            ConversationParticipantFilter::class,
        ];
    }
}
