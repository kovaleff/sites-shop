<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Site;

use App\Enums\ListingStatus;
use App\Models\Site;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class SiteStatusLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        /** @var Site|null $site */
        $site = request()->route('site');

        if ($site === null) {
            return [];
        }

        $transitions = $site->status->allowedTransitions();

        if ($transitions === []) {
            return [
                Label::make('site.status_label')
                    ->title('Статус')
                    ->value(ListingStatus::Sold->label().' — конечный статус'),
            ];
        }

        return [
            Select::make('site.status')
                ->title('Статус')
                ->options(
                    collect($transitions)
                        ->mapWithKeys(fn (ListingStatus $status) => [$status->value => $status->label()])
                        ->all()
                )
                ->help('Доступны только допустимые переходы из текущего статуса «'.$site->status->label().'».'),
        ];
    }
}
