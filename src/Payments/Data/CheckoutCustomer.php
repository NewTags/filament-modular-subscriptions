<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Data;

final readonly class CheckoutCustomer
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $phoneCountryCode = null,
        public ?string $phoneNumber = null,
    ) {}

    public function hasContact(): bool
    {
        return filled($this->email) || filled($this->phoneNumber);
    }

    public function hasPhone(): bool
    {
        return filled($this->phoneCountryCode) && filled($this->phoneNumber);
    }
}
