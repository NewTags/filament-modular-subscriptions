<?php

namespace NewTags\FilamentModularSubscriptions\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use NewTags\FilamentModularSubscriptions\Traits\Subscribable;

class TestTenant extends Model
{
    use Subscribable;

    protected $table = 'test_tenants';

    protected $guarded = [];

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'test_tenant_users', 'test_tenant_id', 'user_id');
    }
}
