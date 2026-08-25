<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TVClubSeeder extends Seeder
{
    /**
     * Run the database seeds for TV Club module.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // ----------------------------------------------------
            // 1. GENERATIONS (4 Angkatan: 2023, 2024, 2025, 2026)
            // ----------------------------------------------------
            $generationsData = [
                [
                    'name' => 'Generasi 8 (2023)',
                    'year' => 2023,
                    'description' => 'Angkatan ke-8 TV Club STMIK Widya Pratama Pekalongan (Masa bakti 2023-2024 - Alumni).',
                    'status' => 0,
                    'created_at' => '2023-09-01 08:00:00',
                    'updated_at' => '2024-08-31 17:00:00',
                ],
                [
                    'name' => 'Generasi 9 (2024)',
                    'year' => 2024,
                    'description' => 'Angkatan ke-9 TV Club STMIK Widya Pratama Pekalongan (Masa bakti 2024-2025 - Demisioner).',
                    'status' => 0,
                    'created_at' => '2024-09-01 08:00:00',
                    'updated_at' => '2025-08-31 17:00:00',
                ],
                [
                    'name' => 'Generasi 10 (2025)',
                    'year' => 2025,
                    'description' => 'Angkatan ke-10 TV Club STMIK Widya Pratama Pekalongan (Kru Utama & Pengurus Aktif).',
                    'status' => 1,
                    'created_at' => '2025-09-01 08:00:00',
                    'updated_at' => '2026-01-15 10:30:00',
                ],
                [
                    'name' => 'Generasi 11 (2026)',
                    'year' => 2026,
                    'description' => 'Angkatan ke-11 TV Club STMIK Widya Pratama Pekalongan (Kru Muda & Magang Siaran).',
                    'status' => 1,
                    'created_at' => '2026-02-01 09:00:00',
                    'updated_at' => '2026-08-01 14:00:00',
                ],
            ];

            $generations = [];
            foreach ($generationsData as $gen) {
                $generations[$gen['year']] = TVClubGeneration::updateOrCreate(
                    ['year' => $gen['year']],
                    [
                        'name' => $gen['name'],
                        'description' => $gen['description'],
                        'status' => $gen['status'],
                        'created_at' => $gen['created_at'],
                        'updated_at' => $gen['updated_at'],
                    ]
                );
            }

            // ----------------------------------------------------
            // 2. MEMBERS (24 Anggota Mahasiswa)
            // ----------------------------------------------------
            $membersData = [
                // Gen 8 (2023) - Alumni
                [
                    'year' => 2023,
                    'student_number' => '20.240.0012',
                    'name' => 'Rizki Ramadhan',
                    'photo' => null,
                    'status' => 0,
                    'created_at' => '2023-09-05 10:00:00',
                ],
                [
                    'year' => 2023,
                    'student_number' => '20.240.0034',
                    'name' => 'Anisa Fitriani',
                    'photo' => null,
                    'status' => 0,
                    'created_at' => '2023-09-05 10:15:00',
                ],
                [
                    'year' => 2023,
                    'student_number' => '20.240.0056',
                    'name' => 'Dimas Prasetyo',
                    'photo' => null,
                    'status' => 0,
                    'created_at' => '2023-09-05 10:30:00',
                ],
                [
                    'year' => 2023,
                    'student_number' => '20.240.0078',
                    'name' => 'Nurul Hidayah',
                    'photo' => null,
                    'status' => 0,
                    'created_at' => '2023-09-05 10:45:00',
                ],
                [
                    'year' => 2023,
                    'student_number' => '20.240.0090',
                    'name' => 'Fajar Kurniawan',
                    'photo' => null,
                    'status' => 0,
                    'created_at' => '2023-09-05 11:00:00',
                ],
                [
                    'year' => 2023,
                    'student_number' => '20.240.0102',
                    'name' => 'Dewi Lestari',
                    'photo' => null,
                    'status' => 0,
                    'created_at' => '2023-09-05 11:15:00',
                ],

                // Gen 9 (2024) - Mixed (Alumni/Active)
                [
                    'year' => 2024,
                    'student_number' => '21.240.0015',
                    'name' => 'Bayu Nugroho',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2024-09-08 09:00:00',
                ],
                [
                    'year' => 2024,
                    'student_number' => '21.240.0028',
                    'name' => 'Tiara Anggraini',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2024-09-08 09:20:00',
                ],
                [
                    'year' => 2024,
                    'student_number' => '21.240.0041',
                    'name' => 'Hendra Setiawan',
                    'photo' => null,
                    'status' => 0,
                    'created_at' => '2024-09-08 09:40:00',
                ],
                [
                    'year' => 2024,
                    'student_number' => '21.240.0063',
                    'name' => 'Maya Indah Puspita',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2024-09-08 10:00:00',
                ],
                [
                    'year' => 2024,
                    'student_number' => '21.240.0082',
                    'name' => 'Galih Permana',
                    'photo' => null,
                    'status' => 0,
                    'created_at' => '2024-09-08 10:20:00',
                ],
                [
                    'year' => 2024,
                    'student_number' => '21.240.0097',
                    'name' => 'Sinta Maulida',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2024-09-08 10:40:00',
                ],

                // Gen 10 (2025) - Active
                [
                    'year' => 2025,
                    'student_number' => '22.240.0008',
                    'name' => 'Aditya Pratama',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2025-09-10 08:30:00',
                ],
                [
                    'year' => 2025,
                    'student_number' => '22.240.0022',
                    'name' => 'Nabila Putri Zahra',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2025-09-10 08:50:00',
                ],
                [
                    'year' => 2025,
                    'student_number' => '22.240.0035',
                    'name' => 'Reza Fahlevi',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2025-09-10 09:10:00',
                ],
                [
                    'year' => 2025,
                    'student_number' => '22.240.0051',
                    'name' => 'Zahra Amalia',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2025-09-10 09:30:00',
                ],
                [
                    'year' => 2025,
                    'student_number' => '22.240.0074',
                    'name' => 'Bagas Wicaksono',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2025-09-10 09:50:00',
                ],
                [
                    'year' => 2025,
                    'student_number' => '22.240.0089',
                    'name' => 'Intan Permatasari',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2025-09-10 10:10:00',
                ],

                // Gen 11 (2026) - Active
                [
                    'year' => 2026,
                    'student_number' => '23.240.0011',
                    'name' => 'Farhan Maulana',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2026-02-15 13:00:00',
                ],
                [
                    'year' => 2026,
                    'student_number' => '23.240.0025',
                    'name' => 'Clarissa Aurelia',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2026-02-15 13:20:00',
                ],
                [
                    'year' => 2026,
                    'student_number' => '23.240.0044',
                    'name' => 'Ilham Saputra',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2026-02-15 13:40:00',
                ],
                [
                    'year' => 2026,
                    'student_number' => '23.240.0060',
                    'name' => 'Safira Maharani',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2026-02-15 14:00:00',
                ],
                [
                    'year' => 2026,
                    'student_number' => '23.240.0077',
                    'name' => 'Doni Wahyudi',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2026-02-15 14:20:00',
                ],
                [
                    'year' => 2026,
                    'student_number' => '23.240.0093',
                    'name' => 'Putri Ayu Wandira',
                    'photo' => null,
                    'status' => 1,
                    'created_at' => '2026-02-15 14:40:00',
                ],
            ];

            $members = [];
            foreach ($membersData as $m) {
                $generationId = $generations[$m['year']]->id;
                $member = TVClubMember::updateOrCreate(
                    ['student_number' => $m['student_number']],
                    [
                        'generation_id' => $generationId,
                        'name' => $m['name'],
                        'photo' => $m['photo'],
                        'status' => $m['status'],
                        'created_at' => $m['created_at'],
                        'updated_at' => $m['created_at'],
                    ]
                );
                $members[$m['student_number']] = $member;
            }

            // ----------------------------------------------------
            // 3. FETCH EXISTING PROGRAMS FROM ms_program
            // ----------------------------------------------------
            $programNgopot = Program::where('name', 'like', '%NGOPOT%')->first() ?? Program::first();
            $programPodcast = Program::where('name', 'like', '%PODCAST%')->first() ?? Program::skip(1)->first() ?? $programNgopot;
            $programStmikToday = Program::where('name', 'like', '%TODAY%')->first() ?? Program::skip(2)->first() ?? $programNgopot;
            $programAktual = Program::where('name', 'like', '%AKTUAL%')->first() ?? Program::skip(3)->first() ?? $programNgopot;
            $programInsight = Program::where('name', 'like', '%INSIGHT%')->first() ?? Program::skip(4)->first() ?? $programNgopot;
            $programHealing = Program::where('name', 'like', '%HEALING%')->first() ?? Program::skip(5)->first() ?? $programNgopot;
            $programHarmoni = Program::where('name', 'like', '%HARMONI%')->first() ?? Program::skip(6)->first() ?? $programNgopot;

            // ----------------------------------------------------
            // 4. GROUPS & CREW ASSIGNMENTS (7 Kelompok Produksi)
            // ----------------------------------------------------
            $groupsConfig = [
                // Group 1: NGOPOT (Gen 10 - 2025)
                [
                    'year' => 2025,
                    'program_id' => $programNgopot->id,
                    'name' => 'Tim Produksi Ngopot Gen 10',
                    'description' => 'Tim kreatif dan teknis produksi konten talkshow santai Ngopot bersama narasumber kampus.',
                    'status' => 1,
                    'created_at' => '2025-09-15 10:00:00',
                    'crews' => [
                        ['student_number' => '22.240.0008', 'role' => 'Produser'],
                        ['student_number' => '22.240.0022', 'role' => 'Host / Presenter'],
                        ['student_number' => '22.240.0035', 'role' => 'Kameramen'],
                        ['student_number' => '22.240.0051', 'role' => 'Script Writer'],
                        ['student_number' => '22.240.0074', 'role' => 'Editor'],
                    ],
                ],

                // Group 2: PODCAST PASTI (Gen 10 - 2025)
                [
                    'year' => 2025,
                    'program_id' => $programPodcast->id,
                    'name' => 'Tim Podcast PASTI Gen 10',
                    'description' => 'Kru siaran program audio-visual podcast kampus STMIK Widya Pratama.',
                    'status' => 1,
                    'created_at' => '2025-09-18 11:00:00',
                    'crews' => [
                        ['student_number' => '22.240.0074', 'role' => 'Technical Director'],
                        ['student_number' => '22.240.0089', 'role' => 'Host / Presenter'],
                        ['student_number' => '22.240.0035', 'role' => 'Audio Operator'],
                        ['student_number' => '22.240.0008', 'role' => 'Editor'],
                    ],
                ],

                // Group 3: STMIK TODAY (Gen 11 - 2026)
                [
                    'year' => 2026,
                    'program_id' => $programStmikToday->id,
                    'name' => 'Tim Liputan STMIK Today Gen 11',
                    'description' => 'Tim reportase harian seputar kegiatan, prestasi, dan agenda civitas akademika kampus.',
                    'status' => 1,
                    'created_at' => '2026-03-01 10:00:00',
                    'crews' => [
                        ['student_number' => '23.240.0011', 'role' => 'Produser'],
                        ['student_number' => '23.240.0025', 'role' => 'Host / Presenter'],
                        ['student_number' => '23.240.0044', 'role' => 'Kameramen'],
                        ['student_number' => '23.240.0060', 'role' => 'Script Writer'],
                    ],
                ],

                // Group 4: AKTUAL (Gen 11 - 2026)
                [
                    'year' => 2026,
                    'program_id' => $programAktual->id,
                    'name' => 'Tim Berita Aktual Gen 11',
                    'description' => 'Kru buletin berita mingguan dan isu terkini kampus WPiTV.',
                    'status' => 1,
                    'created_at' => '2026-03-05 13:00:00',
                    'crews' => [
                        ['student_number' => '23.240.0077', 'role' => 'Kameramen'],
                        ['student_number' => '23.240.0093', 'role' => 'Editor'],
                        ['student_number' => '23.240.0011', 'role' => 'Floor Director'],
                        ['student_number' => '23.240.0025', 'role' => 'Host / Presenter'],
                    ],
                ],

                // Group 5: INSIGHT (Gen 9 - 2024)
                [
                    'year' => 2024,
                    'program_id' => $programInsight->id,
                    'name' => 'Tim Produksi Insight Gen 9',
                    'description' => 'Tim dokumenter edukasi, teknologi, dan liputan mendalam.',
                    'status' => 1,
                    'created_at' => '2024-09-20 09:00:00',
                    'crews' => [
                        ['student_number' => '21.240.0015', 'role' => 'Produser'],
                        ['student_number' => '21.240.0028', 'role' => 'Host / Presenter'],
                        ['student_number' => '21.240.0063', 'role' => 'Kameramen'],
                        ['student_number' => '21.240.0097', 'role' => 'Editor'],
                    ],
                ],

                // Group 6: HEALING SKUY (Gen 9 - 2024)
                [
                    'year' => 2024,
                    'program_id' => $programHealing->id,
                    'name' => 'Tim Healing Skuy Gen 9',
                    'description' => 'Kru liputan jalan-jalan, kuliner, dan destinasi wisata Pekalongan sekitarnya.',
                    'status' => 1,
                    'created_at' => '2024-10-01 10:00:00',
                    'crews' => [
                        ['student_number' => '21.240.0097', 'role' => 'Produser'],
                        ['student_number' => '21.240.0028', 'role' => 'Creative & Design'],
                        ['student_number' => '21.240.0041', 'role' => 'Kameramen'],
                        ['student_number' => '21.240.0082', 'role' => 'Editor'],
                    ],
                ],

                // Group 7: HARMONI (Gen 8 - 2023 - Alumni)
                [
                    'year' => 2023,
                    'program_id' => $programHarmoni->id,
                    'name' => 'Tim Harmoni Musik Gen 8',
                    'description' => 'Kru siaran sesi musik akustik dan penampilan band mahasiswa kampus (Arsip).',
                    'status' => 0,
                    'created_at' => '2023-10-01 14:00:00',
                    'crews' => [
                        ['student_number' => '20.240.0012', 'role' => 'Produser'],
                        ['student_number' => '20.240.0034', 'role' => 'Host / Presenter'],
                        ['student_number' => '20.240.0056', 'role' => 'Kameramen'],
                        ['student_number' => '20.240.0090', 'role' => 'Audio Operator'],
                        ['student_number' => '20.240.0102', 'role' => 'Editor'],
                    ],
                ],
            ];

            foreach ($groupsConfig as $gConfig) {
                $generationId = $generations[$gConfig['year']]->id;

                $group = TVClubGroup::updateOrCreate(
                    [
                        'generation_id' => $generationId,
                        'program_id' => $gConfig['program_id'],
                        'name' => $gConfig['name'],
                    ],
                    [
                        'description' => $gConfig['description'],
                        'status' => $gConfig['status'],
                        'created_at' => $gConfig['created_at'],
                        'updated_at' => $gConfig['created_at'],
                    ]
                );

                // Prepare sync data for pivot table tvclub_group_members
                $syncData = [];
                foreach ($gConfig['crews'] as $crew) {
                    $member = $members[$crew['student_number']] ?? null;
                    if ($member && (int) $member->generation_id === (int) $generationId) {
                        $syncData[$member->id] = ['role' => $crew['role']];
                    }
                }

                $group->members()->sync($syncData);
            }
        });
    }
}
