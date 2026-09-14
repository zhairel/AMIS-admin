<?php

namespace Database\Seeders;

use App\Models\StaffVerification;
use Illuminate\Database\Seeder;

class StaffVerificationSeeder extends Seeder
{
    public function run(): void
    {
        $officials = [
            [
                'slug' => 'cabel-nurhasan',
                'full_name' => 'Cabel Balan Nurhasan',
                'academic_title' => 'Ph.D',
                'position' => 'Director',
                'department' => 'Executive Administration',
                'official_email' => 'almunawwaraislamicschool@gmail.com',
                'phone_number' => '+63927-299-1833',
                'office_address' => 'Don Julian Rodriguez Sr. Avenue, Ma-A, Davao City, 8,000, Philippines',
                'badge_title' => 'Verified AMIS Official',
                'official_website' => 'https://amis.edu.ph',
                'status' => 'active',
                'bio_notes' => 'School Director of Al Munawwara Islamic School.',
                'verified_since' => '2020-01-01 00:00:00',
            ],
            [
                'slug' => 'wardah-pindaton',
                'full_name' => 'Ms. Wardah D. Pindaton',
                'academic_title' => 'LPT, MMEM',
                'position' => 'School Principal',
                'department' => 'Academic Leadership',
                'official_email' => 'wpindaton.amis@gmail.com',
                'phone_number' => '+63967-249-1585',
                'office_address' => 'Don Julian Rodriguez Sr. Avenue, Ma-A, Davao City, 8,000, Philippines',
                'badge_title' => 'Verified AMIS Official',
                'official_website' => 'https://amis.edu.ph',
                'status' => 'active',
                'bio_notes' => 'School Principal of Al Munawwara Islamic School.',
                'verified_since' => '2021-06-01 00:00:00',
            ],
        ];

        foreach ($officials as $official) {
            StaffVerification::updateOrCreate(
                ['slug' => $official['slug']],
                $official
            );
        }
    }
}
