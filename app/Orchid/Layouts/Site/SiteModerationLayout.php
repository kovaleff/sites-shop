<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Site;

use App\Enums\ModerationStatus;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class SiteModerationLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Select::make('site.moderation_status')
                ->title('Статус модерации')
                ->options(
                    collect(ModerationStatus::cases())
                        ->mapWithKeys(fn (ModerationStatus $status) => [$status->value => $status->label()])
                        ->all()
                )
                ->help('Объявление автоматически одобряется после подтверждения владения доменом.'),

            Input::make('site.moderation_note')
                ->title('Комментарий модератора')
                ->max(255)
                ->help('Причина отклонения или заметка для команды.'),

            Label::make('site.verification_checked_at')
                ->title('Последняя проверка домена'),

            Label::make('site.verification_error')
                ->title('Результат проверки'),
        ];
    }
}
