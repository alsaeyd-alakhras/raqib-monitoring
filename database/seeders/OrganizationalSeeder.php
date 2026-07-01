<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Department;
use App\Models\Person;
use App\Models\RoleUser;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrganizationalSeeder extends Seeder
{
    public function run(): void
    {
        $hierarchy = [
            'الجمعية' => [
                'سكرتاريا' => [
                    'سكرتير المجلس',
                    'سكرتير',
                ],
                'دائرة الشؤون الإدارية' => [
                    'قسم شؤون الموظفين',
                    'قسم تنمية الموارد البشرية',
                    'قسم الحاسوب والشبكات',
                    'قسم الصيانة العامة',
                    'قسم الخدمات',
                ],
                'دائرة التنمية الاجتماعية' => [
                    'قسم الأيتام والأسر',
                    'المدارس',
                ],
                'دائرة المشاريع والتسويق والإعلام' => [
                    'مكتب خانيونس',
                    'مكتب رفح',
                    'مكتب دير البلح',
                    'مكتب المغازي',
                    'مكتب الزوايدة',
                    'مكتب البريج',
                    'مكتب غزة',
                    'مكتب جباليا',
                    'قسم المشاريع',
                    'قسم التسويق',
                    'قسم الإعلام',
                    'متابعة السيارات',
                ],
                'دائرة الشؤون المالية' => [
                    'قسم الحسابات',
                    'قسم المشتريات',
                    'قسم المخازن',
                    'قسم الموازنة والتدقيق',
                ],
                'دائرة الاستثمار الخيري' => [
                    'قسم التعليم',
                    'قسم الزراعة',
                    'قسم المشاريع التنموية',
                    'مطبخ البركة',
                    'مطبخ الزيناتي / خارجي',
                    'عمارة سعد',
                    'عمارة المدينة',
                ],
                'دائرة الرقابة العامة' => [],
            ],
            'المراكز الصحية' => [
                'يافا' => [
                    'عيادة مسالك بولية',
                    'عيادة فحص السمع',
                    'عيادة النطق',
                    'عيادة النساء',
                    'عيادة الاوعية الدموية',
                    'عيادة الاسنان- رجال',
                    'عيادة اسنان -نساء',
                    'عيادة عظام',
                    'عيادة طب العيون',
                    'عيادة جلدية',
                    'عيادة جراحة واعصاب',
                    'عيادة جراحة عامة',
                    'عيادة بصريات',
                    'المختبر',
                    'عيادة العمليات',
                    'عيادة العلاج الطبيعي',
                    'الصيدلية',
                    'عيادة اذن وانف حنجرة',
                ],
                'الكويتي' => [
                    'قسم البانوراما',
                    'قسم الايكو اطفال',
                    'قسم الايكو Echo',
                    'قسم الألتراساوند US',
                    'قسم الأشعة المقطعية CT',
                    'قسم اشعة',
                ],
                'الوسطى' => [
                    'عيادة الاسنان- رجال',
                    'عيادة عظام',
                    'عيادة طب العيون',
                    'عيادة طب اسرة',
                    'عيادة جلدية',
                    'عيادة جراحة واعصاب',
                    'بانوراما اشعة',
                    'عيادة باطنة',
                    'المختبر',
                    'عيادة العلاج الطبيعي',
                    'الصيدلية',
                    'عيادة اذن وانف حنجرة',
                ],
            ],
        ];

        foreach ($hierarchy as $centerName => $departments) {
            $center = Center::firstOrCreate([
                'name' => $centerName,
            ]);

            foreach ($departments as $departmentName => $sections) {
                $department = Department::firstOrCreate([
                    'center_id' => $center->id,
                    'name' => $departmentName,
                ]);

                foreach ($sections as $sectionName) {
                    Section::firstOrCreate([
                        'department_id' => $department->id,
                        'name' => $sectionName,
                    ]);
                }
            }
        }

        $monitors = [
            [
                'name' => 'سمير نصار',
                'username' => 'monitor1',
                'email' => 'monitor1@raqib.local',
            ],
            [
                'name' => 'اياد أبو عبدو',
                'username' => 'monitor2',
                'email' => 'monitor2@raqib.local',
            ],
            [
                'name' => 'محمد حسن',
                'username' => 'monitor3',
                'email' => 'monitor3@raqib.local',
            ],
        ];

        foreach ($monitors as $monitor) {
            $user = User::firstOrCreate(
                ['email' => $monitor['email']],
                [
                    'name' => $monitor['name'],
                    'username' => $monitor['username'],
                    'user_type' => 'employee',
                    'is_active' => true,
                    'password' => Str::random(12),
                ]
            );

            Person::firstOrCreate(
                ['user_id' => $user->id],
                ['name' => $monitor['name']]
            );

            RoleUser::firstOrCreate([
                'role_name' => 'people.view',
                'user_id' => $user->id,
                'ability' => 'allow',
            ]);
        }
    }
}
