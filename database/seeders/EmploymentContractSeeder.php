<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmploymentContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contracts = [
            ['id' => 1, 'employee_id' => 197, 'position_id' => 63, 'movement_type_id' => 1, 'start_date' => '2016-10-24', 'end_date' => '2023-09-30', 'store_id' => 'Z999', 'created_at' => '2024-08-23 08:20:23', 'updated_at' => '2024-11-14 09:31:25'],
            ['id' => 2, 'employee_id' => 635, 'position_id' => 63, 'movement_type_id' => 1, 'start_date' => '2022-11-03', 'end_date' => '2023-09-30', 'store_id' => 'Z999', 'created_at' => '2024-08-23 08:24:39', 'updated_at' => '2024-08-23 08:32:55'],
            ['id' => 3, 'employee_id' => 635, 'position_id' => 56, 'movement_type_id' => 3, 'start_date' => '2023-10-01', 'end_date' => '2024-08-31', 'store_id' => 'Z999', 'created_at' => '2024-08-23 08:25:57', 'updated_at' => '2024-08-28 11:36:32'],
            ['id' => 4, 'employee_id' => 635, 'position_id' => 83, 'movement_type_id' => 3, 'start_date' => '2024-09-01', 'end_date' => '2025-06-13', 'store_id' => 'Z999', 'created_at' => '2024-08-28 11:32:54', 'updated_at' => '2025-09-05 11:16:58'],
            ['id' => 5, 'employee_id' => 1, 'position_id' => 2, 'movement_type_id' => 1, 'start_date' => '2020-01-01', 'end_date' => null, 'store_id' => 'Z999', 'created_at' => '2024-08-28 12:28:34', 'updated_at' => null],
            ['id' => 6, 'employee_id' => 808, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2024-08-08', 'end_date' => '2024-11-05', 'store_id' => 'Z422', 'created_at' => '2024-08-28 17:02:50', 'updated_at' => '2025-06-17 08:54:50'],
            ['id' => 7, 'employee_id' => 809, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2022-09-12', 'end_date' => '2022-12-07', 'store_id' => 'Z440', 'created_at' => '2024-08-29 11:11:51', 'updated_at' => null],
            ['id' => 8, 'employee_id' => 810, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2022-09-02', 'end_date' => '2022-11-29', 'store_id' => 'Z438', 'created_at' => '2024-08-29 11:14:15', 'updated_at' => null],
            ['id' => 9, 'employee_id' => 811, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2024-07-25', 'end_date' => '2024-09-08', 'store_id' => 'Z428', 'created_at' => '2024-08-29 11:16:09', 'updated_at' => '2024-11-19 15:00:19'],
            ['id' => 10, 'employee_id' => 812, 'position_id' => 3, 'movement_type_id' => 1, 'start_date' => '2022-11-01', 'end_date' => '2022-12-31', 'store_id' => 'Z429', 'created_at' => '2024-08-29 11:18:40', 'updated_at' => null],
            ['id' => 11, 'employee_id' => 813, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2023-02-01', 'end_date' => '2023-09-04', 'store_id' => 'Z421', 'created_at' => '2024-08-29 11:22:13', 'updated_at' => null],
            ['id' => 12, 'employee_id' => 814, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2023-01-21', 'end_date' => '2023-07-16', 'store_id' => 'Z438', 'created_at' => '2024-08-29 11:24:08', 'updated_at' => null],
            ['id' => 13, 'employee_id' => 815, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2022-12-24', 'end_date' => '2023-01-07', 'store_id' => 'Z431', 'created_at' => '2024-08-29 11:26:03', 'updated_at' => null],
            ['id' => 14, 'employee_id' => 816, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2022-10-13', 'end_date' => '2023-01-01', 'store_id' => 'Z426', 'created_at' => '2024-08-29 11:27:52', 'updated_at' => null],
            ['id' => 15, 'employee_id' => 817, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2023-04-18', 'end_date' => '2023-05-28', 'store_id' => 'Z433', 'created_at' => '2024-08-29 11:30:34', 'updated_at' => null],
            ['id' => 16, 'employee_id' => 818, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2023-10-26', 'end_date' => '2023-11-26', 'store_id' => 'Z422', 'created_at' => '2024-08-29 11:32:44', 'updated_at' => null],
            ['id' => 17, 'employee_id' => 819, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2022-09-28', 'end_date' => '2022-10-03', 'store_id' => 'Z434', 'created_at' => '2024-08-29 11:34:09', 'updated_at' => null],
            ['id' => 18, 'employee_id' => 820, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2022-09-08', 'end_date' => '2023-10-16', 'store_id' => 'Z430', 'created_at' => '2024-08-29 11:38:41', 'updated_at' => null],
            ['id' => 19, 'employee_id' => 821, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2022-12-15', 'end_date' => '2023-01-28', 'store_id' => 'Z430', 'created_at' => '2024-08-29 11:50:54', 'updated_at' => null],
            ['id' => 20, 'employee_id' => 822, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2022-10-22', 'end_date' => '2023-01-01', 'store_id' => 'Z426', 'created_at' => '2024-08-29 11:52:41', 'updated_at' => null],
            ['id' => 21, 'employee_id' => 823, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2023-03-10', 'end_date' => '2024-03-30', 'store_id' => 'Z438', 'created_at' => '2024-08-29 12:00:08', 'updated_at' => null],
            ['id' => 22, 'employee_id' => 725, 'position_id' => 78, 'movement_type_id' => 1, 'start_date' => '2024-03-20', 'end_date' => '2024-07-31', 'store_id' => 'Z429', 'created_at' => '2024-08-29 12:02:30', 'updated_at' => '2024-08-29 12:03:24'],
            ['id' => 23, 'employee_id' => 725, 'position_id' => 78, 'movement_type_id' => 4, 'start_date' => '2024-08-01', 'end_date' => '2024-09-09', 'store_id' => 'Z432', 'created_at' => '2024-08-29 12:03:10', 'updated_at' => '2024-11-19 14:58:17'],
            ['id' => 24, 'employee_id' => 824, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2022-12-15', 'end_date' => '2022-12-21', 'store_id' => 'Z426', 'created_at' => '2024-08-29 12:17:00', 'updated_at' => null],
            ['id' => 25, 'employee_id' => 825, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2022-09-27', 'end_date' => '2022-12-29', 'store_id' => 'Z428', 'created_at' => '2024-08-29 12:20:26', 'updated_at' => null],
            ['id' => 26, 'employee_id' => 826, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2022-09-19', 'end_date' => '2023-01-09', 'store_id' => 'Z423', 'created_at' => '2024-08-29 12:23:20', 'updated_at' => null],
            ['id' => 27, 'employee_id' => 827, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2024-07-30', 'end_date' => '2024-08-31', 'store_id' => 'Z439', 'created_at' => '2024-08-30 21:13:20', 'updated_at' => '2024-11-19 15:00:56'],
            ['id' => 28, 'employee_id' => 828, 'position_id' => 4, 'movement_type_id' => 1, 'start_date' => '2024-08-27', 'end_date' => '2024-09-06', 'store_id' => 'Z432', 'created_at' => '2024-09-01 00:49:30', 'updated_at' => '2024-11-19 15:01:41'],
            ['id' => 29, 'employee_id' => 829, 'position_id' => 3, 'movement_type_id' => 1, 'start_date' => '2024-08-20', 'end_date' => '2024-11-16', 'store_id' => 'Z440', 'created_at' => '2024-09-01 01:34:36', 'updated_at' => '2025-06-17 08:56:10'],
            ['id' => 30, 'employee_id' => 830, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2024-08-17', 'end_date' => '2025-01-03', 'store_id' => 'Z436', 'created_at' => '2024-09-01 01:44:14', 'updated_at' => '2025-06-17 08:57:29'],
            ['id' => 31, 'employee_id' => 831, 'position_id' => 4, 'movement_type_id' => 1, 'start_date' => '2024-08-27', 'end_date' => '2024-09-04', 'store_id' => 'Z422', 'created_at' => '2024-09-01 01:56:38', 'updated_at' => '2024-11-19 15:02:27'],
            ['id' => 32, 'employee_id' => 832, 'position_id' => 4, 'movement_type_id' => 1, 'start_date' => '2024-08-27', 'end_date' => null, 'store_id' => 'Z425', 'created_at' => '2024-09-01 02:04:14', 'updated_at' => null],
            ['id' => 33, 'employee_id' => 833, 'position_id' => 4, 'movement_type_id' => 1, 'start_date' => '2024-08-06', 'end_date' => null, 'store_id' => 'Z425', 'created_at' => '2024-09-01 02:06:30', 'updated_at' => null],
            ['id' => 34, 'employee_id' => 834, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2022-06-06', 'end_date' => '2023-05-15', 'store_id' => 'Z433', 'created_at' => '2024-09-02 17:29:58', 'updated_at' => null],
            ['id' => 35, 'employee_id' => 835, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2024-09-14', 'end_date' => '2024-11-01', 'store_id' => 'Z424', 'created_at' => '2024-09-16 13:14:39', 'updated_at' => '2025-06-17 08:58:47'],
            ['id' => 36, 'employee_id' => 624, 'position_id' => 1, 'movement_type_id' => 1, 'start_date' => '2023-12-05', 'end_date' => '2024-09-09', 'store_id' => 'Z437', 'created_at' => '2024-09-17 13:02:46', 'updated_at' => '2024-09-17 13:03:55'],
            ['id' => 37, 'employee_id' => 624, 'position_id' => 1, 'movement_type_id' => 4, 'start_date' => '2024-09-10', 'end_date' => null, 'store_id' => 'Z439', 'created_at' => '2024-09-17 13:03:17', 'updated_at' => null],
            ['id' => 38, 'employee_id' => 836, 'position_id' => 3, 'movement_type_id' => 1, 'start_date' => '1991-10-01', 'end_date' => '2016-03-11', 'store_id' => 'Z429', 'created_at' => '2024-09-17 15:54:44', 'updated_at' => null],
            ['id' => 39, 'employee_id' => 837, 'position_id' => 84, 'movement_type_id' => 1, 'start_date' => '1998-03-02', 'end_date' => '2006-01-31', 'store_id' => 'Z999', 'created_at' => '2024-09-17 16:10:18', 'updated_at' => null],
            ['id' => 40, 'employee_id' => 838, 'position_id' => 69, 'movement_type_id' => 1, 'start_date' => '2001-06-01', 'end_date' => '2005-10-21', 'store_id' => 'Z999', 'created_at' => '2024-09-17 16:12:20', 'updated_at' => null],
            ['id' => 41, 'employee_id' => 839, 'position_id' => 35, 'movement_type_id' => 1, 'start_date' => '2001-08-01', 'end_date' => '2005-08-31', 'store_id' => 'Z999', 'created_at' => '2024-09-17 16:15:10', 'updated_at' => null],
            ['id' => 42, 'employee_id' => 40, 'position_id' => 4, 'movement_type_id' => 1, 'start_date' => '2001-11-01', 'end_date' => null, 'store_id' => 'Z424', 'created_at' => '2024-09-17 16:16:39', 'updated_at' => null],
            ['id' => 43, 'employee_id' => 840, 'position_id' => 85, 'movement_type_id' => 1, 'start_date' => '2002-11-01', 'end_date' => '2005-08-02', 'store_id' => 'Z999', 'created_at' => '2024-09-17 16:21:06', 'updated_at' => null],
            ['id' => 44, 'employee_id' => 196, 'position_id' => 84, 'movement_type_id' => 1, 'start_date' => '2003-01-02', 'end_date' => '2005-01-15', 'store_id' => 'Z999', 'created_at' => '2024-09-17 16:50:11', 'updated_at' => null],
            ['id' => 45, 'employee_id' => 196, 'position_id' => 58, 'movement_type_id' => 3, 'start_date' => '2005-08-22', 'end_date' => null, 'store_id' => 'Z442', 'created_at' => '2024-09-17 16:51:12', 'updated_at' => null],
            ['id' => 46, 'employee_id' => 841, 'position_id' => 29, 'movement_type_id' => 1, 'start_date' => '2003-02-01', 'end_date' => '2005-10-21', 'store_id' => 'Z999', 'created_at' => '2024-09-17 17:50:51', 'updated_at' => null],
        ];

        foreach ($contracts as $contract) {
            DB::table('employment_contracts')->updateOrInsert(
                ['id' => $contract['id']],
                $contract
            );
        }
    }
}
