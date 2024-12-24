<?php

namespace NewTags\FilamentModularSubscriptions\Traits;


use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use NewTags\FilamentModularSubscriptions\Pages\TenantSubscription;

trait HasSubscriptionNotifications
{
    /**
     * Get users who should be notified about subscription changes.
     * This method should be implemented by the tenant model.
     */
    public function getTenantAdminsUsing()
    {
        if (method_exists($this, 'admins')) {
            return $this->admins();
        }
        throw new \Exception('The tenant model must implement getShouldNotifyUsersQuery() or have a admins() relationship');
    }

    /**
     * Notify users about subscription changes
     */
    public function notifySubscriptionChange(string $action, array $additionalData = [], string $url = null): void
    {
        if (version_compare(app()->version(), '11.23', '>=')) {
            defer(function () use ($action, $additionalData, $url) {
                $this->notifyAdminsUsing($action, $additionalData, $url);
            });
        } else {
            $this->notifyAdminsUsing($action, $additionalData, $url);
        }
    }

    public function notifyAdminsUsing(string $action, array $additionalData = [], string $url = null): void
    {
        $users = $this->getTenantAdminsUsing()->get();

        // Merge default subscription data with additional data
        $data = array_merge(
            $this->getSubscriptionNotificationData($action),
            $additionalData
        );

        $this->getNotificationUsing(
            __('filament-modular-subscriptions::fms.notifications.subscription.' . $action . '.title'),
            __('filament-modular-subscriptions::fms.notifications.subscription.' . $action . '.body', $data)
        )
            ->icon($this->getNotificationIcon($action))
            ->iconColor($this->getNotificationColor($action))
            ->url($url ?? TenantSubscription::getUrl(['tab' => 'subscription']))
            ->sendToDatabase($users);
    }

    public function getSuperAdminsQuery(): Builder
    {
        return config('filament-modular-subscriptions.user_model')::query()->role('super_admin');
    }

    public function notifySuperAdmins(string $action, array $additionalData = [], string $url = null): void
    {
        $users = $this->getSuperAdminsQuery()->get();
        $data = array_merge([
            'tenant' => $this->name,
            'date' => now()->format('Y-m-d H:i:s'),
        ], $additionalData);

        $title = __('filament-modular-subscriptions::fms.notifications.admin_message.' . $action . '.title');
        $body = __('filament-modular-subscriptions::fms.notifications.admin_message.' . $action . '.body', $data);

        $this->getNotificationUsing(
            $title,
            $body
        )
            ->icon($this->getNotificationIcon($action))
            ->iconColor($this->getNotificationColor($action))
            ->sendToDatabase($users);
    }

    public function getNotificationUsing($title, $body)
    {
        return Notification::make()
            ->title($title)
            ->body($body);
    }

    protected function getNotificationIcon(string $action): string
    {
        return match ($action) {
            'expired', 'suspended', 'cancelled' => 'heroicon-o-exclamation-triangle',
            'payment_received' => 'heroicon-o-currency-dollar',
            'payment_rejected', 'payment_overdue' => 'heroicon-o-x-circle',
            'invoice_generated' => 'heroicon-o-document-text',
            'invoice_overdue' => 'heroicon-o-clock',
            'subscription_renewed' => 'heroicon-o-arrow-path',
            'subscription_switched' => 'heroicon-o-arrow-right-circle',
            'subscription_activated' => 'heroicon-o-check-circle',
            'subscription_near_expiry' => 'heroicon-o-clock',
            'usage_limit_exceeded' => 'heroicon-o-exclamation-circle',
            default => 'heroicon-o-bell',
        };
    }

    protected function getNotificationColor(string $action): string
    {
        return match ($action) {
            'expired', 'suspended', 'cancelled', 'payment_rejected' => 'danger',
            'payment_received', 'subscription_renewed',
            'subscription_activated', 'invoice_generated' => 'success',
            'subscription_switched' => 'info',
            'payment_overdue', 'invoice_overdue',
            'subscription_near_expiry' => 'warning',
            'usage_limit_exceeded' => 'danger',
            default => 'primary',
        };
    }

    /**
     * Get the notification data for subscription status changes
     */
    protected function getSubscriptionNotificationData(string $action): array
    {
        $subscription = $this->activeSubscription();
        $plan = $subscription?->plan;

        return [
            'tenant' => $this->name,
            'plan' => $plan?->name ?? 'N/A',
            'start_date' => $subscription?->starts_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'end_date' => $subscription?->ends_at?->format('Y-m-d') ?? 'N/A',
            'currency' => $plan?->currency ?? 'USD',
            'amount' => $this->totalPricing() ?? 0,
            'date' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
