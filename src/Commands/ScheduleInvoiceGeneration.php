<?php

namespace NewTags\FilamentModularSubscriptions\Commands;

use Illuminate\Console\Command;
use NewTags\FilamentModularSubscriptions\Services\InvoiceService;
use NewTags\FilamentModularSubscriptions\Services\SubscriptionLogService;
use NewTags\FilamentModularSubscriptions\Traits\InvoiceGenerationTrait;
use NewTags\FilamentModularSubscriptions\Traits\SubscriptionProcessingTrait;
use NewTags\FilamentModularSubscriptions\Traits\NotificationHandlerTrait;

class ScheduleInvoiceGeneration extends Command
{
    use InvoiceGenerationTrait;
    use SubscriptionProcessingTrait;
    use NotificationHandlerTrait;

    protected $signature = 'fms:schedule-invoices';

    protected $description = 'Generate invoices for subscriptions based on their billing cycles';

    public function handle(InvoiceService $invoiceService, SubscriptionLogService $logService)
    {
        $this->info('Starting invoice generation process');

        $this->processActiveSubscriptions($invoiceService, $logService);

        $this->info('Invoice generation process completed');
    }
}
