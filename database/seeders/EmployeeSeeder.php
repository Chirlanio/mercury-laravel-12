<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = [
            ['cpf' => '12345678945', 'name' => 'ADMINISTRADOR', 'short_name' => 'ADMIN', 'profile_image' => 'gms.jpg', 'admission_date' => '2020-01-01', 'dismissal_date' => null, 'position_id' => 2, 'site_coupon' => '', 'store_id' => 'Z999', 'education_level_id' => 8, 'gender_id' => 2, 'birth_date' => '2020-01-01', 'area_id' => 12, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Senior', 'status_id' => 2, 'created_at' => '2020-12-04 14:12:09', 'updated_at' => '2025-06-23 16:54:15'],
            ['cpf' => '04608271385', 'name' => 'RAONY GONCALVES DE FREITAS', 'short_name' => 'RAONY FREITAS', 'profile_image' => '', 'admission_date' => '2019-04-15', 'dismissal_date' => '2022-09-02', 'position_id' => 36, 'site_coupon' => '', 'store_id' => 'Z999', 'education_level_id' => 6, 'gender_id' => 2, 'birth_date' => '1991-11-22', 'area_id' => 8, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Junior', 'status_id' => 3, 'created_at' => '2021-01-19 20:35:16', 'updated_at' => '2024-11-19 09:29:06'],
            ['cpf' => '25825690387', 'name' => 'MARLI JANE ANDRADE ASSIS', 'short_name' => 'MARLI ANDRADE', 'profile_image' => null, 'admission_date' => '2005-08-22', 'dismissal_date' => '2008-04-16', 'position_id' => 2, 'site_coupon' => '', 'store_id' => 'Z999', 'education_level_id' => 4, 'gender_id' => 1, 'birth_date' => '1966-01-27', 'area_id' => 12, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Junior', 'status_id' => 3, 'created_at' => '2021-01-22 20:46:27', 'updated_at' => '2025-07-23 16:48:55'],
            ['cpf' => '03513809069', 'name' => 'MEIA SOLA', 'short_name' => 'MEIA SOLA', 'profile_image' => '', 'admission_date' => '2020-01-01', 'dismissal_date' => null, 'position_id' => 7, 'site_coupon' => '', 'store_id' => 'Z441', 'education_level_id' => 4, 'gender_id' => 2, 'birth_date' => '2020-01-01', 'area_id' => 12, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Junior', 'status_id' => 2, 'created_at' => '2021-01-22 20:47:01', 'updated_at' => '2025-05-30 10:35:13'],
            ['cpf' => '81873280378', 'name' => 'RÔMULO CESAR PINHEIRO COSTA', 'short_name' => 'RÔMULO CESAR', 'profile_image' => '', 'admission_date' => '2019-11-25', 'dismissal_date' => null, 'position_id' => 34, 'site_coupon' => '', 'store_id' => 'Z999', 'education_level_id' => 6, 'gender_id' => 2, 'birth_date' => '1980-09-06', 'area_id' => 9, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Junior', 'status_id' => 2, 'created_at' => '2021-01-22 20:47:49', 'updated_at' => '2024-11-13 16:37:03'],
            ['cpf' => '61643483315', 'name' => 'ISABEL CRISTINA CASTRO VALENTE', 'short_name' => 'ISABEL CRISTINA', 'profile_image' => null, 'admission_date' => '2019-05-17', 'dismissal_date' => '2022-07-14', 'position_id' => 3, 'site_coupon' => null, 'store_id' => 'Z423', 'education_level_id' => 4, 'gender_id' => 1, 'birth_date' => '1979-07-04', 'area_id' => 10, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Junior', 'status_id' => 3, 'created_at' => '2021-01-22 20:48:22', 'updated_at' => '2024-11-19 09:31:12'],
            ['cpf' => '64594009387', 'name' => 'FLAVIANA CAMPOS DE ALMEIDA', 'short_name' => 'FLAVIANA CAMPOS', 'profile_image' => '', 'admission_date' => '2012-07-09', 'dismissal_date' => null, 'position_id' => 1, 'site_coupon' => 'MSFLAVIANA', 'store_id' => 'Z429', 'education_level_id' => 6, 'gender_id' => 1, 'birth_date' => '1980-02-05', 'area_id' => 10, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Senior', 'status_id' => 2, 'created_at' => '2021-01-22 20:49:05', 'updated_at' => '2025-05-05 14:23:07'],
            ['cpf' => '04722989338', 'name' => 'LUAN OTAVIANO ALVES', 'short_name' => 'LUAN OTAVIANO', 'profile_image' => null, 'admission_date' => '2012-10-15', 'dismissal_date' => '2014-05-26', 'position_id' => 4, 'site_coupon' => null, 'store_id' => 'Z429', 'education_level_id' => 4, 'gender_id' => 2, 'birth_date' => '1991-10-23', 'area_id' => 10, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Junior', 'status_id' => 3, 'created_at' => '2021-01-22 20:49:39', 'updated_at' => '2024-11-19 09:33:39'],
            ['cpf' => '73274267368', 'name' => 'ELIANA ARAUJO DAS CHAGAS', 'short_name' => 'ELIANA ARAUJO', 'profile_image' => null, 'admission_date' => '2004-07-16', 'dismissal_date' => '2021-01-20', 'position_id' => 1, 'site_coupon' => 'MSELIANA', 'store_id' => 'Z429', 'education_level_id' => 4, 'gender_id' => 1, 'birth_date' => '1974-05-29', 'area_id' => 10, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Junior', 'status_id' => 3, 'created_at' => '2021-01-22 20:50:16', 'updated_at' => '2024-09-19 08:38:38'],
            ['cpf' => '04326104309', 'name' => 'RHAIANY DE BRITO SILVEIRA', 'short_name' => 'RHAIANY DE BRITO', 'profile_image' => '', 'admission_date' => '2018-04-13', 'dismissal_date' => null, 'position_id' => 1, 'site_coupon' => 'MSRHAIANY', 'store_id' => 'Z430', 'education_level_id' => 4, 'gender_id' => 1, 'birth_date' => '1990-11-29', 'area_id' => 10, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Senior', 'status_id' => 2, 'created_at' => '2021-01-22 20:50:58', 'updated_at' => '2025-05-05 14:38:23'],
            ['cpf' => '92360424300', 'name' => 'SAMARAH CAULA DE MENDONÇA VIANA', 'short_name' => 'SAMARAH CAULA', 'profile_image' => '', 'admission_date' => '2005-08-17', 'dismissal_date' => null, 'position_id' => 1, 'site_coupon' => 'MSSAMARAH', 'store_id' => 'Z429', 'education_level_id' => 4, 'gender_id' => 1, 'birth_date' => '1981-02-23', 'area_id' => 10, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Senior', 'status_id' => 2, 'created_at' => '2021-01-22 20:51:41', 'updated_at' => '2025-05-05 14:23:24'],
            ['cpf' => '05247957385', 'name' => 'MAYARA BEATRIZ OLIVEIRA CAMPOS', 'short_name' => 'MAYARA BEATRIZ', 'profile_image' => null, 'admission_date' => '2012-12-18', 'dismissal_date' => '2019-03-17', 'position_id' => 6, 'site_coupon' => 'MSMAYARA', 'store_id' => 'Z429', 'education_level_id' => 4, 'gender_id' => 1, 'birth_date' => '1993-01-09', 'area_id' => 10, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Junior', 'status_id' => 3, 'created_at' => '2021-01-22 20:52:15', 'updated_at' => '2024-11-19 09:35:14'],
            ['cpf' => '00960117342', 'name' => 'RAFAELA TAVARES DE SOUSA', 'short_name' => 'RAFAELA TAVARES', 'profile_image' => '', 'admission_date' => '2014-04-11', 'dismissal_date' => null, 'position_id' => 1, 'site_coupon' => 'MSRAFAELA', 'store_id' => 'Z429', 'education_level_id' => 4, 'gender_id' => 1, 'birth_date' => '1985-05-31', 'area_id' => 10, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Pleno', 'status_id' => 2, 'created_at' => '2021-01-22 20:52:53', 'updated_at' => '2025-05-05 14:22:06'],
            ['cpf' => '80809111349', 'name' => 'JANAINA DA SILVA', 'short_name' => 'JANAINA DA SILVA', 'profile_image' => '', 'admission_date' => '2019-06-14', 'dismissal_date' => null, 'position_id' => 1, 'site_coupon' => 'MSJANAINA', 'store_id' => 'Z429', 'education_level_id' => 4, 'gender_id' => 1, 'birth_date' => '1978-07-13', 'area_id' => 10, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Pleno', 'status_id' => 2, 'created_at' => '2021-01-22 20:53:48', 'updated_at' => '2025-05-05 14:22:36'],
            ['cpf' => '05365363337', 'name' => 'CAMILA SOUSA DO NASCIMENTO', 'short_name' => 'CAMILA SOUSA', 'profile_image' => null, 'admission_date' => '2018-04-09', 'dismissal_date' => '2020-01-06', 'position_id' => 3, 'site_coupon' => 'MSCAMILA', 'store_id' => 'Z429', 'education_level_id' => 6, 'gender_id' => 1, 'birth_date' => '1993-04-25', 'area_id' => 10, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Junior', 'status_id' => 3, 'created_at' => '2021-01-22 20:54:15', 'updated_at' => '2024-11-19 09:37:48'],
            ['cpf' => '26633957300', 'name' => 'FRANCISCA IRENE PIMENTEL VITORINO', 'short_name' => 'IRENE PIMENTEL', 'profile_image' => null, 'admission_date' => '2003-09-10', 'dismissal_date' => null, 'position_id' => 23, 'site_coupon' => 'MSIRENE', 'store_id' => 'Z429', 'education_level_id' => 4, 'gender_id' => 1, 'birth_date' => '1960-01-24', 'area_id' => 10, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Junior', 'status_id' => 2, 'created_at' => '2021-01-22 20:54:47', 'updated_at' => '2024-09-19 08:17:13'],
            ['cpf' => '46428313391', 'name' => 'MARIA DAS GRAÇAS MONTEIRO DOS SANTOS', 'short_name' => 'GRAÇAS MONTEIRO', 'profile_image' => '', 'admission_date' => '2009-09-01', 'dismissal_date' => null, 'position_id' => 1, 'site_coupon' => 'MSGRACA', 'store_id' => 'Z429', 'education_level_id' => 4, 'gender_id' => 1, 'birth_date' => '1971-11-29', 'area_id' => 10, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Senior', 'status_id' => 2, 'created_at' => '2021-01-22 20:55:30', 'updated_at' => '2025-05-05 14:22:52'],
            ['cpf' => '66115124387', 'name' => 'ROSEANE DE VASCONCELOS LOPES', 'short_name' => 'ROSE LOPES', 'profile_image' => null, 'admission_date' => '2016-04-11', 'dismissal_date' => '2020-02-29', 'position_id' => 1, 'site_coupon' => null, 'store_id' => 'Z433', 'education_level_id' => 4, 'gender_id' => 1, 'birth_date' => '1982-12-23', 'area_id' => 10, 'is_pcd' => false, 'is_apprentice' => false, 'level' => 'Junior', 'status_id' => 3, 'created_at' => '2021-01-22 20:56:25', 'updated_at' => '2024-11-19 09:39:17'],
        ];

        foreach ($employees as $employee) {
            DB::table('employees')->updateOrInsert(
                ['cpf' => $employee['cpf']],
                [
                    'name' => $employee['name'],
                    'short_name' => $employee['short_name'],
                    'profile_image' => $employee['profile_image'],
                    'admission_date' => $employee['admission_date'],
                    'dismissal_date' => $employee['dismissal_date'],
                    'position_id' => $employee['position_id'],
                    'site_coupon' => $employee['site_coupon'],
                    'store_id' => $employee['store_id'],
                    'education_level_id' => $employee['education_level_id'],
                    'gender_id' => $employee['gender_id'],
                    'birth_date' => $employee['birth_date'],
                    'area_id' => $employee['area_id'],
                    'is_pcd' => $employee['is_pcd'],
                    'is_apprentice' => $employee['is_apprentice'],
                    'level' => $employee['level'],
                    'status_id' => $employee['status_id'],
                    'created_at' => Carbon::parse($employee['created_at']),
                    'updated_at' => $employee['updated_at'] ? Carbon::parse($employee['updated_at']) : null,
                ]
            );
        }
    }
}
