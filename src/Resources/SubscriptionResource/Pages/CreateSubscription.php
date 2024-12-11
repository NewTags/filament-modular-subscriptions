<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource;
use HoceineEl\FilamentModularSubscriptions\Services\InvoiceService;
use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use HoceineEl\FilamentModularSubscriptions\FmsPlugin;
use Illuminate\Database\Eloquent\Model;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['subscribable_type'] = config('filament-modular-subscriptions.tenant_model');
        $plan = config('filament-modular-subscriptions.models.plan')::findOrFail($data['plan_id']);


        $tenant = FmsPlugin::getTenant();

        $record = $tenant->subscribe($plan);

        return $record;
    }

    protected function afterCreate(): void
    {
        $invoiceService = app(InvoiceService::class);

        // Only generate initial invoice for limited plans
        if (!$this->record->plan->is_pay_as_you_go) {
            $invoiceService->generateInitialPlanInvoice(
                $this->record->subscribable,
                $this->record->plan
            );
        }
    }
}
