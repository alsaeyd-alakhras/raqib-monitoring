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
                            {--all : مزامنة كل المستخدمين المرتبطين بشخص (ما عدا super_admin)}';

    protected $description = 'إعادة ضبط صلاحيات role_users من data/role-abilities.php حسب Person.role';

    public function handle(UserRoleAbilitiesSync $sync): int
    {
        $userOption = $this->option('user');
        $syncAll = (bool) $this->option('all');

        if (! $userOption && ! $syncAll) {
            $this->error('حدّد --user=USERNAME أو --all');

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

        $count = 0;
        Person::query()
            ->whereNotNull('user_id')
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

        $this->info("تمت مزامنة {$count} مستخدم.");

        return self::SUCCESS;
    }

    private function syncOne(UserRoleAbilitiesSync $sync, User $user, ?string $role = null): void
    {
        $role ??= $user->person?->role;
        $sync->syncFromRole($user, $role);
        $this->line("  ✓ {$user->username} ← ".($role ?? 'بدون دور'));
    }
}
