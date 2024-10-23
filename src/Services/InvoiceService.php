<?php

namespace HoceineEl\FilamentModularSubscriptions\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Invoice;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Support\Facades\Mail;
use Spatie\Browsershot\Browsershot;
use Illuminate\Contracts\View\View;

class InvoiceService
{
    public function generateInvoice(Subscription $subscription): Invoice
    {
        $invoiceModel = config('filament-modular-subscriptions.models.invoice');
        $invoice = $invoiceModel::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->subscribable_id,
            'amount' => $this->calculateTotalAmount($subscription),
            'status' => PaymentStatus::UNPAID,
            'due_date' => now()->addDays(config('filament-modular-subscriptions.invoice_due_date_days')),
        ]);

        $this->createInvoiceItems($invoice, $subscription);

        return $invoice;
    }

    private function calculateTotalAmount(Subscription $subscription): float
    {
        $total = 0;
        if ($subscription->plan->is_pay_as_you_go) {
            foreach ($subscription->moduleUsages as $moduleUsage) {
                $total += $moduleUsage->pricing;
            }
        } else {
            $total = $subscription->plan->price;
        }

        return $total;
    }

    private function createInvoiceItems(Invoice $invoice, Subscription $subscription): void
    {
        $invoiceItemModel = config('filament-modular-subscriptions.models.invoice_item');

        if ($subscription->plan->is_pay_as_you_go) {
            foreach ($subscription->moduleUsages as $moduleUsage) {
                $invoiceItemModel::create([
                    'invoice_id' => $invoice->id,
                    'description' => __('filament-modular-subscriptions::modular-subscriptions.invoice.module_usage', ['module' => $moduleUsage->module->getName()]),
                    'quantity' => $moduleUsage->usage,
                    'unit_price' => $subscription->plan->modulePrice($moduleUsage->module),
                    'total' => $moduleUsage->pricing,
                ]);
            }
        } else {
            $invoiceItemModel::create([
                'invoice_id' => $invoice->id,
                'description' => __('filament-modular-subscriptions::modular-subscriptions.invoice.subscription_fee', ['plan' => $subscription->plan->trans_name]),
                'quantity' => 1,
                'unit_price' => $subscription->plan->price,
                'total' => $subscription->plan->price,
            ]);
        }
    }

    public function renewSubscription(Subscription $subscription, ?int $newPlanId = null): bool
    {
        $invoice = $this->generateInvoice($subscription);

        if ($newPlanId) {
            $subscription->plan_id = $newPlanId;
        }

        $subscription->starts_at = now();
        $subscription->ends_at = $subscription->starts_at->addDays($subscription->plan->period);
        $subscription->save();

        //@todo: trigger payment process (Stripe, etc...)

        $invoice->status = PaymentStatus::PAID;
        $invoice->paid_at = now();
        $invoice->save();

        return true;
    }

    public function generateAndSendInvoicePdf(Invoice $invoice)
    {
        $pdf = $this->generateInvoicePdf($invoice);
        $this->sendInvoiceEmail($invoice, $pdf);
    }

    private function generateInvoicePdf(Invoice $invoice)
    {
        $html = View::make('filament-modular-subscriptions::pages.invoice-pdf', compact('invoice'))->render();

        return Browsershot::html($html)
            ->format('A4')
            ->showBackground()
            ->margins(10, 10, 10, 10)
            ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36')
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
            ->waitUntilNetworkIdle()
            ->emulateMedia('screen')
            ->deviceScaleFactor(2)
            ->pdf();
    }

    private function sendInvoiceEmail(Invoice $invoice, $pdf)
    {
        $subscriber = $invoice->subscription->subscribable;
        $emailAddress = $subscriber->email ?? $subscriber->{config('filament-modular-subscriptions.tenant_attribute')};

        Mail::send('filament-modular-subscriptions::emails.invoice', ['invoice' => $invoice], function ($message) use ($invoice, $pdf, $emailAddress) {
            $message->to($emailAddress)
                ->subject(__('filament-modular-subscriptions::modular-subscriptions.invoice.email_subject', ['number' => $invoice->id]))
                ->attachData($pdf, "invoice-{$invoice->id}.pdf", [
                    'mime' => 'application/pdf',
                ]);
        });
    }
}
