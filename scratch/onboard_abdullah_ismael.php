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
use App\Services\MicrosoftGraphService;
use App\Services\SoaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

echo "========================================================\n";
echo "  ONBOARDING ABDULLAH GAMBOA ISMAEL (TABUK, KSA)\n";
echo "========================================================\n\n";

$firstName = 'ABDULLAH';
$middleName = 'GAMBOA';
$middleInitial = 'G.';
$rawLastName = 'ISMAEL';
$taggedLastName = 'ISMAEL [TEMPORARY ACC]';
$localDisplayName = "{$firstName} {$middleInitial} {$taggedLastName}";
$m365DisplayName = "{$firstName} {$middleInitial} {$taggedLastName}";

$gender = 'Male';
$gradeLevel = 'Kinder 2';
$dob = '2021-09-30';
$tempPassword = 'Sep302021@';

$sectionId = 100;
$sectionName = "ABDULLAH IBN MAS'UD";

$parentEmail = 'windle.gamboa@yahoo.com';
$motherFirstName = 'WINDLE';
$motherMiddleName = 'N.';
$motherLastName = 'GAMBOA';
$fatherFirstName = 'MUSTAPHA';
$fatherMiddleName = 'M.';
$fatherLastName = 'ISMAEL JR.';
$address = '3341 Muawiyah ibn Abi Sufyan As Saadah Tabuk KSA';
$city = 'Tabuk';
$country = 'Saudi Arabia';
$schoolYear = '2026-2027';
$learningMode = 'Flexible Online Learning - 2nd Shift';

// Calculate student number
$maxStudentNum = (int) Student::where('student_number', 'like', '26%')
    ->where('student_number', 'not like', '269%')
    ->max('student_number');
$studentNumber = (string) ($maxStudentNum + 1);

$mailNickname = $studentNumber . 'iabdullah';
$schoolEmail = $mailNickname . '@amis.edu.ph';

echo "Learner:         {$localDisplayName}\n";
echo "Student Number:  {$studentNumber}\n";
echo "Grade Level:     {$gradeLevel}\n";
echo "Gender:          {$gender}\n";
echo "Date of Birth:   {$dob}\n";
echo "Email / UPN:     {$schoolEmail}\n";
echo "Temp Password:   {$tempPassword}\n";
echo "Section:         {$sectionName} (ID: {$sectionId})\n";
echo "Parent Email:    {$parentEmail}\n";
echo "Mother:          {$motherFirstName} {$motherMiddleName} {$motherLastName}\n";
echo "Father:          {$fatherFirstName} {$fatherMiddleName} {$fatherLastName}\n";
echo "Address:         {$address}\n\n";

$graph = new MicrosoftGraphService();
$studentSkuId = (string) config('services.microsoft.student_sku_id');

// Verify Azure user doesn't already exist
if ($graph->userExists($schoolEmail)) {
    echo "[ERROR] Azure user {$schoolEmail} already exists in Entra ID.\n";
    exit(1);
}

