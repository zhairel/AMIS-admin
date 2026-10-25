<?php

namespace App\Services\Admin\Enrollment;

use App\Console\Commands\FixDisplayNames;
use App\Mail\EnrollmentOnboardingMail;
use App\Models\AdminAuditLog;
use App\Models\EnrollmentApplicant;
use App\Models\EnrollmentSetting;
use App\Models\Payment;
use App\Models\SchoolFee;
use App\Models\Student;
use App\Models\User;
use App\Services\GoogleDriveUploadService;
use App\Services\MicrosoftGraphService;
use App\Services\MsTeamsEnrollmentService;
use App\Services\SoaService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnrollmentApprovalService
{
    public function __construct(
        private readonly EnrollmentReviewService $reviewService,
    ) {}

    public function approve(EnrollmentApplicant $applicant): string
    {
        $applicant->loadMissing('payment', 'student.account');
        $this->reviewService->assertReadyForApproval($applicant);

        $settings = EnrollmentSetting::current();

        // ── Auto-link existing student for Old Student re-enrollment ───────────
        if (! $applicant->student) {
            $existingStudent = null;
            if ($applicant->amis_student_id) {
                $existingStudent = Student::where('student_number', $applicant->amis_student_id)->first();
            }
            if (! $existingStudent && $applicant->student_type === 'Old') {
                $existingStudent = Student::whereHas('applicant', function ($q) use ($applicant) {
                    $q->whereRaw('LOWER(TRIM(first_name)) = ?', [mb_strtolower(trim((string) $applicant->first_name))])
                        ->whereRaw('LOWER(TRIM(last_name)) = ?', [mb_strtolower(trim((string) $applicant->last_name))])
                        ->where('date_of_birth', $applicant->date_of_birth);
                })->first();
            }

            if ($existingStudent) {
                $existingStudent->update([
                    'enrollment_applicant_id' => $applicant->id,
                ]);
                $applicant->setRelation('student', $existingStudent);
            }
        }

        if ($applicant->student) {
            $this->backfillMicrosoftPhoto($applicant);

            if ($applicant->status !== 'approved') {
                $applicant->update([
                    'status' => 'approved',
                    'review_remarks' => $this->reviewService->missingDocumentRemarks($applicant),
                ]);
            }

            if ($applicant->student->grade_level !== $applicant->grade_level || $applicant->student->school_year !== $applicant->school_year) {
                $applicant->student->update([
                    'grade_level' => $applicant->grade_level,
                    'school_year' => $applicant->school_year,
                ]);
            }

            // Enroll into matching Teams section/channels for their new grade level
            if ($applicant->student->ms_user_id) {
                $this->enrollInTeams($applicant->student, $applicant->student->ms_user_id, new MicrosoftGraphService);
            }

            // Auto-generate permanent official Enrollment Application Form PDF and queue requirements if missing
            try {
                $docService = app(\App\Services\Admin\Enrollment\EnrollmentDocumentService::class);
                if (! $applicant->student->officialEnrollmentForm) {
                    $docService->generateApprovedEnrollmentForm($applicant->student, $applicant, auth()->id());
                }
                $docService->queueRequirements($applicant->student, $applicant);
            } catch (\Throwable $e) {
                Log::error("Auto Enrollment Form PDF generation failed on backfill for student {$applicant->student->id}: ".$e->getMessage());
            }

            // Sync enrollees files to Google Drive on approval
            try {
                $driveUploadService = app(GoogleDriveUploadService::class);
                $driveUploadService->uploadApplicantFiles($applicant);
            } catch (\Throwable $e) {
                Log::error('Auto Google Drive upload failed on backfill: '.$e->getMessage());
            }

            if (! $applicant->student->account && $this->shouldGenerateSoa($applicant)) {
                $this->generateSoa($applicant->student, $applicant);

                return 'Student already onboarded. Missing SOA was generated. Official enrollment form verified. Microsoft profile photo sync was retried. Files synced to Google Drive.';
            }

            return 'Student already onboarded. Official enrollment form verified. Microsoft profile photo sync was retried. Files synced to Google Drive.';
        }

        // ── Duplicate student guard ────────────────────────────────────────────
        // Prevent creating a second student record (and MS account) for the same
        // person when a parent submitted multiple enrollment applications.
        // We match by first name + last name + date of birth (case-insensitive).
        $duplicateStudent = Student::whereHas('applicant', function ($q) use ($applicant) {
            $q->whereRaw('LOWER(TRIM(first_name)) = ?', [mb_strtolower(trim((string) $applicant->first_name))])
                ->whereRaw('LOWER(TRIM(last_name)) = ?', [mb_strtolower(trim((string) $applicant->last_name))])
                ->where('date_of_birth', $applicant->date_of_birth);
        })
            ->where('school_year', $applicant->school_year ?? config('services.school.year'))
            ->first();

        if ($duplicateStudent) {
            AdminAuditLog::record(
                'duplicate_approval_blocked',
                false,
                "Approval blocked for applicant #{$applicant->id} — duplicate student detected: #{$duplicateStudent->student_number}",
                [
                    'applicant_id' => $applicant->id,
                    'applicant_name' => trim("{$applicant->first_name} {$applicant->last_name}"),
                    'existing_student' => $duplicateStudent->student_number,
                    'existing_email' => $duplicateStudent->school_email,
                ]
            );

            throw ValidationException::withMessages([
                'status' => "⚠️ Duplicate student detected!\n\n"
                    ."A student with the same name and date of birth already exists:\n"
                    ."• Student #: {$duplicateStudent->student_number}\n"
                    ."• School Email: {$duplicateStudent->school_email}\n\n"
                    ."Do NOT approve this application. Instead, link this applicant to the existing student record to avoid creating a duplicate Microsoft account.\n\n"
                    .'If this is a different student with the same name and birthdate, contact IT to resolve manually.',
            ]);
        }
        // ── End duplicate guard ───────────────────────────────────────────────

        $result = DB::transaction(function () use ($applicant, $settings) {
            $shouldGenerateMicrosoftAccount = $settings->generate_microsoft_account ?? true;
            $graph = $shouldGenerateMicrosoftAccount ? new MicrosoftGraphService : null;
            $studentNumber = $this->generateStudentNumber($applicant);
            [$mailNick, $schoolEmail] = $this->generateSchoolEmail($applicant, $studentNumber, $graph);
            // Use birthdate as temp password (aug291997 format) — simple for students
            $tempPassword = $this->generateBirthdatePassword($applicant);
            $student = $this->createStudent($applicant, $studentNumber, $schoolEmail, null, $tempPassword);

            if ($shouldGenerateMicrosoftAccount) {
                [$msUserId, $msError] = $this->createMicrosoftAccount($graph, $applicant, $mailNick, $schoolEmail, $tempPassword);
                if ($msUserId) {
                    $student->update([
                        'ms_user_id' => $msUserId,
                        'ms_account_created_at' => now(),
                    ]);

                    $this->uploadApplicantPhotoToMicrosoft($applicant, $student, $graph, $msUserId);
                }
            } else {
                $msUserId = null;
                $msError = null;
                $graph = new MicrosoftGraphService;
            }

            $this->enrollInTeams($student, $msUserId, $graph);

            if ($settings->generate_soa ?? true) {
                $this->generateSoa($student, $applicant);
            }

            $documentRemarks = $this->reviewService->missingDocumentRemarks($applicant);

            $applicant->update([
                'status' => 'approved',
                'review_remarks' => $documentRemarks,
            ]);

            $onboardingStatus = $this->sendOnboardingIfPossible($settings, $applicant, $student, $tempPassword, $msError);

            $credentialsInfo = " (Email: {$schoolEmail} | Temp Pass: {$tempPassword})";

            if ($msError) {
                return 'Application approved. Student number generated.'.$credentialsInfo.' Note: Microsoft account creation failed. Please create it manually. Error: '.$msError;
            }

            // Auto-generate permanent official Enrollment Application Form PDF and queue requirements
            try {
                $docService = app(\App\Services\Admin\Enrollment\EnrollmentDocumentService::class);
                $docService->generateApprovedEnrollmentForm($student, $applicant, auth()->id());
                $docService->queueRequirements($student, $applicant);
            } catch (\Throwable $e) {
                Log::error("Auto Enrollment Form PDF generation failed on approval for student {$student->id}: ".$e->getMessage());
            }

            return match ($onboardingStatus) {
                'sent' => 'Application approved.'.$credentialsInfo.' Student credentials and official enrollment form were generated and sent to the parent.',
                'missing_payment_proof' => 'Application approved.'.$credentialsInfo.' Student credentials and official enrollment form were generated. Welcome email was not sent because no payment proof is uploaded yet.',
                'missing_recipient' => 'Application approved.'.$credentialsInfo.' Student credentials and official enrollment form were generated. Welcome email was not sent because no valid recipient email was found.',
                'failed' => 'Application approved.'.$credentialsInfo.' Student credentials and official enrollment form were generated. Welcome email failed to send; please check the mail logs.',
                default => 'Application approved.'.$credentialsInfo.' Student credentials and official enrollment form were generated. Welcome email auto-send is currently disabled.',
            };
        });

        // Sync enrollees files to Google Drive on approval
        try {
            $driveUploadService = app(GoogleDriveUploadService::class);
            $driveUploadService->uploadApplicantFiles($applicant);
        } catch (\Throwable $e) {
            Log::error('Auto Google Drive upload failed on approval transaction success: '.$e->getMessage());
        }

        return $result;
    }

    /**
     * Generate a simple birthdate-based password: aug291997, jan012005, etc.
     * Falls back to a secure random if DOB is missing.
     */
    private function generateBirthdatePassword(EnrollmentApplicant $applicant): string
    {
        $dob = $applicant->date_of_birth;
        if ($dob) {
            $ts = strtotime((string) $dob);
            if ($ts !== false) {
                return ucfirst(strtolower(date('M', $ts))).date('d', $ts).date('Y', $ts).'@';
            }
        }

        // Fallback: strong password compliant with MS Graph requirements
        return 'Amis@'.rand(10000, 99999);
    }

    private function generateStudentNumber(EnrollmentApplicant $applicant): string
    {
        $schoolYear = $applicant->school_year ?? config('services.school.year') ?? date('Y');
        $startYear = substr(preg_replace('/\D+/', '', $schoolYear), 0, 4);
        if (strlen($startYear) < 4) {
            $startYear = date('Y');
        }
        $yearSuffix = substr($startYear, 2, 2);

        $studentNumber = DB::transaction(function () use ($yearSuffix) {
            $latest = Student::where('student_number', 'like', $yearSuffix.'%')
                ->where('student_number', 'not like', $yearSuffix.'9%')
                ->lockForUpdate()
                ->orderByRaw('CAST(student_number AS UNSIGNED) DESC')
                ->value('student_number');

            $num = $latest ? (int) substr($latest, 2) + 1 : 1;
            if ($yearSuffix === '26' && $num < 1219) {
                $num = 1219;
            }

            $candidate = $yearSuffix.str_pad($num, 4, '0', STR_PAD_LEFT);

            while (Student::where('student_number', $candidate)->exists()) {
                $num++;
                $candidate = $yearSuffix.str_pad($num, 4, '0', STR_PAD_LEFT);
            }

            return $candidate;
        });

        return $studentNumber;
    }

    private function generateSchoolEmail(
        EnrollmentApplicant $applicant,
        string $studentNumber,
        ?MicrosoftGraphService $graph = null,
    ): array {
        $baseMailNick = $this->baseMailNickname($applicant, $studentNumber);
        $mailNick = $baseMailNick;
        $schoolEmail = $mailNick.'@amis.edu.ph';
        $suffix = 1;
        $maxAttempts = 500;

        while ($this->schoolEmailIsReserved($schoolEmail, $graph)) {
            if ($suffix > $maxAttempts) {
                throw ValidationException::withMessages([
                    'status' => 'Unable to generate a unique Microsoft school email after '.$maxAttempts.' attempts. Please contact IT before approving this applicant.',
                ]);
            }

            $mailNick = $baseMailNick.$suffix;
            $schoolEmail = $mailNick.'@amis.edu.ph';
            $suffix++;
        }

        return [$mailNick, $schoolEmail];
    }

    private function baseMailNickname(EnrollmentApplicant $applicant, string $studentNumber): string
    {
        $firstLetterOfLastName = strtolower(substr(preg_replace('/[^a-zA-Z]/', '', (string) $applicant->last_name), 0, 1));
        $firstGivenName = preg_split('/\s+/', trim((string) $applicant->first_name))[0] ?? '';
        $firstName = strtolower(preg_replace('/[^a-zA-Z]/', '', $firstGivenName));

        return $studentNumber.$firstLetterOfLastName.($firstName ?: 'student');
    }

    private function schoolEmailIsReserved(string $schoolEmail, ?MicrosoftGraphService $graph = null): bool
    {
        $existsInPortal = Student::where(function ($query) use ($schoolEmail) {
            $query->where('school_email', $schoolEmail)
                ->orWhere('ms_email', $schoolEmail);
        })->exists();

        if ($existsInPortal) {
            return true;
        }

        if (! $graph) {
            return false;
        }

        try {
            return $graph->userExistsOrFail($schoolEmail);
        } catch (\Throwable $exception) {
            Log::error('Microsoft email availability check failed for '.$schoolEmail.': '.$exception->getMessage());

            throw ValidationException::withMessages([
                'status' => 'Unable to verify Microsoft email availability in Azure AD. Approval stopped before creating official student records. Please try again once Microsoft Graph is reachable.',
            ]);
        }
    }

    private function createMicrosoftAccount(
        MicrosoftGraphService $graph,
        EnrollmentApplicant $applicant,
        string $mailNick,
        string $schoolEmail,
        string $tempPassword,
    ): array {
        try {
            $displayName = FixDisplayNames::buildM365DisplayName(
                $applicant->first_name,
                $applicant->middle_name,
                $applicant->last_name
            );

            if ($graph->userExistsOrFail($schoolEmail)) {
                throw ValidationException::withMessages([
                    'status' => "Microsoft account {$schoolEmail} already exists in Azure AD. Approval stopped to prevent assigning a duplicate official email.",
                ]);
            }

            $msUser = $graph->createUser($displayName, $mailNick, $schoolEmail, $tempPassword, reuseExisting: false);
            $msUserId = $msUser['id'] ?? null;

            if ($msUserId) {
                $studentSkuId = config('services.microsoft.student_sku_id');
                if ($studentSkuId) {
                    try {
                        $graph->assignLicense($msUserId, [$studentSkuId], []);
                        AdminAuditLog::record('license_assigned', true, "Automatically assigned student license to {$schoolEmail}", [
                            'email' => $schoolEmail,
                            'sku_id' => $studentSkuId,
                            'ms_user_id' => $msUserId,
                        ]);
                    } catch (\Throwable $licenseEx) {
                        Log::error("Failed to assign license to {$schoolEmail}: ".$licenseEx->getMessage());
                        AdminAuditLog::record('license_assigned', false, "Failed to automatically assign student license to {$schoolEmail}: ".$licenseEx->getMessage(), [
                            'email' => $schoolEmail,
                            'sku_id' => $studentSkuId,
                            'ms_user_id' => $msUserId,
                        ]);
                    }
                }

                // Auto-disable MFA for the new student account
                try {
                    $graph->disablePerUserMfa($msUserId);
                } catch (\Throwable $mfaEx) {
                    Log::warning("Could not disable MFA for {$schoolEmail}: ".$mfaEx->getMessage());
                }
            }

            return [$msUserId, null];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            Log::error('Microsoft Graph error for applicant '.$applicant->id.': '.$message);

            return [null, $message];
        }
    }

    private function createStudent(
        EnrollmentApplicant $applicant,
        string $studentNumber,
        string $schoolEmail,
        ?string $msUserId,
        string $tempPassword,
    ): Student {
        try {
            // Find or create a unique User record for this student school email UPN
            $studentUser = User::where('email', $schoolEmail)->first();
            if (! $studentUser) {
                $prefix = explode('@', $schoolEmail)[0];
                $username = $prefix;
                if (User::where('username', $username)->exists()) {
                    $username = $prefix.'_'.$studentNumber;
                }
                $studentUser = User::create([
                    'name' => trim(($applicant->first_name ?? '').' '.($applicant->last_name ?? '')),
                    'email' => $schoolEmail,
                    'username' => $username,
                    'password' => Hash::make(Str::random(32)),
                    'role' => 'student',
                    'account_status' => 'verified',
                    'email_verified_at' => now(),
                ]);
            } else {
                $studentUser->update([
                    'role' => 'student',
                    'account_status' => 'verified',
                ]);
            }

            return Student::create([
                'user_id' => $studentUser->id,
                'enrollment_applicant_id' => $applicant->id,
                'student_number' => $studentNumber,
                'school_email' => $schoolEmail,
                'ms_email' => $schoolEmail,
                'ms_user_id' => $msUserId,
                'ms_account_created_at' => $msUserId ? now() : null,
                'temp_password' => $tempPassword,
                'temp_password_set_at' => now(),
                'grade_level' => $applicant->grade_level,
                'school_year' => $applicant->school_year,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'status' => 'Generated AMIS Student ID or school email already exists. Approval stopped before creating duplicate official records. Please try again.',
            ]);
        }
    }

    private function enrollInTeams(Student $student, ?string $msUserId, MicrosoftGraphService $graph): void
    {
        if (! $msUserId) {
            return;
        }

        try {
            $teamsResult = (new MsTeamsEnrollmentService($graph))->enrollStudent($student);

            if (($teamsResult['failed'] ?? 0) > 0) {
                Log::warning("Teams enrollment partial failure for {$student->student_number}", $teamsResult['errors'] ?? []);
            }
        } catch (\Throwable $exception) {
            Log::error('Teams enrollment failed for '.$student->student_number.': '.$exception->getMessage());
        }
    }

    public function backfillMicrosoftPhoto(EnrollmentApplicant $applicant): bool
    {
        $applicant->loadMissing('student');

        if (! $applicant->student) {
            return false;
        }

        $identifier = $applicant->student->ms_user_id ?: $applicant->student->school_email;

        if (blank($identifier)) {
            return false;
        }

        return $this->uploadApplicantPhotoToMicrosoft($applicant, $applicant->student, new MicrosoftGraphService, $identifier);
    }

    private function uploadApplicantPhotoToMicrosoft(
        EnrollmentApplicant $applicant,
        Student $student,
        MicrosoftGraphService $graph,
        string $msUserId,
    ): bool {
        $photo = $this->applicantPhotoForMicrosoft($applicant);

        if (! $photo) {
            return false;
        }

        try {
            $graph->uploadUserPhoto($msUserId, $photo['bytes'], $photo['content_type']);

            AdminAuditLog::record('student_photo_uploaded', true, "Uploaded 2x2 photo to Microsoft profile for {$student->school_email}", [
                'student_id' => $student->id,
                'applicant_id' => $applicant->id,
                'email' => $student->school_email,
                'photo_path' => $photo['path'],
                'content_type' => $photo['content_type'],
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::error('Microsoft profile photo upload failed for '.$student->school_email.': '.$exception->getMessage());

            AdminAuditLog::record('student_photo_uploaded', false, "Failed to upload 2x2 photo to Microsoft profile for {$student->school_email}", [
                'student_id' => $student->id,
                'applicant_id' => $applicant->id,
                'email' => $student->school_email,
                'photo_path' => $photo['path'],
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function applicantPhotoForMicrosoft(EnrollmentApplicant $applicant): ?array
    {
        $urlOrPath = $applicant->photo_2x2_url;
        if (blank($urlOrPath)) {
            return null;
        }

        $bytes = null;
        $contentType = null;
        $resolvedPathOrUrl = null;
        $candidates = [];

        // 1. Try to resolve as a local file path
        $localPath = $this->resolveApplicantPhotoPath($urlOrPath);
        if ($localPath) {
            $bytes = @file_get_contents($localPath);
            if ($bytes !== false && $bytes !== '') {
                $contentType = $this->imageContentType($localPath);
                $resolvedPathOrUrl = $localPath;
            }
        }

        // 2. If local resolution failed or if it's a URL, try fetching via HTTP
        $urlCandidates = [];
        if (! $bytes) {
            if (filter_var($urlOrPath, FILTER_VALIDATE_URL)) {
                $urlCandidates[] = $urlOrPath;
            } else {
                // Configured storage URL
                $storageUrl = config('services.enrollment_storage_url')
                    ?? env('ENROLLMENT_STORAGE_URL')
                    ?? 'https://enrollment.amis.edu.ph/storage';

                if (str_contains($storageUrl, '127.0.0.1') || str_contains($storageUrl, 'localhost')) {
                    $storageUrl = 'https://enrollment.amis.edu.ph/storage';
                }
                $urlCandidates[] = rtrim($storageUrl, '/').'/'.ltrim($urlOrPath, '/');

                // Explicit production enrollment URL fallback
                $urlCandidates[] = 'https://enrollment.amis.edu.ph/storage/'.ltrim($urlOrPath, '/');

                // Admin site APP_URL fallback (in case of symlinks served directly)
                $appUrl = config('app.url');
                if ($appUrl && ! str_contains($appUrl, '127.0.0.1') && ! str_contains($appUrl, 'localhost')) {
                    $urlCandidates[] = rtrim($appUrl, '/').'/storage/'.ltrim($urlOrPath, '/');
                }

                // Explicit production admin URL fallback
                $urlCandidates[] = 'https://admin.amis.edu.ph/storage/'.ltrim($urlOrPath, '/');
            }

            $urlCandidates = array_values(array_unique($urlCandidates));

            foreach ($urlCandidates as $url) {
                try {
                    $response = Http::timeout(10)->withoutVerifying()->get($url);
                    if ($response->successful()) {
                        $bytes = $response->body();
                        $contentType = $response->header('Content-Type');

                        // Parse Content-Type or extract from path extension
                        if (! $contentType || ! str_starts_with(strtolower($contentType), 'image/')) {
                            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                            $contentType = match ($ext) {
                                'png' => 'image/png',
                                'webp' => 'image/webp',
                                default => 'image/jpeg',
                            };
                        }

                        $resolvedPathOrUrl = $url;
                        break; // Found and loaded
                    } else {
                        Log::warning("Failed to fetch photo from URL {$url}: Status Code ".$response->status());
                    }
                } catch (\Throwable $e) {
                    Log::warning("Failed to fetch photo from URL {$url}: ".$e->getMessage());
                }
            }
        }

        if (! $bytes) {
            // Retrieve candidate local paths for logging
            $optimizedPath = preg_replace('#thumbnails/(small|medium|large)/#', 'optimized/', $urlOrPath);
            $variantPaths = collect([
                str_replace('optimized/', 'thumbnails/large/', $optimizedPath),
                str_replace('optimized/', 'thumbnails/medium/', $optimizedPath),
                $optimizedPath,
                $urlOrPath,
            ])->unique();

            foreach ($this->enrollmentStorageRoots() as $root) {
                foreach ($variantPaths as $variantPath) {
                    $candidates[] = rtrim($root, '/').'/'.ltrim($variantPath, '/');
                }
            }

            $searchedCandidatesStr = implode(', ', $candidates);
            $triedUrlsStr = implode(', ', $urlCandidates);
            Log::error("Microsoft profile photo sync failed: 2x2 photo not found for applicant {$applicant->id}. Searched local candidates: [{$searchedCandidatesStr}]. Tried HTTP URLs: [{$triedUrlsStr}].");

            return null;
        }

        return [
            'path' => $resolvedPathOrUrl,
            'bytes' => $bytes,
            'content_type' => $contentType ?: 'image/jpeg',
        ];
    }

    private function resolveApplicantPhotoPath(?string $path): ?string
    {
        if (blank($path) || filter_var($path, FILTER_VALIDATE_URL)) {
            return null;
        }

        $path = ltrim((string) $path, '/');
        $candidates = [];

        $optimizedPath = preg_replace('#thumbnails/(small|medium|large)/#', 'optimized/', $path);

        if (str_contains($optimizedPath, 'optimized/')) {
            $originalDirectory = dirname(str_replace('optimized/', 'original/', $optimizedPath));
            $filename = pathinfo($optimizedPath, PATHINFO_FILENAME);

            foreach ($this->enrollmentStorageRoots() as $root) {
                $directory = rtrim($root, '/').'/'.$originalDirectory;
                if (! is_dir($directory)) {
                    continue;
                }

                foreach (glob($directory.'/'.$filename.'.*') ?: [] as $file) {
                    $candidates[] = $file;
                }
            }
        }

        $variantPaths = collect([
            str_replace('optimized/', 'thumbnails/large/', $optimizedPath),
            str_replace('optimized/', 'thumbnails/medium/', $optimizedPath),
            $optimizedPath,
            $path,
        ])->unique();

        foreach ($this->enrollmentStorageRoots() as $root) {
            foreach ($variantPaths as $variantPath) {
                $candidates[] = rtrim($root, '/').'/'.ltrim($variantPath, '/');
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && filesize($candidate) > 0) {
                return $candidate;
            }
        }

        return null;
    }

    private function enrollmentStorageRoots(): array
    {
        return [
            base_path('../amis_enrollment/storage/app/public'),
            base_path('../amis_enrollment/public/storage'),
            base_path('../enrollment.amis.edu.ph/storage/app/public'),
            base_path('../enrollment.amis.edu.ph/public/storage'),
            base_path('../enrollment/storage/app/public'),
            base_path('../enrollment/public/storage'),
            base_path('../../amis_enrollment/storage/app/public'),
            base_path('../../enrollment.amis.edu.ph/storage/app/public'),
            base_path('../../enrollment.amis.edu.ph/public/storage'),
            base_path('../../public_html/amis_enrollment/storage/app/public'),
            base_path('../../public_html/storage'),
            storage_path('app/public'),
            public_path('storage'),
            public_path(),
        ];
    }

    private function imageContentType(string $path): string
    {
        $mime = function_exists('mime_content_type') ? mime_content_type($path) : null;

        if ($mime && str_starts_with(strtolower($mime), 'image/')) {
            return $mime;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    private function generateSoa(Student $student, EnrollmentApplicant $applicant): void
    {
        if (! $this->shouldGenerateSoa($applicant)) {
            Log::info('SOA generation skipped for non-new student applicant '.$applicant->id, [
                'student_type' => $applicant->student_type,
            ]);

            return;
        }

        if (! SchoolFee::forGrade($applicant->grade_level, $applicant->school_year)) {
            Log::warning("Skipped SOA generation for Student {$student->student_number}: No school fees found for {$applicant->grade_level} SY {$applicant->school_year}.");

            return;
        }

        try {
            (new SoaService)->generate($student, $applicant);
        } catch (\Throwable $exception) {
            Log::error('SOA generation failed: '.$exception->getMessage());

            throw ValidationException::withMessages([
                'status' => 'Application approval created the student record, but SOA generation failed: '.$exception->getMessage(),
            ]);
        }
    }

    private function shouldGenerateSoa(EnrollmentApplicant $applicant): bool
    {
        $studentType = Str::of((string) $applicant->student_type)
            ->lower()
            ->replace(['_', '-'], ' ')
            ->squish()
            ->toString();

        return $studentType === 'new' || $studentType === 'new student' || $studentType === 'transferee' || $studentType === 'transfer';
    }

    private function sendOnboardingIfPossible(
        EnrollmentSetting $settings,
        EnrollmentApplicant $applicant,
        Student $student,
        string $tempPassword,
        ?string $msError,
    ): string {
        if (! ($settings->send_onboarding_email ?? false)) {
            $this->markOnboardingEmail($applicant, 'disabled');

            return 'disabled';
        }

        if (! $this->hasUploadedPaymentProof($applicant)) {
            $this->markOnboardingEmail($applicant, 'missing_payment_proof');

            return 'missing_payment_proof';
        }

        $recipients = $this->onboardingRecipients($applicant);

        if ($recipients->isEmpty()) {
            $this->markOnboardingEmail($applicant, 'missing_recipient');

            return 'missing_recipient';
        }

        [$sent, $error] = $this->sendOnboardingEmail($applicant, $student, $tempPassword, $msError, $recipients->all());

        if (! $sent) {
            $this->markOnboardingEmail($applicant, 'failed', $error);

            return 'failed';
        }

        $student->update(['credentials_sent_at' => now()]);
        $this->markOnboardingEmail($applicant, 'sent', sentAt: now());

        return 'sent';
    }

    public function resendOnboardingInbox(EnrollmentApplicant $applicant): string
    {
        $applicant->loadMissing('student');

        if ($applicant->status !== 'approved' || ! $applicant->student) {
            throw ValidationException::withMessages([
                'onboarding_email' => 'Only approved applicants with generated student credentials can receive the onboarding inbox email.',
            ]);
        }

        $recipients = $this->onboardingRecipients($applicant);

        if ($recipients->isEmpty()) {
            $this->markOnboardingEmail($applicant, 'missing_recipient');

            return 'No valid parent or applicant email was found.';
        }

        $student = $applicant->student;
        // Use birthdate-based password for resend too (consistent with initial approval)
        $tempPassword = $this->generateBirthdatePassword($applicant);

        try {
            if (filled($student->school_email)) {
                // Reset without forcing change on next sign-in
                (new MicrosoftGraphService)->resetPasswordSimple($student->school_email, $tempPassword);
            }
        } catch (\Throwable $exception) {
            $msError = 'Microsoft password reset failed: '.$exception->getMessage();

            Log::error('Failed to reset Microsoft password before onboarding resend: '.$exception->getMessage(), [
                'applicant_id' => $applicant->id,
                'student_id' => $student->id,
                'school_email' => $student->school_email,
            ]);

            [$sent, $error] = $this->sendOnboardingEmail($applicant, $student, '', $msError, $recipients->all());

            if (! $sent) {
                $this->markOnboardingEmail($applicant, 'failed', $error);

                return 'Inbox email failed to send after Microsoft password reset failed. Please check mail logs.';
            }

            $this->markOnboardingEmail($applicant, 'sent_reset_pending', $msError, now());

            return 'Inbox email sent to '.$recipients->implode(', ').', but Microsoft password reset is still blocked. Fix Microsoft permissions before resending credentials.';
        }

        $student->update([
            'temp_password' => $tempPassword,
            'temp_password_set_at' => now(),
            'password_changed_at' => null,
        ]);

        [$sent, $error] = $this->sendOnboardingEmail($applicant, $student, $tempPassword, null, $recipients->all());

        if (! $sent) {
            $this->markOnboardingEmail($applicant, 'failed', $error);

            return 'Inbox email failed to resend. Please check mail logs.';
        }

        $student->update(['credentials_sent_at' => now()]);
        $this->markOnboardingEmail($applicant, 'sent', sentAt: now());

        return 'Inbox email resent to '.$recipients->implode(', ').'.';
    }

    private function hasUploadedPaymentProof(EnrollmentApplicant $applicant): bool
    {
        $applicant->loadMissing('payment');

        if (filled($applicant->payment?->receipt_url)) {
            return true;
        }

        return Payment::whereHas('applicant', function ($query) use ($applicant) {
            if ($applicant->family_application_id) {
                $query->where('family_application_id', $applicant->family_application_id);
            } elseif ($applicant->user_id) {
                $query->where('user_id', $applicant->user_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        })
            ->whereNotNull('receipt_url')
            ->where('receipt_url', '!=', '')
            ->exists();
    }

    private function sendOnboardingEmail(
        EnrollmentApplicant $applicant,
        Student $student,
        string $tempPassword,
        ?string $msError,
        array $recipients,
    ): array {
        try {
            $mailer = $this->onboardingMailer();
            Mail::mailer($mailer)->to($recipients)->send(new EnrollmentOnboardingMail($applicant, $student, $tempPassword, $msError));

            Log::info('Onboarding email sent', [
                'applicant_id' => $applicant->id,
                'student_id' => $student->id,
                'recipients' => $recipients,
                'mailer' => $mailer,
            ]);

            return [true, null];
        } catch (\Throwable $exception) {
            Log::error('Failed to send onboarding email: '.$exception->getMessage(), [
                'applicant_id' => $applicant->id,
                'student_id' => $student->id,
                'recipients' => $recipients,
                'mailer' => $this->onboardingMailer(),
            ]);

            return [false, $exception->getMessage()];
        }
    }

    private function onboardingMailer(): string
    {
        $mailers = config('mail.mailers', []);

        // Prefer the dedicated inquiries@amis.edu.ph mailer if its password is set
        if (array_key_exists('inquiries', $mailers) && filled(env('MAIL_INQUIRIES_PASSWORD'))) {
            return 'inquiries';
        }

        $default = (string) config('mail.default', 'log');

        if (in_array($default, ['log', 'array'], true) && array_key_exists('sendmail', $mailers)) {
            return 'sendmail';
        }

        return $default;
    }

    private function onboardingRecipients(EnrollmentApplicant $applicant): Collection
    {
        return collect([$applicant->parent_email ?: null, $applicant->email ?: null])
            ->filter(fn ($email) => $email && $email !== 'NA' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
    }

    private function markOnboardingEmail(
        EnrollmentApplicant $applicant,
        string $status,
        ?string $error = null,
        mixed $sentAt = null,
    ): void {
        $applicant->forceFill([
            'onboarding_email_status' => $status,
            'onboarding_email_sent_at' => $sentAt,
            'onboarding_email_error' => $error ? Str::limit($error, 1000, '') : null,
        ])->save();
    }
}
