<?php

namespace NewTags\FilamentModularSubscriptions\Services;

use Illuminate\Support\Facades\Log;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\Models\Subscription;

class SubscriptionLogService
{
    public function log(
        Subscription $subscription,
        string $event,
        string $description,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?array $metadata = null
    ): void {
        try {
            $logModel = config('filament-modular-subscriptions.models.subscription_log');

            $logModel::create([
                'subscription_id' => $subscription->id,
                // Store the raw event key; it is translated at read time so the label is
                // always locale-correct and new event types never freeze as a raw key.
                'event' => $event,
                'description' => $description,
                'old_status' => $oldStatus instanceof SubscriptionStatus ? $oldStatus->getLabel() : $oldStatus,
                'new_status' => $newStatus instanceof SubscriptionStatus ? $newStatus->getLabel() : $newStatus,
                'metadata' => $metadata,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create subscription log', [
                'subscription_id' => $subscription->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
