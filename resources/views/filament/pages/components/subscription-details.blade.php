<!-- Days Left -->
<div class="flex items-center justify-between">
    <div class="flex items-center space-x-2 gap-2">
        <x-filament::icon icon="heroicon-o-clock" class="w-5 h-5 text-gray-400" />
        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament-modular-subscriptions::fms.tenant_subscription.days_left') }}
        </span>
    </div>
    <span class="text-lg font-bold text-primary-600 dark:text-primary-400">
        {{ $tenant->daysLeft() }}
    </span>
</div>

<!-- Trial Status -->
<div class="flex items-center justify-between">
    <div class="flex items-center space-x-2 gap-2">
        <x-filament::icon icon="heroicon-o-beaker" class="w-5 h-5 text-gray-400" />
        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament-modular-subscriptions::fms.tenant_subscription.on_trial') }}
        </span>
    </div>
    <x-filament::badge :color="$subscription->onTrial() ? 'warning' : 'success'" class="text-sm">
        {{ $subscription->onTrial() ? __('filament-modular-subscriptions::fms.tenant_subscription.yes') : __('filament-modular-subscriptions::fms.tenant_subscription.no') }}
    </x-filament::badge>
</div>

@if ($subscription->onTrial())
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-2 gap-2">
            <x-filament::icon icon="heroicon-o-calendar" class="w-5 h-5 text-gray-400" />
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('filament-modular-subscriptions::fms.tenant_subscription.trial_ends_at') }}
            </span>
        </div>
        <span class="text-lg font-semibold text-warning-600 dark:text-warning-400">
            {{ $subscription->trial_ends_at->translatedFormat('M d, Y') }}
        </span>
    </div>
@endif