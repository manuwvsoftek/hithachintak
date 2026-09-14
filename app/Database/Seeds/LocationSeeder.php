<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds the full Prant / Jila / Prakhand tree from the VHP Prant Mantri
 * List (as captured in the Hithachintak prototype's LOCATION_MASTER data,
 * app/Database/Seeds/data/location_master.json). All 50 Prants are
 * created; only the handful the source data actually enumerates
 * (Uttar Karnataka, Dakshin Andhra, Vidarbha, Mahakoshal, Madhya Bharat,
 * Malwa, Braj) get Jila/Prakhand rows — the rest are empty shells ready
 * for Masters > Locations > Add Jila, same "pending" state the prototype
 * itself shows for Prants without data on file yet.
 *
 * Each Prant also gets its enrolment-form languages seeded from the same
 * source file's per-Prant `lang` tag (e.g. Karnataka's Prants are tagged
 * `kn`) — always paired with English and Hindi, e.g. Dakshin Karnataka ->
 * kn,en,hi. A Prant with no `lang` tag (the Hindi-belt Prants) gets
 * hi,en. Only ever fills a currently-empty `languages` column, so it
 * never overwrites a Super Admin's own customisation via Masters.
 *
 * Idempotent — safe to re-run; existing rows (matched by name within
 * their parent) are left untouched, aside from that languages backfill.
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $db   = db_connect();
        $now  = date('Y-m-d H:i:s');
        $path = __DIR__ . '/data/location_master.json';

        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $existingPrants = $this->indexBy(
            $db->table('prants')->select('id, name, languages')->get()->getResultArray(),
            'name'
        );

        foreach ($data as $prant) {
            $prantName = $prant['prant'];
            $languages = $this->deriveLanguages($prant['lang'] ?? null);

            if (isset($existingPrants[$prantName])) {
                $prantId = $existingPrants[$prantName]['id'];
                if (empty($existingPrants[$prantName]['languages'])) {
                    $db->table('prants')->where('id', $prantId)->update(['languages' => $languages, 'updated_at' => $now]);
                }
            } else {
                $db->table('prants')->insert([
                    'name' => $prantName, 'languages' => $languages, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $prantId = $db->insertID();
            }

            if (! $prant['jilas']) {
                continue;
            }

            $existingJilas = $this->indexBy(
                $db->table('jilas')->select('id, name')->where('prant_id', $prantId)->get()->getResultArray(),
                'name'
            );

            foreach ($prant['jilas'] as $jila) {
                $jilaName = $jila['jila'];

                if (isset($existingJilas[$jilaName])) {
                    $jilaId = $existingJilas[$jilaName]['id'];
                } else {
                    $db->table('jilas')->insert([
                        'prant_id' => $prantId, 'name' => $jilaName, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $jilaId = $db->insertID();
                }

                $existingPrakhands = $this->indexBy(
                    $db->table('prakhands')->select('id, name')->where('jila_id', $jilaId)->get()->getResultArray(),
                    'name'
                );

                $batch = [];
                foreach ($jila['prakhands'] as $prakhandName) {
                    if (isset($existingPrakhands[$prakhandName])) {
                        continue;
                    }
                    $batch[] = [
                        'jila_id' => $jilaId, 'name' => $prakhandName, 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                if ($batch) {
                    $db->table('prakhands')->insertBatch($batch);
                }
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function indexBy(array $rows, string $key): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row[$key]] = $row;
        }

        return $indexed;
    }

    private function deriveLanguages(?string $regional): string
    {
        $codes = $regional ? [$regional, 'en', 'hi'] : ['hi', 'en'];

        return implode(',', array_unique($codes));
    }
}
