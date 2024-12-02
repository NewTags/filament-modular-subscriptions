<x-filament::section class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl overflow-hidden">
    <x-slot name="heading">
        <div class="flex items-center space-x-2">
            <x-filament::icon icon="heroicon-o-credit-card" class="w-6 h-6 text-primary-500" />
            <span class="text-xl font-bold">
                {{ __('filament-modular-subscriptions::fms.tenant_subscription.current_subscription') }}
            </span>
        </div>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Plan Info Card -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('filament-modular-subscriptions::fms.tenant_subscription.plan') }}
            </h3>
            <p class="mt-2 text-2xl font-bold text-primary-600 dark:text-primary-400">
                {{ $subscription->plan->trans_name }}
            </p>
        </div>

        <!-- Status Card -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('filament-modular-subscriptions::fms.tenant_subscription.status') }}
            </h3>
            <div class="mt-2">
                <x-filament::badge size="xl" :color="$subscription->status->getColor()" class="text-lg">
                    {{ $subscription->status->getLabel() }}
                </x-filament::badge>
            </div>
        </div>

        <!-- Start Date Card -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('filament-modular-subscriptions::fms.tenant_subscription.started_on') }}
            </h3>
            <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ $subscription->starts_at->translatedFormat('M d, Y') }}
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $subscription->starts_at->translatedFormat('h:i A') }}
            </p>
        </div>

        <!-- End Date Card -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('filament-modular-subscriptions::fms.tenant_subscription.ends_on') }}
            </h3>
            <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ $subscription->ends_at->translatedFormat('M d, Y') }}
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $subscription->ends_at->translatedFormat('h:i A') }}
            </p>
        </div>
    </div>

    <!-- Subscription Details -->
    <div class="mt-8">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
            {{ __('filament-modular-subscriptions::fms.tenant_subscription.subscription_details') }}
        </h3>
        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6 space-y-4">
            @include('filament-modular-subscriptions::filament.pages.components.subscription-details', [
                'subscription' => $subscription,
                'tenant' => $tenant
            ])
        </div>
    </div>
</x-filament::section> 