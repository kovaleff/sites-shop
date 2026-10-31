<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Site;

use App\Enums\ModerationStatus;
use App\Jobs\VerifySiteDomain;
use App\Models\Site;
use App\Orchid\Layouts\Site\ModerationQueueLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Layout;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class ModerationQueueScreen extends Screen
{
    /**
     * Объявления, ожидающие решения модератора.
     *
     * @return array<string, mixed>
     */
    public function query(): iterable
    {
        return [
            'sites' => Site::query()
                ->where('moderation_status', ModerationStatus::Pending)
                ->with(['owner'])
                ->latest('created_at')
                ->paginate(),
        ];
    }

    public function name(): ?string
    {
        return 'Модерация объявлений';
    }

    public function description(): ?string
    {
        return 'Объявления на проверке: подтверждение владения доменом и содержание.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.marketplace.sites',
        ];
    }

    /**
     * @return Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Проверить домены')
                ->icon('bs.shield-check')
                ->method('verifyAll')
                ->confirm('Запустить проверку доменов для всех объявлений в очереди?'),
        ];
    }

    /**
     * @return string[]|Layout[]
     */
    public function layout(): iterable
    {
        return [
            ModerationQueueLayout::class,
        ];
    }

    public function approve(Request $request): void
    {
        $site = Site::query()->find((int) $request->input('site'));

        if ($site === null) {
            Toast::error('Объявление не найдено.');

            return;
        }

        $site->setModeration(ModerationStatus::Approved, null);

        Toast::info('Объявление одобрено.');
    }

    public function reject(Request $request): void
    {
        $site = Site::query()->find((int) $request->input('site'));

        if ($site === null) {
            Toast::error('Объявление не найдено.');

            return;
        }

        $site->setModeration(ModerationStatus::Rejected, 'Отклонено модератором.');

        Toast::warning('Объявление отклонено. Причину можно уточнить в карточке.');
    }

    public function verifyAll(): void
    {
        $sites = Site::query()
            ->where('moderation_status', ModerationStatus::Pending)
            ->where('verified', false)
            ->limit(50)
            ->pluck('id');

        $sites->each(fn (int $id) => VerifySiteDomain::dispatch($id));

        Toast::info("Проверка запущена для объявлений: {$sites->count()}.");
    }
}
