<?php

namespace Database\Seeders;

use App\Models\ChecklistGroup;
use App\Models\ChecklistItem;
use Illuminate\Database\Seeder;

class ChecklistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            [
                'name' => 'اللوجستيك والموارد',
                'order' => 1,
                'items' => [
                    ['name' => 'المواد والمستلزمات', 'order' => 1, 'has_person_field' => false],
                    ['name' => 'تأكيد المورد', 'order' => 2, 'has_person_field' => false],
                    ['name' => 'حركة السيارات', 'order' => 3, 'has_person_field' => false],
                    ['name' => 'الكشوفات والأسماء معتمدة', 'order' => 4, 'has_person_field' => false],
                    ['name' => 'التنسيق مع الإعلام', 'order' => 5, 'has_person_field' => false],
                    ['name' => 'طباعة الاعلاميات', 'order' => 6, 'has_person_field' => false],
                    ['name' => 'مراحل التصوير — قبل وأثناء', 'order' => 7, 'has_person_field' => false],
                ],
            ],
            [
                'name' => 'التحضيرات الميدانية',
                'order' => 2,
                'items' => [
                    ['name' => 'تبليغ المستفيدين', 'order' => 1, 'has_person_field' => false],
                    ['name' => 'التنسيق مع الرقابة', 'order' => 2, 'has_person_field' => false],
                    ['name' => 'التنسيق مع المندوب / المخيم', 'order' => 3, 'has_person_field' => false],
                    ['name' => 'الموقع جاهز ومهيأ / الاعلام', 'order' => 4, 'has_person_field' => false],
                    ['name' => 'كود الكشوفات والكوبونات', 'order' => 5, 'has_person_field' => false],
                ],
            ],
            [
                'name' => 'الموارد البشرية',
                'order' => 3,
                'items' => [
                    ['name' => 'الاستقبال', 'order' => 1, 'has_person_field' => true],
                    ['name' => 'المطابقة', 'order' => 2, 'has_person_field' => true],
                    ['name' => 'التسليم', 'order' => 3, 'has_person_field' => true],
                    ['name' => 'المستودع', 'order' => 4, 'has_person_field' => true],
                    ['name' => 'التوثيق', 'order' => 5, 'has_person_field' => true, 'has_file_field' => true],
                    ['name' => 'تقرير ختامي + رابط توثيق', 'order' => 6, 'has_person_field' => true, 'has_file_field' => true],
                    ['name' => 'كشوفات توقيع ختامية', 'order' => 7, 'has_person_field' => true, 'has_file_field' => true],
                ],
            ],
        ];

        foreach ($groups as $groupData) {
            $items = $groupData['items'];
            unset($groupData['items']);

            $group = ChecklistGroup::firstOrCreate(
                ['name' => $groupData['name']],
                [
                    'order' => $groupData['order'],
                    'is_active' => true,
                ]
            );

            foreach ($items as $itemData) {
                $item = ChecklistItem::firstOrCreate(
                    [
                        'group_id' => $group->id,
                        'name' => $itemData['name'],
                    ],
                    [
                        'has_person_field' => $itemData['has_person_field'],
                        'has_file_field' => $itemData['has_file_field'] ?? false,
                        'order' => $itemData['order'],
                        'is_active' => true,
                    ]
                );

                if (($itemData['has_file_field'] ?? false) && ! $item->has_file_field) {
                    $item->update(['has_file_field' => true]);
                }
            }
        }
    }
}
