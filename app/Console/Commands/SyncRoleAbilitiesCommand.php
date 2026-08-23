<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Models\User;
use App\Services\UserRoleAbilitiesSync;
use Illuminate\Console\Command;

class SyncRoleAbilitiesCommand extends Command
{
    protected $signature = 'raqib:sync-role-abilities
                            {--user= : username أو id للمستخدم}
                            {--role= : Person.role — مزامنة كل المستخدمين بهذا الدور (مثل project_secretariat)}
                            {--all : مزامنة كل المستخدمين المرتبطين بشخص (ما عدا super_admin)}';

    protected $description = 'إعادة ضبط صلاحيات role_users من data/role-abilities.php حسب Person.role';

    public function handle(UserRoleAbilitiesSync $sync): int
    {
        $userOption = $this->option('user');
        $roleOption = $this->option('role');
        $syncAll = (bool) $this->option('all');

        if (! $userOption && ! $syncAll && ! $roleOption) {
            $this->error('حدّد --user=USERNAME أو --role=ROLE أو --all');

            return self::FAILURE;
        }

        if ($userOption) {
            $user = User::query()
                ->where('username', $userOption)
                ->orWhere('id', $userOption)
                ->first();

            if (! $user) {
                $this->error('المستخدم غير موجود.');

                return self::FAILURE;
            }

            $this->syncOne($sync, $user);

            return self::SUCCESS;
        }

        if ($roleOption) {
            if (! in_array($roleOption, Person::ROLES, true)) {
                $this->error("دور غير معروف: {$roleOption}");

                return self::FAILURE;
            }

            $count = $this->syncPeopleQuery(
                $sync,
                Person::query()->where('role', $roleOption)
            );
            $this->info("تمت مزامنة {$count} مستخدم بدور {$roleOption}.");

            return self::SUCCESS;
        }

        $count = $this->syncPeopleQuery($sync, Person::query());

        $this->info("تمت مزامنة {$count} مستخدم.");

        return self::SUCCESS;
    }

    private function syncPeopleQuery(UserRoleAbilitiesSync $sync, $query): int
    {
        $count = 0;
        $query->whereNotNull('user_id')
            ->with('user')
            ->orderBy('id')
            ->chunkById(100, function ($people) use ($sync, &$count) {
                foreach ($people as $person) {
                    if ($person->user && ! $person->user->super_admin) {
                        $this->syncOne($sync, $person->user, $person->role);
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function syncOne(UserRoleAbilitiesSync $sync, User $user, ?string $role = null): void
    {
        $role ??= $user->person?->role;
        $sync->syncFromRole($user, $role);
        $this->line("  ✓ {$user->username} ← ".($role ?? 'بدون دور'));
    }
}
