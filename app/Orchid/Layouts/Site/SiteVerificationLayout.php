<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Site;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Layouts\Rows;

class SiteVerificationLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('site.verification_filename')
                ->title('Файл подтверждения')
                ->readonly()
                ->help('Генерируется один раз и не изменяется.'),

            Switcher::make('site.verified')
                ->title('Владение подтверждено')
                ->sendTrueOrFalse(),

            Input::make('site.paid_until')
                ->title('Оплачено до')
                ->readonly()
                ->help('Изменяется только успешным платежом.'),
        ];
    }
}
