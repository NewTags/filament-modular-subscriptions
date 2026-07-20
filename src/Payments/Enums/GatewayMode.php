<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Enums;

enum GatewayMode: string
{
    case TEST = 'test';
    case LIVE = 'live';

    public function isLive(): bool
    {
        return $this === self::LIVE;
    }
}
