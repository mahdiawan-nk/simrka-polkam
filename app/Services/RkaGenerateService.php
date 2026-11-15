<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Department;
use App\Models\RkaGenerateConfig;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaSchedule;
use Illuminate\Support\Facades\DB;
use Exception;

class RkaGenerateService
{
    /**
     * Jalankan proses auto-generate RKA.
     *
     * @param RkaSchedule|null $schedule
     * @param RkaGenerateConfig|null $config
     * @return array
     */
    public function generate(?RkaSchedule $schedule = null, ?RkaGenerateConfig $config = null): array
    {
        // 1. Ambil Jadwal jika tidak dipassing (ambil jadwal aktif terdekat)
        $schedule = $schedule ?? RkaSchedule::where('is_active', true)->latest()->first();

        if (!$schedule) {
            throw new Exception('Jadwal RKA (RkaSchedule) tidak ditemukan atau tidak ada jadwal aktif.');
        }

        // 2. Ambil konfigurasi aktif jika tidak dipassing
        $config = $config ?? RkaGenerateConfig::where('is_active', true)->first();

        if (!$config) {
            throw new Exception('Tidak ada konfigurasi RKA Generate yang aktif.');
        }

        if (!$config->rka_setting_id) {
            throw new Exception('Setting RKA belum dipilih pada konfigurasi.');
        }

        $departmentIds = $config->selected_department_ids ?? [];
        $templateIndicators = $config->template_indicators ?? [];

        if (empty($departmentIds)) {
            throw new Exception('Tidak ada departemen/unit yang dipilih pada konfigurasi.');
        }

        $processedHeadersCount = 0;
        $createdIndicatorsCount = 0;

        DB::beginTransaction();

        try {
            foreach ($departmentIds as $departmentId) {
                $department = Department::find($departmentId);
                if (!$department) {
                    continue;
                }

                // 3. Create or Find RKA Header berdasarkan schedule_id & department_id
                $rkaHeader = RkaHeader::firstOrCreate(
                    [
                        'schedule_id'   => $schedule->id,
                        'department_id' => $department->id,
                    ],
                    [
                        'setting_id'   => $config->rka_setting_id,
                        'status'       => 'DRAFT',
                        'pagu_budget'  => 0.00,
                        'is_locked'    => false,
                    ]
                );

                $processedHeadersCount++;

                // 4. Generate RKA Indicators
                // 4. Generate RKA Indicators
                foreach ($templateIndicators as $indicatorConfig) {
                    if (empty($indicatorConfig['is_active'])) {
                        continue;
                    }

                    $accountCode = $indicatorConfig['account_code'] ?? null;
                    if (!$accountCode) {
                        continue;
                    }

                    $account = Account::where('code', $accountCode)->first();
                    if (!$account) {
                        continue;
                    }

                    $programName  = $indicatorConfig['program_name'] ?? '-';
                    $activityName = $indicatorConfig['activity_name'] ?? '-';
                    $matrixList   = $indicatorConfig['matrix'] ?? [];

                    // Remap key dari konfigurasi ke struktur atribut indicator_matrix
                    $processedMatrix = array_map(function ($row) use ($department) {
                        $benchmark = $row['benchmark'] ?? '-';
                        $benchmark = str_replace(':department_name', $department->name, $benchmark);

                        return [
                            'indicator_category'   => $row['indicator'] ?? '-',
                            'performance_benchmark' => $benchmark,
                            'target_value'         => $row['target'] ?? '-',
                            'unit_target'           => $row['unit'] ?? ($row['unit_target'] ?? '-'),
                        ];
                    }, $matrixList);

                    // Buat atau Update record RkaIndicator (1 record per RkaHeader & Account)
                    RkaIndicator::updateOrCreate(
                        [
                            'rka_header_id' => $rkaHeader->id,
                            'account_id'    => $account->id,
                        ],
                        [
                            'program_name'     => $programName,
                            'activity_name'    => $activityName,
                            'location'         => 'Politeknik Kampar',
                            'indicator_matrix' => $processedMatrix, // Tersimpan rapi sebagai JSON dengan key baru
                        ]
                    );

                    $createdIndicatorsCount++;
                }
            }

            DB::commit();

            return [
                'status'     => true,
                'message'    => 'Proses Auto Generate RKA berhasil dijalankan.',
                'headers'    => $processedHeadersCount,
                'indicators' => $createdIndicatorsCount,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
