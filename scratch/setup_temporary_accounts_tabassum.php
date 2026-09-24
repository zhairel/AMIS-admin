<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AdminAuditLog;
use App\Models\EnrollmentApplicant;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSection;
use App\Models\User;
use App\Services\Microsoft\MicrosoftGraphAuthService;
use App\Services\MicrosoftGraphService;
use App\Services\SoaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

echo "========================================================\n";
echo "  SETTING UP TEMPORARY ACCOUNTS: YOUSOF & AYESHA\n";
echo "========================================================\n\n";

$graphService = new MicrosoftGraphService();
$authService = app(MicrosoftGraphAuthService::class);
$graphToken = $authService->accessToken();
$studentSkuId = (string) config('services.microsoft.student_sku_id');

// Configuration for both students
$accounts = [
    '261217' => [
        'type'            => 'Yousof',
        'first_name'      => 'YOUSOF',
        'middle_name'     => 'SAADUDDIN',
        'last_name'       => 'MUHAMMAD TABASSUM [TEMPORARY ACC]',
        'full_name'       => 'YOUSOF S. MUHAMMAD TABASSUM [TEMPORARY ACC]',
        'm365_display'    => 'YOUSOF S. MUHAMMAD TABASSUM [TEMPORARY ACC]',
        'given_name'      => 'YOUSOF',
        'surname'         => 'MUHAMMAD TABASSUM [TEMPORARY ACC]',
        'dob'             => '2020-02-20',
        'lrn'             => 'NA',
        'grade_level'     => 'Kinder 2',
        'section_name'    => 'ABU BAKR AS-SIDEEQ',
        'section_id'      => 98,
        'mother_first'    => 'RAIHANIE',
        'mother_middle'   => 'IBRAHIM',
        'mother_last'     => 'SAADUDDIN',
        'father_first'    => 'MUHAMMAD',
        'father_middle'   => 'YASIN',
        'father_last'     => 'TABASSUM',
        'email'           => 'raibintsaad@gmail.com',
        'address'         => 'Al Nahda 1 Dubai UAE',
    ],
    '261216' => [
        'type'            => 'Ayesha',
        'first_name'      => 'AYESHA LAYAL',
        'middle_name'     => 'SAADUDDIN',
        'last_name'       => 'BINT MUHAMMAD [TEMPORARY ACC]',
        'full_name'       => 'AYESHA LAYAL S. BINT MUHAMMAD [TEMPORARY ACC]',
        'm365_display'    => 'AYESHA LAYAL S. BINT MUHAMMAD [TEMPORARY ACC]',
        'given_name'      => 'AYESHA LAYAL',
        'surname'         => 'BINT MUHAMMAD [TEMPORARY ACC]',
        'dob'             => '2018-01-09',
        'lrn'             => '466150230035',
        'grade_level'     => 'Grade 2',
        'section_name'    => 'AASIM IBN THABIT',
        'section_id'      => 139,
        'mother_first'    => 'RAIHANIE',
        'mother_middle'   => 'IBRAHIM',
        'mother_last'     => 'SAADUDDIN',
        'father_first'    => 'MUHAMMAD',
        'father_middle'   => 'YASIN',
        'father_last'     => 'TABASSUM',
        'email'           => 'raibintsaad@gmail.com',
        'address'         => 'Al Nahda 1 Dubai UAE',
    ],
];

