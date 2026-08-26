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
                            {--section-managers : مزامنة مديري الأقسام فقط (role = section_manager)}
                            {--all : مزامنة كل المستخدمين المرتبطين بشخص (ما عدا super_admin)}';

    protected $description = 'إعادة ضبط صلاحيات role_users من data/role-abilities.php حسب أدوار الشخص (أساسي + إضافي)';

    public function handle(UserRoleAbilitiesSync $sync): int
    {
        $userOption = $this->option('user');
        $roleOption = $this->option('role');
        $sectionManagers = (bool) $this->option('section-managers');
        $syncAll = (bool) $this->option('all');

        if (! $userOption && ! $syncAll && ! $roleOption && ! $sectionManagers) {
            $this->error('حدّد --user=USERNAME أو --role=ROLE أو --section-managers أو --all');

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

        if ($sectionManagers) {
            $people = Person::query()
                ->where('role', 'section_manager')
                ->whereNotNull('user_id')
                ->with('user')
                ->orderBy('id')
                ->get();

            $withPmRole = $people->filter(
                fn (Person $person) => $person->hasRole('project_manager')
            );
            $withoutPmRole = $people->reject(
                fn (Person $person) => $person->hasRole('project_manager')
            );

            $count = $this->syncPeopleQuery(
                $sync,
                Person::query()->where('role', 'section_manager')
            );

            $this->info("تمت مزامنة {$count} مدير/ة قسم (مع الأدوار الإضافية إن وُجدت).");

            if ($withPmRole->isNotEmpty()) {
                $this->line('  ↳ يظهرون كمدير مشروع في قائمة المشاريع: ' . $withPmRole->pluck('name')->implode('، '));
            }

            if ($withoutPmRole->isNotEmpty()) {
                $this->warn('  ↳ بدون دور «مدير مشروع» إضافي (لن يظهروا في قائمة مديري المشاريع): ' . $withoutPmRole->pluck('name')->implode('، '));
                $this->warn('     فعّل «مدير مشروع» من دليل الأشخاص ← تعديل ← أدوار إضافية.');
            }

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
                        $this->syncOne($sync, $person->user, $person->allRoles());
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * @param  list<string>|null  $roles
     */
    private function syncOne(UserRoleAbilitiesSync $sync, User $user, ?array $roles = null): void
    {
        $roles ??= $user->person?->allRoles() ?? [];
        $sync->syncFromRoles($user, $roles);
        $label = $roles === [] ? 'بدون دور' : implode(' + ', $roles);
        $this->line("  ✓ {$user->username} ← {$label}");
    }
}
