<?php

namespace App\Observers;

use App\Models\Person;
use App\Services\UserRoleAbilitiesSync;

class PersonObserver
{
    public function updated(Person $person): void
    {
        if ((! $person->wasChanged('role') && ! $person->wasChanged('additional_roles')) || ! $person->user_id) {
            return;
        }

        $user = $person->user;

        if (! $user || $user->super_admin) {
            return;
        }

        app(UserRoleAbilitiesSync::class)->syncFromRoles($user, $person->allRoles());
    }
}
