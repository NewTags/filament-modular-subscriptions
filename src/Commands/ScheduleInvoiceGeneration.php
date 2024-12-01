<?php

namespace HoceineEl\FilamentModularSubscriptions\Commands;

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
                'plan:id,fixed_invoice_day,invoice_interval,invoice_period,is_pay_as_you_go',
                'invoices:id,subscription_id,created_at',
                'subscribable:id,name',
            ])
            ->select([
                'id',
                'plan_id',
                'starts_at',
                'ends_at',
                'subscribable_id',
                'subscribable_type',
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
                                            'days' => $subscription->ends_at->diffInDays(now()),
                                            'expiry_date' => $subscription->ends_at->format('Y-m-d'),
                                            'plan' => $subscription->plan->trans_name
                                        ]);

                                        $subscription->subscribable->notifySuperAdmins('subscription_near_expiry', [
                                            'tenant' => $subscription->subscribable->name,
                                            'days' => $subscription->ends_at->diffInDays(now()),
                                            'expiry_date' => $subscription->ends_at->format('Y-m-d'),
                                            'plan' => $subscription->plan->trans_name
                                        ]);
                                    }

                                    if ($subscription->ends_at && 
                                        $subscription->ends_at->isPast() && 
                                        $subscription->ends_at->copy()->addDays($subscription->plan->grace_period)->isFuture()) {
                                        $daysLeft = now()->diffInDays($subscription->ends_at->copy()->addDays($subscription->plan->grace_period));
                                        $subscription->subscribable->notifySubscriptionChange('subscription_grace_period', [
                                            'days' => $daysLeft,
                                            'grace_end_date' => $subscription->ends_at->copy()->addDays($subscription->plan->grace_period)->format('Y-m-d')
                                        ]);

                                        $subscription->subscribable->notifySuperAdmins('subscription_grace_period', [
                                            'tenant' => $subscription->subscribable->name,
                                            'days' => $daysLeft,
                                            'grace_end_date' => $subscription->ends_at->copy()->addDays($subscription->plan->grace_period)->format('Y-m-d')
                                        ]);
                                    }
                                });
                            }
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
        $lastInvoice = $subscription->invoices->sortByDesc('created_at')->first();
        $today = now();

        // If no previous invoice exists, generate one
        if (! $lastInvoice) {
            return true;
        }

        // If plan has fixed invoice day
        if ($plan->fixed_invoice_day) {
            // Check if today is the fixed invoice day
            if ($today->day == $plan->fixed_invoice_day) {
                // Check if we already generated an invoice this month
                return ! $subscription->invoices
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->where('created_at', '<=', now()->endOfMonth())
                    ->count();
            }

            return false;
        }

        // For interval-based billing
        $nextInvoiceDate = $subscription->ends_at ?? $subscription->starts_at;

        return $today->copy()->subDays($plan->grace_period)->startOfDay()->gte($nextInvoiceDate);
    }

    protected function calculateNextInvoiceDate($subscription, $lastInvoice): Carbon
    {
        $plan = $subscription->plan;
        // Always use last invoice date as base if available
        $baseDate = $subscription->starts_at;

        if (! $baseDate) {
            throw new \InvalidArgumentException('Invalid base date for invoice calculation');
        }

        // Calculate next date based on invoice interval
        return match ($plan->invoice_interval->value) {
            'day' => $baseDate->copy()->addDays($plan->invoice_period),
            'week' => $baseDate->copy()->addWeeks($plan->invoice_period),
            'month' => $baseDate->copy()->addMonths($plan->invoice_period),
            'year' => $baseDate->copy()->addYears($plan->invoice_period),
            default => throw new \InvalidArgumentException("Invalid invoice interval: {$plan->invoice_interval->getLabel()}"),
        };
    }
}
