<?php

namespace HoceineEl\FilamentModularSubscriptions\Services;

use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
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
                'event' => $event,
                'description' => $description,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
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