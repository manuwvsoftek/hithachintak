<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $rows = [
            ['code' => 'hc', 'name' => 'Hithachintak', 'mode' => 'min', 'rate' => 20.00, 'sort_order' => 1],
            ['code' => 'drn', 'name' => 'Dharma Raksha Nidhi', 'mode' => 'open', 'rate' => null, 'sort_order' => 2],
            ['code' => 'mag', 'name' => 'Magazine Subscription', 'mode' => 'open', 'rate' => null, 'sort_order' => 3],
            ['code' => 'amd', 'name' => 'Autopay Monthly Donation to VHP', 'mode' => 'min', 'rate' => 100.00, 'is_recurring' => 1, 'sort_order' => 4],
        ];

        $db = db_connect();

        foreach ($rows as $row) {
            $exists = $db->table('programmes')->where('code', $row['code'])->countAllResults();
            if ($exists > 0) {
                continue;
            }
            $row['status']     = 'active';
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $db->table('programmes')->insert($row);
        }
    }
}
