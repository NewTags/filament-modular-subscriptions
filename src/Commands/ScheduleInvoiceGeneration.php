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

        // Get active subscriptions that need invoicing with eager loading using chunking
        $subscriptionModel::query()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where(function ($query) {
                $query->whereDoesntHave('invoices')
                    ->orWhereHas('invoices', function ($q) {
                        $q->where('created_at', '<=', now()->subDay());
                    });
            })
            ->with([
                'plan:id,fixed_invoice_day,invoice_interval,invoice_period',
                'invoices:id,subscription_id,created_at'
            ])
            ->select([
                'id',
                'plan_id',
                'starts_at',
                'ends_at'
            ])
            ->chunk(100, function ($activeSubscriptions) use ($invoiceService, $logService) {
                foreach ($activeSubscriptions as $subscription) {
                    try {
                        if ($this->shouldGenerateInvoice($subscription)) {
                            $this->info(__('filament-modular-subscriptions::fms.commands.schedule_invoices.generating', ['id' => $subscription->id]));

                            $invoice = $invoiceService->generateInvoice($subscription);

                            if ($invoice) {
                                $this->info(__('filament-modular-subscriptions::fms.commands.schedule_invoices.success', ['id' => $invoice->id]));

                                $oldStatus = $subscription->status;
                                $subscription->update([
                                    'status' => SubscriptionStatus::PENDING_PAYMENT
                                ]);
                                defer(function () use ($subscription, $logService, $oldStatus, $invoice) {
                                    $logService->log(
                                        $subscription,
                                        'invoice_generated',
                                        __('filament-modular-subscriptions::fms.logs.invoice_generated', [
                                            'invoice_id' => $invoice->id,
                                            'amount' => $invoice->total
                                        ]),
                                        $oldStatus->value,
                                        SubscriptionStatus::PENDING_PAYMENT->value,
                                        [
                                            'invoice_id' => $invoice->id,
                                            'total' => $invoice->total,
                                            'items_count' => $invoice->items->count(),
                                        ]
                                    );
                                });
                            }
                        }
                    } catch (\Exception $e) {
                        defer(function () use ($e, $subscription, $logService) {
                            $logService->log(
                                $subscription,
                                'invoice_generation_failed',
                                __('filament-modular-subscriptions::fms.logs.invoice_generation_failed', [
                                    'error' => $e->getMessage()
                                ]),
                                null,
                                null,
                                ['error' => $e->getMessage()]
                            );
                        });

                        $this->error(__('filament-modular-subscriptions::fms.commands.schedule_invoices.error', [
                            'id' => $subscription->id,
                            'message' => $e->getMessage()
                        ]));
                        Log::error("Invoice generation error for subscription {$subscription->id}", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
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
        if (!$lastInvoice) {
            return true;
        }

        // If subscription has an end date and we've passed it, don't generate invoice
        if ($subscription->ends_at && $today->isAfter($subscription->ends_at)) {
            return false;
        }

        // If plan has fixed invoice day
        if ($plan->fixed_invoice_day) {
            // Check if today is the fixed invoice day
            if ($today->day == $plan->fixed_invoice_day) {
                // Check if we already generated an invoice today
                return !$subscription->invoices
                    ->where('created_at', '>', now()->startOfDay())
                    ->count();
            }
            return false;
        }

        // Calculate next invoice date based on subscription end date if available
        $nextInvoiceDate = $this->calculateNextInvoiceDate($subscription, $lastInvoice);
        return $today->startOfDay()->gte($nextInvoiceDate);
    }

    protected function calculateNextInvoiceDate($subscription, $lastInvoice): Carbon
    {
        $plan = $subscription->plan;

        // Use last invoice date, subscription end date, or subscription start date
        $baseDate = $lastInvoice?->created_at ?? $subscription->ends_at ?? $subscription->starts_at;

        if (!$baseDate) {
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
