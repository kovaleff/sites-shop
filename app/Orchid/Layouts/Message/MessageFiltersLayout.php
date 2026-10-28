<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Message;

use App\Orchid\Filters\MessageConversationFilter;
use App\Orchid\Filters\MessageReadFilter;
use App\Orchid\Filters\MessageSenderFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class MessageFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            MessageConversationFilter::class,
            MessageSenderFilter::class,
            MessageReadFilter::class,
        ];
    }
}
