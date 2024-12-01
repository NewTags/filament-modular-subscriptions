<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource\Pages;

use HoceineEl\FilamentModularSubscriptions\Filament\Resources\SubscriptionLogResource;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionLogs extends ListRecords
{
    protected static string $resource = SubscriptionLogResource::class;
} 