foreach ($accounts as $studentNumber => $data) {
    echo "--------------------------------------------------------\n";
    echo "Processing Student #{$studentNumber} ({$data['type']})...\n";

    $student = Student::where('student_number', $studentNumber)->first();
    if (!$student) {
        echo "[ERROR] Student #{$studentNumber} not found!\n";
        continue;
    }

    $applicant = $student->applicant;
    $user = $student->user;
    $msUserId = $student->ms_user_id;

    DB::beginTransaction();
    try {
        // 1. Update Enrollment Applicant
        if ($applicant) {
            $applicant->update([
                'first_name'          => $data['first_name'],
                'middle_name'         => $data['middle_name'],
                'last_name'           => $data['last_name'],
                'date_of_birth'       => $data['dob'],
                'lrn'                 => $data['lrn'],
                'mother_first_name'   => $data['mother_first'],
                'mother_middle_name'  => $data['mother_middle'],
                'mother_last_name'    => $data['mother_last'],
                'father_first_name'   => $data['father_first'],
                'father_middle_name'  => $data['father_middle'],
                'father_last_name'    => $data['father_last'],
                'address'             => $data['address'],
                'street_address'      => $data['address'],
                'home_address'        => $data['address'],
                'email'               => $data['email'],
                'parent_email'        => $data['email'],
                'status'              => 'approved',
            ]);
            echo "  Updated EnrollmentApplicant ID: {$applicant->id}\n";
        }

        // 2. Update Student User Account
        if ($user) {
            $user->update([
                'name' => $data['full_name'],
            ]);
            echo "  Updated Student User ID: {$user->id} -> {$data['full_name']}\n";
        }

        // 3. Assign Section to Student
        $student->update([
            'section'                => $data['section_name'],
            'ms_license_active'      => 1,
            'ms_license_assigned_at' => now(),
            'ms_account_enabled'     => 1,
        ]);

        StudentSection::updateOrCreate(
            ['student_id' => $student->id, 'section_id' => $data['section_id']],
            [
                'ms_status'      => 'enrolled',
                'ms_enrolled_at' => now(),
            ]
        );
        echo "  Assigned Section: {$data['section_name']} (ID: {$data['section_id']})\n";

        // 4. Update Microsoft 365 Entra ID / Teams App Account
        if ($msUserId) {
            // Assign student license if not already active
            if ($studentSkuId) {
                try {
                    $graphService->assignLicense($msUserId, [$studentSkuId], []);
                    echo "  Verified/Assigned M365 Student License ({$studentSkuId})\n";
                } catch (\Exception $licEx) {
                    echo "  License note: " . $licEx->getMessage() . "\n";
                }
            }

            // Update Teams / Entra ID Display Name
            $patchResponse = Http::withToken($graphToken)
                ->patch("https://graph.microsoft.com/v1.0/users/{$msUserId}", [
                    'displayName' => $data['m365_display'],
                    'givenName'   => $data['given_name'],
                    'surname'     => $data['surname'],
                ]);

            if ($patchResponse->successful() || $patchResponse->status() === 204) {
                echo "  Updated Microsoft Teams App Display Name: {$data['m365_display']}\n";
            } else {
                echo "  [WARNING] Graph patch returned: " . $patchResponse->body() . "\n";
            }
        }

        // 5. Generate SOA Ledger if missing
        if (!$student->account) {
            try {
                (new SoaService())->generate($student, $applicant);
                echo "  Generated Statement of Account (SOA).\n";
            } catch (\Throwable $soaEx) {
                echo "  SOA note: " . $soaEx->getMessage() . "\n";
            }
        }

        AdminAuditLog::record(
            'temporary_student_tagged',
            true,
            "Configured temporary account with [TEMPORARY ACC] tag and Teams app for student #{$studentNumber} ({$data['full_name']}).",
            [
                'student_id'     => $student->id,
                'student_number' => $studentNumber,
                'full_name'      => $data['full_name'],
                'm365_display'   => $data['m365_display'],
                'section'        => $data['section_name'],
                'lrn'            => $data['lrn'],
            ]
        );

        DB::commit();
        echo "[SUCCESS] Student #{$studentNumber} setup completed successfully!\n\n";

    } catch (\Throwable $e) {
        DB::rollBack();
        echo "[FAILED] Student #{$studentNumber}: " . $e->getMessage() . "\n\n";
    }
}

echo "========================================================\n";
echo "  DONE!\n";
echo "========================================================\n";
