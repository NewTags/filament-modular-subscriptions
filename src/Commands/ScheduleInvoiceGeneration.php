<?php

namespace HoceineEl\FilamentModularSubscriptions\Commands;

use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use HoceineEl\FilamentModularSubscriptions\Services\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ScheduleInvoiceGeneration extends Command
{
    protected $signature = 'subscriptions:schedule-invoices';
    protected $description = 'Generate invoices for subscriptions based on their billing cycles';

    public function handle(InvoiceService $invoiceService)
    {
        $this->info(__('filament-modular-subscriptions::fms.commands.schedule_invoices.starting'));

        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        // Get active subscriptions that need invoicing
        $activeSubscriptions = $subscriptionModel::query()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where(function ($query) {
                $query->whereDoesntHave('invoices')
                    ->orWhereHas('invoices', function ($q) {
                        $q->where('created_at', '<=', now()->subDay());
                    });
            })
            ->with(['plan', 'invoices'])
            ->get();

        foreach ($activeSubscriptions as $subscription) {
            try {
                if ($this->shouldGenerateInvoice($subscription)) {
                    $this->info(__('filament-modular-subscriptions::fms.commands.schedule_invoices.generating', ['id' => $subscription->id]));

                    $invoice = $invoiceService->generateInvoice($subscription);

                    if ($invoice) {
                        $this->info(__('filament-modular-subscriptions::fms.commands.schedule_invoices.success', ['id' => $invoice->id]));
                    }
                }
            } catch (\Exception $e) {
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

        $this->info(__('filament-modular-subscriptions::fms.commands.schedule_invoices.completed'));
    }

    protected function shouldGenerateInvoice($subscription): bool
    {
        $plan = $subscription->plan;
        $lastInvoice = $subscription->invoices()->latest()->first();
        $today = now();

        // If no previous invoice exists, generate one
        if (!$lastInvoice) {
            return true;
        }

        // If plan has fixed invoice day
        if ($plan->fixed_invoice_day) {
            // Check if today is the fixed invoice day
            if ($today->day == $plan->fixed_invoice_day) {
                // Ensure we haven't already generated an invoice this month
                return !$subscription->invoices()
                    ->where(function ($query) {
                        $query->whereDoesntHave('invoices')
                            ->orWhereHas('invoices', function ($q) {
                                $q->where('created_at', '<=', now()->subDay());
                            });
                    })
                    ->exists();
            }
            return false;
        }

        // If no fixed day, calculate based on subscription start date
        $nextInvoiceDate = $this->calculateNextInvoiceDate($subscription, $lastInvoice);
        return $today->startOfDay()->gte($nextInvoiceDate);
    }

    protected function calculateNextInvoiceDate($subscription, $lastInvoice): Carbon
    {
        $plan = $subscription->plan;

        // Use last invoice date or subscription start date
        $baseDate = $lastInvoice ? $lastInvoice->created_at : $subscription->starts_at;

        if (!$baseDate) {
            throw new \InvalidArgumentException('Invalid base date for invoice calculation');
        }

        // Calculate next date based on invoice interval
        return match ($plan->invoice_interval) {
            'day' => $baseDate->copy()->addDays($plan->invoice_period),
            'week' => $baseDate->copy()->addWeeks($plan->invoice_period),
            'month' => $baseDate->copy()->addMonths($plan->invoice_period),
            'year' => $baseDate->copy()->addYears($plan->invoice_period),
            default => throw new \InvalidArgumentException("Invalid invoice interval: {$plan->invoice_interval}"),
        };
    }
}
