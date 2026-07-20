<?php

namespace NewTags\FilamentModularSubscriptions\Resources\SubscriptionResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use NewTags\FilamentModularSubscriptions\FmsPlugin;
use NewTags\FilamentModularSubscriptions\Resources\SubscriptionResource;
use NewTags\FilamentModularSubscriptions\Services\InvoiceService;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected int $bonusDays = 0;

    protected function handleRecordCreation(array $data): Model
    {
        $data['subscribable_type'] = config('filament-modular-subscriptions.tenant_model');
        $plan = config('filament-modular-subscriptions.models.plan')::findOrFail($data['plan_id']);

        $this->bonusDays = $plan->is_trial_plan ? 0 : max(0, (int) ($data['bonus_days'] ?? 0));

        $subscribable = ! empty($data['subscribable_id'])
            ? config('filament-modular-subscriptions.tenant_model')::find($data['subscribable_id'])
            : FmsPlugin::getTenant();

        $endDate = $this->bonusDays > 0
            ? now()->addDays($plan->period + $this->bonusDays)
            : null;

        $record = $subscribable->subscribe($plan, endDate: $endDate);

        if ($this->bonusDays > 0) {
            $record->update(['bonus_days' => $this->bonusDays]);
        }

        return $record;
    }

    protected function afterCreate(): void
    {
        $invoiceService = app(InvoiceService::class);

        // Only generate initial invoice for limited plans
        if (! $this->record->plan->is_pay_as_you_go) {
            $invoiceService->generateInitialPlanInvoice(
                $this->record->subscribable,
                $this->record->plan,
                $this->bonusDays
            );
        }
    }
}
