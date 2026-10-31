<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Site;

use App\Enums\ReportStatus;
use App\Models\SiteReport;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class SiteReportsTable extends Table
{
    /**
     * @var string
     */
    public $target = 'reports';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('reason', 'Причина')
                ->render(fn (SiteReport $report) => $report->reason->label()),

            TD::make('reporter', 'Автор')
                ->render(fn (SiteReport $report) => $report->reporter?->name ?? '—'),

            TD::make('comment', 'Комментарий')
                ->render(fn (SiteReport $report) => $report->comment ?? '—'),

            TD::make('status', 'Статус')
                ->render(fn (SiteReport $report) => $report->status->label()),

            TD::make('created_at', 'Получена')
                ->render(fn (SiteReport $report) => $report->created_at?->format('d.m.Y H:i')),

            TD::make('actions', 'Решение')
                ->alignRight()
                ->render(fn (SiteReport $report) => $report->status === ReportStatus::New
                    ? Button::make('Принять')
                        ->icon('bs.check-circle')
                        ->method('resolveReport')
                        ->parameters(['report' => $report->id])
                        ->class('btn btn-link text-success')
                    : ''),

            TD::make('dismiss', '')
                ->alignRight()
                ->render(fn (SiteReport $report) => $report->status === ReportStatus::New
                    ? Button::make('Отклонить')
                        ->icon('bs.x-circle')
                        ->method('dismissReport')
                        ->parameters(['report' => $report->id])
                        ->class('btn btn-link text-danger')
                    : ''),
        ];
    }
}
