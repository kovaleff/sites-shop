<?php

declare(strict_types=1);

namespace App\Orchid\Screens\SubscriptionPayment;

use App\Enums\PaymentReviewDecision;
use App\Enums\PaymentStatus;
use App\Models\SubscriptionPayment;
use App\Orchid\Layouts\SubscriptionPayment\PaymentReviewLayout;
use App\Orchid\Layouts\SubscriptionPayment\SubscriptionPaymentShowLayout;
use App\Services\Payments\PaymentReviewService;
use DomainException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Layout;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Toast;

class SubscriptionPaymentShowScreen extends Screen
{
    private ?SubscriptionPayment $payment = null;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(SubscriptionPayment $subscriptionPayment): iterable
    {
        $this->payment = $subscriptionPayment;

        $subscriptionPayment->load(['site', 'payer', 'reviews.admin']);

        $reviewReason = $subscriptionPayment->review_reason;
        $failureReason = $subscriptionPayment->failure_reason;

        return [
            'payment' => [
                'invoice_number' => '#'.$subscriptionPayment->id,
                'site_domain' => $subscriptionPayment->site_domain ?? '—',
                'current_site_domain' => $subscriptionPayment->site?->domain ?? '—',
                'site' => $subscriptionPayment->site?->title ?? '—',
                'payer' => $subscriptionPayment->payer?->name ?? '—',
                'receipt_email' => $subscriptionPayment->receipt_email ?? '—',
                'base_price' => $subscriptionPayment->base_price === null
                    ? '—'
                    : number_format((float) $subscriptionPayment->base_price, 2, '.', ' '),
                'discount' => $subscriptionPayment->discount_percent > 0
                    ? $subscriptionPayment->discount_percent.'% (−'
                        .number_format((float) $subscriptionPayment->discount_amount, 2, '.', ' ').')'
                    : '—',
                'amount' => number_format((float) $subscriptionPayment->amount, 2, '.', ' '),
                'currency' => $subscriptionPayment->currency,
                'period_months' => $subscriptionPayment->period_months,
                'target_status' => $subscriptionPayment->target_status ?? '—',
                'status' => $subscriptionPayment->status->label(),
                'payment_method' => $subscriptionPayment->payment_method?->label() ?? '—',
                'is_test' => $subscriptionPayment->is_test ? 'Да' : 'Нет',
                'created_at' => $subscriptionPayment->created_at?->format('d.m.Y H:i') ?? '—',
                'paid_at' => $subscriptionPayment->paid_at?->format('d.m.Y H:i') ?? '—',
                'qr_expires_at' => $subscriptionPayment->qr_expires_at?->format('d.m.Y H:i') ?? '—',
                'external_id' => $subscriptionPayment->external_id ?? '—',
                'provider' => $subscriptionPayment->provider?->value ?? '—',
                'paid_until' => $subscriptionPayment->site?->paid_until?->format('d.m.Y') ?? '—',
                'failure_reason' => $failureReason ?? '—',
                'review_reason' => $reviewReason ?? '—',
                'provider_payload' => json_encode(
                    $subscriptionPayment->provider_payload ?? [],
                    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
                ) ?: '—',
            ],
            'review' => [
                'decision' => PaymentReviewDecision::Accepted->value,
                'comment' => '',
            ],
            'reviews' => $subscriptionPayment->reviews()
                ->with('admin')
                ->latest()
                ->get()
                ->map(fn ($review): array => [
                    'id' => $review->id,
                    'admin' => $review->admin?->name ?? '—',
                    'decision' => $review->decision->label(),
                    'comment' => $review->comment,
                    'created_at' => $review->created_at?->format('d.m.Y H:i') ?? '—',
                ]),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Платёж';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Данные платежа за подписку на объявление.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.marketplace.payments',
        ];
    }

    /**
     * @return array<int, Button>
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Сохранить решение')
                ->icon('bs.check2-circle')
                ->method('decide')
                ->canSee($this->payment?->status === PaymentStatus::RequiresReview),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return string[]|Layout[]
     */
    public function layout(): iterable
    {
        $layouts = [
            SubscriptionPaymentShowLayout::class,
        ];

        if ($this->payment?->status === PaymentStatus::RequiresReview) {
            $layouts[] = PaymentReviewLayout::class;
        }

        $layouts[] = \Orchid\Support\Facades\Layout::table('reviews', [
            TD::make('created_at', 'Дата')->render(fn (array $row): string => $row['created_at']),
            TD::make('admin', 'Администратор')->render(fn (array $row): string => $row['admin']),
            TD::make('decision', 'Решение')->render(fn (array $row): string => $row['decision']),
            TD::make('comment', 'Комментарий')->render(fn (array $row): string => $row['comment']),
        ])->title('История решений');

        return $layouts;
    }

    public function decide(
        Request $request,
        SubscriptionPayment $subscriptionPayment,
        PaymentReviewService $reviewService,
    ): void {
        $data = $request->validate([
            'review.decision' => ['required', 'in:accepted,rejected'],
            'review.comment' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $decision = PaymentReviewDecision::from($data['review']['decision']);
        $comment = (string) $data['review']['comment'];

        try {
            if ($decision === PaymentReviewDecision::Accepted) {
                $reviewService->accept($subscriptionPayment, $request->user(), $comment);
                Toast::info('Платёж принят, подписка продлена.');
            } else {
                $reviewService->reject($subscriptionPayment, $request->user(), $comment);
                Toast::info('Платёж отклонён.');
            }
        } catch (DomainException|InvalidArgumentException $exception) {
            Toast::error($exception->getMessage());
        }
    }
}
