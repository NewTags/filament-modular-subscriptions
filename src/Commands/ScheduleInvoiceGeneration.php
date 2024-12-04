<?php

namespace HoceineEl\FilamentModularSubscriptions\Commands;

use HoceineEl\FilamentModularSubscriptions\Models\Invoice;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Enums\InvoiceStatus;
use HoceineEl\FilamentModularSubscriptions\Enums\Interval;
use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use HoceineEl\FilamentModularSubscriptions\Services\InvoiceService;
use HoceineEl\FilamentModularSubscriptions\Services\SubscriptionLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ScheduleInvoiceGeneration extends Command
{
    protected $signature = 'subscriptions:schedule-invoices';

    protected $description = 'Generate invoices for subscriptions based on their billing cycles';

    public function handle(InvoiceService $invoiceService, SubscriptionLogService $logService)
    {
        $this->info(__('filament-modular-subscriptions::fms.commands.schedule_invoices.starting'));

        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        $subscriptionModel::query()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->with([
                'plan',
                'invoices',
                'subscribable',
            ])
            ->chunk(100, function ($activeSubscriptions) use ($invoiceService, $logService) {
                foreach ($activeSubscriptions as $subscription) {
                    try {
                        if ($this->shouldGenerateInvoice($subscription)) {
                            $this->info(__('filament-modular-subscriptions::fms.commands.schedule_invoices.generating', ['id' => $subscription->id]));
                            $invoice = $invoiceService->generate($subscription);
                            if ($invoice) {
                                $this->info(__('filament-modular-subscriptions::fms.commands.schedule_invoices.success', ['id' => $invoice->id]));

                                $oldStatus = $subscription->status;
                                $subscription->update([
                                    'status' => SubscriptionStatus::PENDING_PAYMENT,
                                ]);

                                $subscription->subscribable->notifySubscriptionChange('invoice_generated', [
                                    'invoice_id' => $invoice->id,
                                    'amount' => $invoice->total,
                                    'due_date' => $invoice->due_date->format('Y-m-d'),
                                    'currency' => $subscription->plan->currency
                                ]);

                                $subscription->subscribable->notifySuperAdmins('invoice_generated', [
                                    'invoice_id' => $invoice->id,
                                    'amount' => $invoice->total,
                                    'tenant' => $subscription->subscribable->name,
                                    'currency' => $subscription->plan->currency
                                ]);

                                defer(function () use ($subscription, $logService, $oldStatus, $invoice) {
                                    $logService->log(
                                        $subscription,
                                        'invoice_generated',
                                        __('filament-modular-subscriptions::fms.logs.invoice_generated', [
                                            'invoice_id' => $invoice->id,
                                            'amount' => $invoice->total,
                                        ]),
                                        $oldStatus->value,
                                        SubscriptionStatus::PENDING_PAYMENT->value,
                                        [
                                            'invoice_id' => $invoice->id,
                                            'total' => $invoice->total,
                                            'items_count' => $invoice->items->count(),
                                        ]
                                    );

                                    if ($invoice->due_date->isPast()) {
                                        $daysOverdue = now()->diffInDays($invoice->due_date);
                                        $subscription->subscribable->notifySubscriptionChange('invoice_overdue', [
                                            'invoice_id' => $invoice->id,
                                            'days' => $daysOverdue,
                                            'amount' => $invoice->total,
                                            'currency' => $subscription->plan->currency
                                        ]);

                                        $subscription->subscribable->notifySuperAdmins('invoice_overdue', [
                                            'invoice_id' => $invoice->id,
                                            'days' => $daysOverdue,
                                            'tenant' => $subscription->subscribable->name,
                                            'amount' => $invoice->total,
                                            'currency' => $subscription->plan->currency
                                        ]);
                                    }

                                    if ($subscription->ends_at && $subscription->ends_at->diffInDays(now()) <= 7) {
                                        $subscription->subscribable->notifySubscriptionChange('subscription_near_expiry', [
                                            'days' => number_format($subscription->ends_at->diffInDays(now())),
                                            'expiry_date' => $subscription->ends_at->format('Y-m-d'),
                                            'plan' => $subscription->plan->trans_name
                                        ]);

                                        $subscription->subscribable->notifySuperAdmins('subscription_near_expiry', [
                                            'tenant' => $subscription->subscribable->name,
                                            'days' => number_format($subscription->ends_at->diffInDays(now())),
                                            'expiry_date' => $subscription->ends_at->format('Y-m-d'),
                                            'plan' => $subscription->plan->trans_name
                                        ]);
                                    }

                                    if (
                                        $subscription->ends_at &&
                                        $subscription->ends_at->isPast() &&
                                        $subscription->ends_at->copy()->addDays($subscription->plan->grace_period)->isFuture()
                                    ) {
                                        $daysLeft = now()->diffInDays($subscription->ends_at->copy()->addDays($subscription->plan->grace_period));
                                        $subscription->subscribable->notifySubscriptionChange('subscription_grace_period', [
                                            'days' => number_format($daysLeft),
                                            'grace_end_date' => $subscription->ends_at->copy()->addDays($subscription->plan->grace_period)->format('Y-m-d')
                                        ]);

                                        $subscription->subscribable->notifySuperAdmins('subscription_grace_period', [
                                            'tenant' => $subscription->subscribable->name,
                                            'days' => number_format($daysLeft),
                                            'grace_end_date' => $subscription->ends_at->copy()->addDays($subscription->plan->grace_period)->format('Y-m-d')
                                        ]);
                                    }
                                });
                            }

                            $subscription->subscribable->invalidateSubscriptionCache();
                        }
                    } catch (\Exception $e) {
                        defer(function () use ($e, $subscription, $logService) {
                            $logService->log(
                                $subscription,
                                'invoice_generation_failed',
                                __('filament-modular-subscriptions::fms.logs.invoice_generation_failed', [
                                    'error' => $e->getMessage(),
                                ]),
                                null,
                                null,
                                ['error' => $e->getMessage()]
                            );

                            $subscription->subscribable->notifySubscriptionChange('invoice_generation_failed', [
                                'error' => $e->getMessage(),
                                'subscription_id' => $subscription->id
                            ]);

                            $subscription->subscribable->notifySuperAdmins('invoice_generation_failed', [
                                'tenant' => $subscription->subscribable->name,
                                'error' => $e->getMessage(),
                                'subscription_id' => $subscription->id
                            ]);
                        });

                        $this->error(__('filament-modular-subscriptions::fms.commands.schedule_invoices.error', [
                            'id' => $subscription->id,
                            'message' => $e->getMessage(),
                        ]));
                        Log::error("Invoice generation error for subscription {$subscription->id}", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            });

        $this->info(__('filament-modular-subscriptions::fms.commands.schedule_invoices.completed'));
    }

    protected function shouldGenerateInvoice($subscription): bool
    {
        $plan = $subscription->plan;
        $lastInvoice = $subscription->invoices()
            ->where(function ($query) {
                $query->where('status', InvoiceStatus::UNPAID)
                      ->orWhere('status', InvoiceStatus::PARTIALLY_PAID);
            })
            ->latest()
            ->first();
        $today = now();

        if (! $lastInvoice) {
            return true;
        }

        if ($plan->fixed_invoice_day) {
            if ($today->day == $plan->fixed_invoice_day) {
                return ! $subscription->invoices
                    ->whereBetween('created_at', [
                        now()->startOfMonth(),
                        now()->endOfMonth()
                    ])
                    ->count();
            }
            return false;
        }

        $nextInvoiceDate = $subscription->ends_at ?? $subscription->starts_at;
        return $today->startOfDay()->gte(
            $nextInvoiceDate->copy()->addDays($plan->grace_period)
        );
    }

    protected function calculateNextInvoiceDate($subscription, $lastInvoice): Carbon
    {
        $plan = $subscription->plan;
        $baseDate = $lastInvoice?->created_at ?? $subscription->starts_at;

        $nextDate = match ($plan->invoice_interval) {
            Interval::DAY => $baseDate->copy()->addDays($plan->invoice_period),
            Interval::WEEK => $baseDate->copy()->addWeeks($plan->invoice_period),
            Interval::MONTH => $baseDate->copy()->addMonths($plan->invoice_period),
            Interval::YEAR => $baseDate->copy()->addYears($plan->invoice_period),
            default => throw new \InvalidArgumentException(
                "Invalid invoice interval: {$plan->invoice_interval->getLabel()}"
            ),
        };

        return $nextDate;
    }

    protected function isInvoiceOverdue(Invoice $invoice): bool
    {
        $totalPaid = $invoice->payments()
            ->where('status', PaymentStatus::PAID)
            ->sum('amount');

        return $totalPaid < $invoice->amount && 
            now()->greaterThan($invoice->due_date);
    }
}
