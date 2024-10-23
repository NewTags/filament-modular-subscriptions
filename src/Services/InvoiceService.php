<?php

namespace HoceineEl\FilamentModularSubscriptions\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Invoice;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Support\Facades\Mail;

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
        $pdf = Pdf::loadView('filament-modular-subscriptions::pages.invoice-pdf', compact('invoice'));

        $pdf->setOption('enable-local-file-access', true);
        $pdf->setOption('enable-unicode', true);
        $pdf->setOption('encoding', 'UTF-8');
        $pdf->setOption('font-family', 'Cairo');
        $pdf->setOption('margin-top', 10);
        $pdf->setOption('margin-right', 10);
        $pdf->setOption('margin-bottom', 10);
        $pdf->setOption('margin-left', 10);
        $pdf->setOption('direction', 'rtl');

        return $pdf;
    }

    private function sendInvoiceEmail(Invoice $invoice, $pdf)
    {
        $subscriber = $invoice->subscription->subscribable;
        $emailAddress = $subscriber->email ?? $subscriber->{config('filament-modular-subscriptions.tenant_attribute')};

        Mail::send('filament-modular-subscriptions::emails.invoice', ['invoice' => $invoice], function ($message) use ($invoice, $pdf, $emailAddress) {
            $message->to($emailAddress)
                ->subject(__('filament-modular-subscriptions::modular-subscriptions.invoice.email_subject', ['number' => $invoice->id]))
                ->attachData($pdf->output(), "invoice-{$invoice->id}.pdf");
        });
    }
}
