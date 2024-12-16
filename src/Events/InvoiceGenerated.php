<?php

namespace NewTags\FilamentModularSubscriptions\Events;

use NewTags\FilamentModularSubscriptions\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceGenerated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Invoice $invoice) {}
}
