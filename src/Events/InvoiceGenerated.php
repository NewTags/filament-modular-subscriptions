<?php

namespace HoceineEl\FilamentModularSubscriptions\Events;

use HoceineEl\FilamentModularSubscriptions\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceGenerated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Invoice $invoice)
    {
    }
} 