DB::beginTransaction();
try {
    // 1. Create or find Parent user
    $parentUser = User::where('email', $parentEmail)->first();
    if (!$parentUser) {
        $parentUser = User::create([
            'name'              => "{$motherFirstName} {$motherLastName}",
            'email'             => $parentEmail,
            'username'          => 'windle.gamboa',
            'role'              => 'applicant',
            'password'          => Hash::make('Amis@2026'),
            'account_status'    => 'verified',
            'email_verified_at' => now(),
            'first_login'       => 1,
        ]);
        echo "Created Parent User ID: {$parentUser->id}\n";
    } else {
        echo "Found Parent User ID: {$parentUser->id}\n";
    }

    // 2. Create EnrollmentApplicant
    $applicant = EnrollmentApplicant::create([
        'user_id'               => $parentUser->id,
        'student_type'          => 'New',
        'learning_mode'         => $learningMode,
        'timezone'              => 'Asia/Riyadh',
        'grade_level'           => $gradeLevel,
        'first_name'            => $firstName,
        'middle_name'           => $middleName,
        'last_name'             => $taggedLastName,
        'gender'                => $gender,
        'date_of_birth'         => $dob,
        'religion'              => 'ISLAM',
        'ethnicity'             => 'FILIPINO-MUSLIM',
        'country'               => $country,
        'city'                  => $city,
        'address'               => $address,
        'street_address'        => $address,
        'home_address'          => $address,
        'email'                 => $parentEmail,
        'parent_email'          => $parentEmail,
        'mother_first_name'     => $motherFirstName,
        'mother_middle_name'    => $motherMiddleName,
        'mother_last_name'      => $motherLastName,
        'mother_email'          => $parentEmail,
        'father_first_name'     => $fatherFirstName,
        'father_middle_name'    => $fatherMiddleName,
        'father_last_name'      => $fatherLastName,
        'father_email'          => $parentEmail,
        'emergency_name'        => "{$motherFirstName} {$motherLastName}",
        'emergency_relationship'=> 'MOTHER',
        'emergency_phone'       => '-',
        'emergency_address'     => $address,
        'school_year'           => $schoolYear,
        'status'                => 'approved',
        'submitted_at'          => now(),
        'review_remarks'        => 'Temporary enrollment approved by administration. Pending document submission.',
    ]);
    echo "Created EnrollmentApplicant ID: {$applicant->id}\n";

    // 3. Create Student User Account
    $studentUser = User::create([
        'name'                  => $localDisplayName,
        'email'                 => $schoolEmail,
        'username'              => $mailNickname,
        'password'              => Hash::make($tempPassword),
        'role'                  => 'student',
        'account_status'        => 'verified',
        'email_verified_at'     => now(),
        'first_login'           => 1,
    ]);
    echo "Created Student User ID: {$studentUser->id}\n";

    // 4. Create Student Record
    $student = Student::create([
        'user_id'                 => $studentUser->id,
        'enrollment_applicant_id' => $applicant->id,
        'student_number'          => $studentNumber,
        'school_email'            => $schoolEmail,
        'ms_email'                => $schoolEmail,
        'temp_password'           => $tempPassword,
        'temp_password_set_at'    => now(),
        'grade_level'             => $gradeLevel,
        'school_year'             => $schoolYear,
        'section'                 => $sectionName,
        'ms_account_enabled'      => 1,
        'ms_license_active'       => 1,
        'ms_license_assigned_at'  => now(),
        'ms_tenant_id'            => config('services.microsoft.tenant_id'),
    ]);
    echo "Created Student Record ID: {$student->id}\n";

    // 5. Assign Section
    StudentSection::create([
        'student_id'     => $student->id,
        'section_id'     => $sectionId,
        'ms_status'      => 'enrolled',
        'ms_enrolled_at' => now(),
    ]);
    echo "Assigned Section: {$sectionName} (ID: {$sectionId})\n";

    // 6. Create Microsoft 365 Account via Graph API
    echo "Creating Microsoft 365 / Entra ID Account via Graph API...\n";
    $msUser = $graph->createUser(
        displayName: $m365DisplayName,
        mailNickname: $mailNickname,
        upn: $schoolEmail,
        tempPassword: $tempPassword,
        reuseExisting: false
    );

    $msUserId = $msUser['id'] ?? null;
    if (!$msUserId) {
        throw new \Exception("Failed to retrieve Microsoft Object ID for {$schoolEmail}");
    }
    echo "Microsoft User Created: OID = {$msUserId}\n";

    // Assign M365 Student License
    if ($studentSkuId) {
        echo "Assigning Student License ({$studentSkuId})...\n";
        $graph->assignLicense($msUserId, [$studentSkuId], []);
        echo "Student License Assigned Successfully.\n";
    }

    $student->update([
        'ms_user_id'            => $msUserId,
        'ms_account_created_at' => now(),
    ]);

    // 7. Generate SOA
    try {
        (new SoaService())->generate($student, $applicant);
        echo "Generated Statement of Account (SOA).\n";
    } catch (\Throwable $soaEx) {
        echo "[Notice] SOA generation: " . $soaEx->getMessage() . "\n";
    }

    // 8. Record Audit Log
    AdminAuditLog::record(
        'student_onboarded',
        true,
        "Onboarded temporary student {$localDisplayName} ({$studentNumber}) with M365 account {$schoolEmail}.",
        [
            'student_id'     => $student->id,
            'student_number' => $studentNumber,
            'school_email'   => $schoolEmail,
            'ms_user_id'     => $msUserId,
            'section'        => $sectionName,
        ]
    );

    DB::commit();
    echo "\n[SUCCESS] Successfully onboarded {$localDisplayName} (#{$studentNumber})!\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n[FAILED] " . $e->getMessage() . "\n";
    Log::error("Failed onboarding learner {$studentNumber}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    exit(1);
}
