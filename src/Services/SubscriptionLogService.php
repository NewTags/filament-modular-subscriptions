<?php

namespace NewTags\FilamentModularSubscriptions\Services;

use NewTags\FilamentModularSubscriptions\Models\Subscription;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use Illuminate\Support\Facades\Log;

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
                'event' => __('filament-modular-subscriptions::fms.logs.events.' . $event),
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
