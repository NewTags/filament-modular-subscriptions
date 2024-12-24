<?php

namespace NewTags\FilamentModularSubscriptions\Traits;


use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTrialSubscription
{

    /**
     * Check if subscription is currently in trial period.
     */
    public function onTrial(): bool
    {
        return $this->activeSubscription() && $this->activeSubscription()->onTrial();
    }



    /**
     * Check if model has a generic trial period.
     */
    public function onGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Get remaining trial days.
     */
    public function trialDaysLeft(): int
    {
        if ($this->onTrial()) {
            return $this->activeSubscription()->trial_ends_at->diffInDays(now());
        }

        if ($this->onGenericTrial()) {
            return $this->trial_ends_at->diffInDays(now());
        }

        return 0;
    }

    /**
     * Extend trial period by specified number of days.
     */
    public function extendTrial(int $days): void
    {
        if ($this->onTrial()) {
            $subscription = $this->activeSubscription();
            $subscription->trial_ends_at = $subscription->trial_ends_at->addDays($days);
            $subscription->save();
            $this->clearFmsCache();
        } elseif ($this->onGenericTrial()) {
            $this->trial_ends_at = $this->trial_ends_at->addDays($days);
            $this->save();
        }
    }

    /**
     * End trial period immediately.
     */
    public function endTrial(): void
    {
        if ($this->onTrial()) {
            $subscription = $this->activeSubscription();
            $subscription->trial_ends_at = now();
            $subscription->save();
            $this->clearFmsCache();
        } elseif ($this->onGenericTrial()) {
            $this->trial_ends_at = now();
            $this->save();
        }
    }


    public function canUseTrial(): bool
    {
        return !$this->subscription?->has_used_trial;
    }
}
