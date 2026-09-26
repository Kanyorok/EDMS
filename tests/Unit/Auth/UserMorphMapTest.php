<?php

namespace Tests\Unit\Auth;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Tests\TestCase;

class UserMorphMapTest extends TestCase
{
    public function test_user_morph_alias_matches_the_persisted_model_type(): void
    {
        $this->assertSame('UserID', (new User())->getMorphClass());
        $this->assertSame(User::class, Relation::getMorphedModel('UserID'));
    }
}
