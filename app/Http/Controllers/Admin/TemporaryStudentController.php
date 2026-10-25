<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\EnrollmentApplicant;
use App\Models\Section;
use App\Models\Student;
use App\Services\MicrosoftGraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemporaryStudentController extends Controller
{
    public function index(Request $request)
    {
        $gradeOrder = ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
        $activeSy = app(\App\Services\SchoolYearService::class)->current();

        $applyFilters = function ($query) use ($request, $activeSy) {
            if ($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy)) {
                $query->where('students.school_year', $activeSy);
            }

            // Temporary accounts filter
            $query->where(function ($q) {
                $q->whereHas('applicant', function ($a) {
                    $a->where('last_name', 'like', '%[TEMP%')
                      ->orWhere('first_name', 'like', '%[TEMP%');
                })->orWhereHas('user', function ($u) {
                    $u->where('name', 'like', '%[TEMP%');
                })->orWhere('students.school_email', 'like', '%temp%');
            });

            if ($request->filled('student_numbers')) {
                $numbers = array_filter(array_map('trim', explode(',', $request->student_numbers)));
                if (!empty($numbers)) {
                    $query->whereIn('students.student_number', $numbers);
                }
            }

            if ($request->filled('student_ids')) {
                $ids = array_filter(array_map('trim', explode(',', $request->student_ids)));
                if (!empty($ids)) {
                    $query->whereIn('students.id', $ids);
                }
            }

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('students.student_number', 'like', "%{$search}%")
                      ->orWhere('students.school_email', 'like', "%{$search}%")
                      ->orWhereHas('applicant', function ($a) use ($search) {
                          $a->where('first_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('lrn', 'like', "%{$search}%");
                      })->orWhereHas('user', function ($u) use ($search) {
                          $u->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->filled('grade')) {
                $query->where('students.grade_level', $request->grade);
            }

            if ($request->filled('gender')) {
                $gender = strtolower($request->gender);
                if ($gender === 'not_set') {
                    $query->whereHas('applicant', fn ($q) => $q->whereNull('gender')->orWhere('gender', ''));
                } else {
                    $query->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(gender) = ?', [$gender]));
                }
            }

            if ($request->filled('mode') || $request->filled('learning_mode')) {
                $mode = strtolower($request->input('mode', $request->input('learning_mode')));
                if (str_contains($mode, 'face') || str_contains($mode, 'f2f')) {
                    $query->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(learning_mode) LIKE ? OR LOWER(learning_mode) LIKE ?', ['%face%', '%f2f%']));
                } elseif (str_contains($mode, '1st')) {
                    $query->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(learning_mode) LIKE ?', ['%1st%']));
                } elseif (str_contains($mode, '2nd')) {
                    $query->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(learning_mode) LIKE ?', ['%2nd%']));
                } else {
                    $query->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(learning_mode) LIKE ?', ["%{$mode}%"]));
                }
            }

            if ($request->filled('type') || $request->filled('student_type')) {
                $type = strtolower($request->input('type', $request->input('student_type')));
                $query->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(student_type) LIKE ?', ["%{$type}%"]));
            }

            if ($request->filled('ms_status')) {
                $status = $request->ms_status;
                if ($status === 'active' || $status === 'enrolled') {
                    $query->where('students.ms_license_active', true)
                        ->whereNotNull('students.ms_user_id');
                } elseif ($status === 'failed') {
                    $query->whereHas('studentSection', fn ($q) => $q->where('ms_status', 'failed'));
                } elseif ($status === 'pending') {
                    $query->whereHas('studentSection', fn ($q) => $q->where('ms_status', 'pending'));
                } elseif ($status === 'no_account') {
                    $query->whereNull('students.ms_user_id');
                } elseif ($status === 'no_license') {
                    $query->where('students.ms_license_active', false)
                        ->whereNotNull('students.ms_user_id');
                }
            }

            if ($request->filled('password_status')) {
                $pStatus = $request->password_status;
                if ($pStatus === 'changed') {
                    $query->whereNotNull('students.password_changed_at');
                } elseif ($pStatus === 'temp') {
                    $query->whereNull('students.password_changed_at')
                        ->whereNotNull('students.ms_user_id');
                } elseif ($pStatus === 'no_account') {
                    $query->whereNull('students.ms_user_id');
                }
            }

            return $query;
        };

        $baseQuery = Student::with(['applicant.user', 'studentSection.section'])->whereNull('students.deleted_at');
        $query = $applyFilters($baseQuery);

        $gradeField = "FIELD(students.grade_level, 'Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12')";
        $sort = $request->input('sort', 'latest');
        $direction = strtolower((string) $request->input('direction', $sort === 'name' ? 'asc' : 'desc')) === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'name' => $query
                ->leftJoin('enrollment_applicants as sort_applicants', 'sort_applicants.id', '=', 'students.enrollment_applicant_id')
                ->select('students.*')
                ->orderByRaw("CASE 
                    WHEN LOWER(sort_applicants.learning_mode) LIKE '%face%' OR LOWER(sort_applicants.learning_mode) LIKE '%f2f%' THEN 1 
                    WHEN LOWER(sort_applicants.learning_mode) LIKE '%1st%' THEN 2 
                    WHEN LOWER(sort_applicants.learning_mode) LIKE '%2nd%' THEN 3 
                    ELSE 9 
                END ASC")
                ->orderBy('sort_applicants.last_name', $direction)
                ->orderBy('sort_applicants.first_name', $direction)
                ->orderBy('students.id', 'desc'),
            'grade' => $query
                ->orderByRaw("CASE WHEN {$gradeField} = 0 THEN 1 ELSE 0 END ASC")
                ->orderByRaw("{$gradeField} {$direction}")
                ->orderBy('students.id', 'desc'),
            'gender' => $query
                ->leftJoin('enrollment_applicants as sort_applicants', 'sort_applicants.id', '=', 'students.enrollment_applicant_id')
                ->select('students.*')
                ->orderByRaw("CASE LOWER(COALESCE(sort_applicants.gender, '')) WHEN 'male' THEN 1 WHEN 'female' THEN 2 ELSE 3 END {$direction}")
                ->orderBy('students.id', 'desc'),
            'student_id' => $query->orderBy('student_number', $direction)->orderBy('students.id', 'desc'),
            default => $query->orderBy('students.id', 'desc'),
        };

        $isPrint = $request->boolean('print_info')
            || $request->boolean('print_credentials')
            || $request->boolean('print_id')
            || $request->boolean('print_documents')
            || $request->boolean('print')
            || $request->boolean('is_print');

        if ($isPrint) {
            $students = $query->get();
        } else {
            $students = $query->paginate(25)->withQueryString();
        }

        // Stats calculation for telemetry
        $tempBase = Student::whereNull('deleted_at')
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))
            ->where(function ($q) {
                $q->whereHas('applicant', function ($a) {
                    $a->where('last_name', 'like', '%[TEMP%')
                      ->orWhere('first_name', 'like', '%[TEMP%');
                })->orWhereHas('user', function ($u) {
                    $u->where('name', 'like', '%[TEMP%');
                })->orWhere('school_email', 'like', '%temp%');
            });

        $totalTemp = (clone $tempBase)->count();
        $f2fTemp = (clone $tempBase)->whereHas('applicant', function ($q) {
            $q->where('learning_mode', 'like', '%f2f%')
              ->orWhere('learning_mode', 'like', '%face%');
        })->count();
        $odlTemp = (clone $tempBase)->whereHas('applicant', function ($q) {
            $q->where('learning_mode', 'not like', '%face%')
              ->where('learning_mode', 'not like', '%f2f%');
        })->count();
        $passwordsChanged = (clone $tempBase)->whereNotNull('password_changed_at')->count();
        $passwordsTemp = (clone $tempBase)->whereNull('password_changed_at')->whereNotNull('ms_user_id')->count();
        $noMsAccounts = (clone $tempBase)->whereNull('ms_user_id')->count();

        $stats = [
            'total' => $totalTemp,
            'total_students' => $totalTemp,
            'f2f_students' => $f2fTemp,
            'flexible_students' => $odlTemp,
            'passwords_changed' => $passwordsChanged,
            'passwords_temp' => $passwordsTemp,
            'no_ms_accounts' => $noMsAccounts,
            'active_ms' => (clone $tempBase)->where('ms_license_active', true)->whereNotNull('ms_user_id')->count(),
            'no_account' => $noMsAccounts,
            'password_changed' => $passwordsChanged,
        ];

        $passwordByGrade = DB::table('students')
            ->leftJoin('enrollment_applicants', 'enrollment_applicants.id', '=', 'students.enrollment_applicant_id')
            ->whereNull('students.deleted_at')
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))
            ->where(function ($q) {
                $q->where('enrollment_applicants.last_name', 'like', '%[TEMP%')
                  ->orWhere('enrollment_applicants.first_name', 'like', '%[TEMP%')
                  ->orWhere('students.school_email', 'like', '%temp%');
            })
            ->select(
                'students.grade_level',
                DB::raw('COUNT(students.id) as total'),
                DB::raw("COUNT(CASE WHEN LOWER(enrollment_applicants.learning_mode) LIKE '%face%' OR LOWER(enrollment_applicants.learning_mode) LIKE '%f2f%' THEN 1 END) as f2f"),
                DB::raw("COUNT(CASE WHEN LOWER(enrollment_applicants.learning_mode) LIKE '%flexible%' OR LOWER(enrollment_applicants.learning_mode) LIKE '%online%' OR LOWER(enrollment_applicants.learning_mode) LIKE '%odl%' THEN 1 END) as odl"),
                DB::raw('COUNT(CASE WHEN students.password_changed_at IS NOT NULL THEN 1 END) as changed'),
                DB::raw('COUNT(CASE WHEN students.password_changed_at IS NULL AND students.ms_user_id IS NOT NULL THEN 1 END) as temp'),
                DB::raw('COUNT(CASE WHEN students.ms_user_id IS NULL THEN 1 END) as no_account')
            )
            ->groupBy('students.grade_level')
            ->get();

        $analytics = [
            'total_students' => $totalTemp,
            'filtered_total' => $isPrint ? count($students) : $students->total(),
            'password_by_grade' => $passwordByGrade,
        ];

        return view('admin.students.temporary', compact('students', 'stats', 'analytics', 'gradeOrder', 'isPrint'));
    }

    public function toggleTag(Request $request, Student $student)
    {
        $applicant = $student->applicant;
        if (! $applicant) {
            return back()->with('error', 'Student applicant record not found.');
        }

        $user = $student->user ?? $applicant->user;
        $isCurrentlyTagged = str_contains($applicant->last_name, '[TEMP') || ($user && str_contains($user->name, '[TEMP'));

        if ($isCurrentlyTagged) {
            $cleanedLastName = trim(preg_replace('/\s*\[TEMP[^\]]*\]/i', '', $applicant->last_name));
            $applicant->last_name = $cleanedLastName;
            $applicant->saveQuietly();

            if ($user) {
                $user->name = trim(preg_replace('/\s*\[TEMP[^\]]*\]/i', '', $user->name));
                $user->saveQuietly();
            }

            $this->syncAzureName($student, $user?->name, $cleanedLastName);

            AdminAuditLog::record('temporary_student_cleared', true, "Removed temporary tag from student #{$student->student_number} ({$applicant->full_name}). Returned to standard student records.");

            return back()->with('success', "Temporary tag removed for student #{$student->student_number}. Account returned to standard student records.");
        } else {
            $newLastName = trim($applicant->last_name . ' [TEMP ACC]');
            $applicant->last_name = $newLastName;
            $applicant->saveQuietly();

            if ($user) {
                $user->name = trim($user->name . ' [TEMP ACC]');
                $user->saveQuietly();
            }

            $this->syncAzureName($student, $user?->name, $newLastName);

            AdminAuditLog::record('temporary_student_tagged', true, "Marked student #{$student->student_number} ({$applicant->full_name}) as Temporary Account [TEMP ACC].");

            return back()->with('success', "Student #{$student->student_number} tagged as Temporary Account [TEMP ACC].");
        }
    }

    public function bulkToggleTag(Request $request)
    {
        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
            'action' => 'required|in:tag,untag',
        ]);

        $action = $validated['action'];
        $students = Student::with(['applicant', 'user'])->whereIn('id', $validated['student_ids'])->get();
        $count = 0;

        foreach ($students as $student) {
            $applicant = $student->applicant;
            if (! $applicant) continue;

            $user = $student->user ?? $applicant->user;

            if ($action === 'tag') {
                if (! str_contains($applicant->last_name, '[TEMP')) {
                    $applicant->last_name = trim($applicant->last_name . ' [TEMP ACC]');
                    $applicant->saveQuietly();

                    if ($user) {
                        $user->name = trim(preg_replace('/\s*\[TEMP[^\]]*\]/i', '', $user->name) . ' [TEMP ACC]');
                        $user->saveQuietly();
                    }

                    $this->syncAzureName($student, $user?->name, $applicant->last_name);
                    $count++;
                }
            } else {
                if (str_contains($applicant->last_name, '[TEMP') || ($user && str_contains($user->name, '[TEMP'))) {
                    $cleanedLastName = trim(preg_replace('/\s*\[TEMP[^\]]*\]/i', '', $applicant->last_name));
                    $applicant->last_name = $cleanedLastName;
                    $applicant->saveQuietly();

                    if ($user) {
                        $user->name = trim(preg_replace('/\s*\[TEMP[^\]]*\]/i', '', $user->name));
                        $user->saveQuietly();
                    }

                    $this->syncAzureName($student, $user?->name, $cleanedLastName);
                    $count++;
                }
            }
        }

        $actionWord = $action === 'tag' ? 'moved to Temporary Accounts [TEMP ACC]' : 'cleared of [TEMP ACC] tag and returned to standard Student Records';
        AdminAuditLog::record('temporary_student_bulk_' . $action, true, "Bulk {$actionWord} for {$count} student records.");

        return back()->with('success', "Successfully {$actionWord} for {$count} student(s).");
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $query = Student::with(['applicant.user', 'applicant.payment', 'studentSection.section'])
            ->whereNull('students.deleted_at')
            ->where(function ($q) {
                $q->whereHas('applicant', function ($a) {
                    $a->where('last_name', 'like', '%[TEMP%')
                      ->orWhere('first_name', 'like', '%[TEMP%');
                })->orWhereHas('user', function ($u) {
                    $u->where('name', 'like', '%[TEMP%');
                })->orWhere('students.school_email', 'like', '%temp%');
            });

        $filename = 'amis_temporary_students_' . date('Y_m_d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Student Number',
                'Last Name',
                'First Name',
                'Middle Name',
                'Grade Level',
                'Section',
                'Learning Mode',
                'Gender',
                'School Email',
                '2x2 Photo',
                'PSA Birth Certificate',
                'Report Card',
                'Enrollment Fee Receipt',
                'Requirements Completion',
            ]);

            $query->chunk(100, function ($students) use ($handle) {
                foreach ($students as $st) {
                    $app = $st->applicant;
                    $hasPhoto = ! empty($app?->photo_2x2_url);
                    $hasBirthCert = ! empty($app?->birth_cert_url);
                    $hasReportCard = ! empty($app?->report_card_url);
                    $hasReceipt = ! empty($app?->enrollment_fee_receipt_url);
                    $docCount = ($hasPhoto ? 1 : 0) + ($hasBirthCert ? 1 : 0) + ($hasReportCard ? 1 : 0) + ($hasReceipt ? 1 : 0);

                    fputcsv($handle, [
                        $st->student_number,
                        $app?->last_name ?? '',
                        $app?->first_name ?? '',
                        $app?->middle_name ?? '',
                        $st->grade_level ?? '',
                        $st->section ?? 'Unassigned',
                        $app?->learning_mode ?? 'ODL',
                        $app?->gender ?? 'Unspecified',
                        $st->school_email ?? '',
                        $hasPhoto ? 'Uploaded' : 'Missing',
                        $hasBirthCert ? 'Uploaded' : 'Missing',
                        $hasReportCard ? 'Uploaded' : 'Missing',
                        $hasReceipt ? 'Uploaded' : 'Missing',
                        "{$docCount} of 4",
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function syncAzureName(Student $student, ?string $displayName, ?string $surname): void
    {
        if (empty($student->school_email) && empty($student->ms_user_id)) {
            return;
        }

        try {
            $graph = new MicrosoftGraphService();
            $upnOrId = $student->ms_user_id ?: $student->school_email;

            $payload = [];
            if (! empty($displayName)) {
                $payload['displayName'] = $displayName;
            }
            if (! empty($surname)) {
                $payload['surname'] = $surname;
            }

            if (! empty($payload)) {
                $graph->updateAzureUser($upnOrId, $payload);
            }
        } catch (\Exception $e) {
            Log::warning("TemporaryStudentController: Failed to sync Azure user name for student {$student->student_number}: " . $e->getMessage());
        }
    }
}
