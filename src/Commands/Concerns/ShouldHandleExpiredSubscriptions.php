<?php

namespace NewTags\FilamentModularSubscriptions\Commands\Concerns;

use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\Services\SubscriptionLogService;

trait ShouldHandleExpiredSubscriptions
{
    protected function handleSubscriptionNearExpiry($subscription): void
    {
        if (!$subscription->ends_at || $subscription->ends_at->diffInDays(now()) > 5) {
            return;
        }

        $notificationData = [
            'days' => number_format($subscription->ends_at->diffInDays(now())),
            'expiry_date' => $subscription->ends_at->format('Y-m-d'),
            'plan' => $subscription->plan?->trans_name
        ];

        $subscription->subscribable->notifySubscriptionChange('subscription_near_expiry', $notificationData);

        if ($subscription->ends_at->diffInDays(now()) <= 3) {
            $adminNotificationData = array_merge($notificationData, [
                'tenant' => $subscription->subscribable->name
            ]);

            $subscription->subscribable->notifySuperAdmins('subscription_near_expiry', $adminNotificationData);
        }
    }
    
    protected function handleExpiredSubscriptions($subscription,SubscriptionLogService $logService): void
    {
        if ($subscription->isExpired()) {
            $subscription->update(['status' => SubscriptionStatus::EXPIRED]);
            $logService->log(
                $subscription,
                'subscription_expired',
                __('filament-modular-subscriptions::fms.logs.subscription_expired'),
                $subscription->status->value,
                SubscriptionStatus::EXPIRED->value
            );

            $subscription->subscribable->notifySubscriptionChange('subscription_expired', [
                'plan' => $subscription->plan?->trans_name,
                'expiry_date' => $subscription->ends_at->format('Y-m-d')
            ]);
        }
    }

    protected function handleTrialExpiration($subscription, $logService): void
    {
        $oldStatus = $subscription->status;
        $subscription->update([
            'status' => SubscriptionStatus::EXPIRED,
            'trial_ends_at' => null,
            'has_used_trial' => true,
        ]);

        $logService->log(
            $subscription,
            'trial_expired',
            __('filament-modular-subscriptions::fms.logs.trial_expired'),
            $oldStatus->value,
            SubscriptionStatus::EXPIRED->value
        );

        $subscription->subscribable->notifySubscriptionChange('trial_expired', [
            'plan' => $subscription->plan?->trans_name,
            'expiry_date' => $subscription->ends_at->format('Y-m-d')
        ]);
    }
}
