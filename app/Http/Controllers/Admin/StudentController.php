<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\EnrollmentApplicant;
use App\Models\Section;
use App\Models\Student;
use App\Services\Admin\Enrollment\EnrollmentReviewService;
use App\Services\MicrosoftGraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        if (auth()->user()?->isTeacherAdminViewer() && $request->filled('print_credentials')) {
            abort(403);
        }

        $gradeOrder = ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
        $isTeacherAdminViewer = $request->user()?->isTeacherAdminViewer() ?? false;
        $visibleGrades = $isTeacherAdminViewer
            ? $request->user()->adminVisibleGradeLevels()
            : [];
        $teacherGradeScope = null;

        if ($isTeacherAdminViewer) {
            $gradeOrder = $visibleGrades;

            if (! empty($visibleGrades)) {
                $teacherGradeScope = $visibleGrades[0];

                if ($request->filled('grade') && in_array((string) $request->input('grade'), $visibleGrades, true)) {
                    $teacherGradeScope = (string) $request->input('grade');
                } elseif ($request->filled('grade')) {
                    $teacherGradeScope = null;
                }
            }
        }

        $applyFilters = function ($query) use ($request, $isTeacherAdminViewer, $teacherGradeScope) {
            $activeSy = $request->input('school_year', session('admin_school_year'));
            if ($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy)) {
                $query->where('students.school_year', $activeSy);
            }

            if ($isTeacherAdminViewer) {
                $teacherGradeScope === null
                    ? $query->whereRaw('1 = 0')
                    : $query->where('students.grade_level', $teacherGradeScope);
            }

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
                $query->search($request->search);
            }

            if ($request->filled('grade') && (! $isTeacherAdminViewer || $teacherGradeScope !== null)) {
                $query->where('students.grade_level', $request->grade);
            }

            if ($request->filled('gender')) {
                $gender = strtolower($request->gender);
                $query->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(gender) = ?', [$gender]));
            }

            if ($request->filled('learning_mode')) {
                $mode = strtolower($request->learning_mode);
                $query->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(learning_mode) LIKE ?', ["%{$mode}%"]));
            }

            if ($request->filled('student_type')) {
                $type = strtolower($request->student_type);
                $query->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(student_type) LIKE ?', ["%{$type}%"]));
            }

            if ($request->filled('ms_status')) {
                $status = $request->ms_status;
                if ($status === 'active') {
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

            if ($request->filled('section_status') || $request->filled('section')) {
                $secStatus = $request->input('section_status', $request->input('section'));
                if ($secStatus === 'unassigned') {
                    $query->where(function ($q) {
                        $q->whereNull('students.section')->orWhere('students.section', '');
                    });
                } elseif ($secStatus === 'assigned') {
                    $query->whereNotNull('students.section')->where('students.section', '!=', '');
                }
            }

            // Exclude temporary accounts from standard student records (managed in dedicated Temporary Records page)
            if (! $request->boolean('include_temp', false)) {
                $query->whereDoesntHave('applicant', function ($q) {
                    $q->where('last_name', 'like', '%[TEMP%')
                      ->orWhere('first_name', 'like', '%[TEMP%');
                })->whereDoesntHave('user', function ($q) {
                    $q->where('name', 'like', '%[TEMP%');
                });
            }

            return $query;
        };

        $query = $applyFilters(Student::with(['applicant.user', 'studentSection.section']));

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

        $isPrint = $request->boolean('print_info') || $request->boolean('print_credentials') || $request->boolean('print_id') || $request->boolean('print_documents');

        if ($isPrint) {
            $students = $query->get();
        } else {
            $students = $query->paginate(25)->withQueryString();
        }

        $baseStudentQuery = fn () => Student::query()
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy));

        $totalStudents = $baseStudentQuery()->count();
        $f2fStudents = $baseStudentQuery()->whereHas('applicant', function ($q) {
            $q->where('learning_mode', 'like', '%face-to-face%')
                ->orWhere('learning_mode', 'like', '%f2f%')
                ->orWhere('learning_mode', 'like', '%face_to_face%');
        })->count();
        $flexibleStudents = $baseStudentQuery()->whereHas('applicant', function ($q) {
            $q->where('learning_mode', 'like', '%flexible%')
                ->orWhere('learning_mode', 'like', '%online%')
                ->orWhere('learning_mode', 'like', '%odl%');
        })->count();
        $passwordsChanged = $baseStudentQuery()->whereNotNull('password_changed_at')->count();
        $passwordsTemp = $baseStudentQuery()->whereNull('password_changed_at')->whereNotNull('ms_user_id')->count();
        $noMsAccounts = $baseStudentQuery()->whereNull('ms_user_id')->count();

        $stats = [
            'total' => $totalStudents,
            'total_students' => $totalStudents,
            'f2f_students' => $f2fStudents,
            'flexible_students' => $flexibleStudents,
            'passwords_changed' => $passwordsChanged,
            'passwords_temp' => $passwordsTemp,
            'no_ms_accounts' => $noMsAccounts,
            'active_ms' => $baseStudentQuery()->where('ms_license_active', true)->whereNotNull('ms_user_id')->count(),
            'no_account' => $noMsAccounts,
            'password_changed' => $passwordsChanged,
            'temp_accounts' => $baseStudentQuery()->whereNull('deleted_at')->where(function ($q) {
                $q->whereHas('applicant', function ($a) {
                    $a->where('last_name', 'like', '%[TEMP%')
                      ->orWhere('first_name', 'like', '%[TEMP%');
                })->orWhereHas('user', function ($u) {
                    $u->where('name', 'like', '%[TEMP%');
                })->orWhere('school_email', 'like', '%temp%');
            })->count(),
        ];

        $passwordByGrade = DB::table('students')
            ->leftJoin('enrollment_applicants', 'enrollment_applicants.id', '=', 'students.enrollment_applicant_id')
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))
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
            'total_students' => $totalStudents,
            'filtered_total' => $isPrint ? count($students) : $students->total(),
            'password_by_grade' => $passwordByGrade,
        ];

        return view('admin.students.index', compact('students', 'stats', 'analytics', 'gradeOrder', 'isPrint'));
    }

    public function show(Student $student)
    {
        abort_unless(auth()->user()?->canViewAdminGrade($student->grade_level), 403);

        $student->load([
            'applicant.user',
            'applicant.payment',
            'studentSection.section.subjects',
            'account.monthlyBillings',
            'account.payments',
        ]);

        $siblings = EnrollmentApplicant::where(function ($query) use ($student) {
            $userId = $student->applicant?->user_id;
            $parentEmail = $student->applicant?->parent_email;

            if ($userId) {
                $query->where('user_id', $userId);
            }
            if (!empty($parentEmail)) {
                if ($userId) {
                    $query->orWhere('parent_email', $parentEmail);
                } else {
                    $query->where('parent_email', $parentEmail);
                }
            }
        })
        ->where('id', '!=', $student->enrollment_applicant_id)
        ->when(auth()->user()?->isTeacherAdminViewer(), fn ($query) => $query->whereIn('grade_level', auth()->user()->adminVisibleGradeLevels()))
        ->whereNotIn('status', ['draft'])
        ->get();

        $statusLabels = EnrollmentReviewService::STATUS_LABELS;

        $studentEmail = strtolower($student->school_email ?? '');
        $studentNumber = $student->student_number;
        $applicant = $student->applicant;
        $fullName = $applicant ? trim(($applicant->first_name ?? '').' '.($applicant->last_name ?? '')) : '';

        $auditLogs = AdminAuditLog::with('user')
            ->where(function ($query) use ($student, $studentEmail, $studentNumber, $fullName) {
                $query->where('metadata->student_id', $student->id)
                    ->orWhere('metadata->student_number', $studentNumber)
                    ->orWhere('metadata->applicant_id', $student->enrollment_applicant_id);

                if ($studentEmail !== '') {
                    $query->orWhere('message', 'like', "%{$studentEmail}%")
                        ->orWhere('metadata->email', $studentEmail)
                        ->orWhere('metadata->school_email', $studentEmail);
                }

                if (! empty($studentNumber)) {
                    $query->orWhere('message', 'like', "%{$studentNumber}%");
                }

                if (! empty($fullName)) {
                    $query->orWhere('message', 'like', "%{$fullName}%");
                }
            })
            ->latest()
            ->take(100)
            ->get();

        $sections = Section::orderBy('grade_level')->orderBy('name')->get();

        $sectionId = $student->studentSection?->section_id;
        if ($sectionId) {
            $siblingsQuery = Student::whereHas('studentSection', function ($q) use ($sectionId) {
                $q->where('section_id', $sectionId);
            });
        } else {
            $siblingsQuery = Student::where('grade_level', $student->grade_level);
        }

        $orderedStudents = $siblingsQuery->orderBy('id', 'asc')->pluck('id')->toArray();
        $currentIndex = array_search($student->id, $orderedStudents);

        $prevStudentId = ($currentIndex !== false && isset($orderedStudents[$currentIndex - 1])) ? $orderedStudents[$currentIndex - 1] : null;
        $nextStudentId = ($currentIndex !== false && isset($orderedStudents[$currentIndex + 1])) ? $orderedStudents[$currentIndex + 1] : null;

        return view('admin.students.show', [
            'student' => $student,
            'siblings' => $siblings,
            'statusLabels' => $statusLabels,
            'auditLogs' => $auditLogs,
            'sections' => $sections,
            'prevStudentId' => $prevStudentId,
            'nextStudentId' => $nextStudentId,
        ]);
    }

    public function toggleRequirementsLock(Request $request, Student $student)
    {
        $student->is_requirements_locked = ! $student->is_requirements_locked;
        $student->save();

        $action = $student->is_requirements_locked ? 'locked as COMPLETED INFORMATION' : 'unlocked';

        AdminAuditLog::create([
            'user_id' => auth()->id(),
            'event' => 'student_requirements_lock',
            'message' => "Student {$student->student_number} requirements manually {$action} by ".(auth()->user()->name ?? 'Admin'),
            'metadata' => [
                'student_id' => $student->id,
                'student_number' => $student->student_number,
                'is_requirements_locked' => $student->is_requirements_locked,
            ],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', "Student {$student->student_number} requirements status has been {$action}.");
    }

    public function destroy(Student $student)
    {
        $name = $student->student_number.' ('.$student->school_email.')';
        $msError = null;

        if ($student->ms_user_id) {
            try {
                (new MicrosoftGraphService)->deleteAzureUser($student->ms_user_id);
            } catch (\Throwable $e) {
                $msError = $e->getMessage();
                Log::error("Failed to delete Azure AD user for student {$student->id}: ".$msError);
            }
        }

        DB::transaction(function () use ($student) {
            if ($student->account) {
                $student->account->payments()->delete();
                $student->account->monthlyBillings()->delete();
                $student->account->delete();
            }

            if ($student->applicant) {
                $student->applicant->update([
                    'status' => 'under_review',
                    'review_remarks' => 'Student record was deleted. Re-review required.',
                ]);
            }

            $student->delete();
        });

        AdminAuditLog::record(
            event: 'delete_student_record',
            successful: true,
            message: "Deleted student record for {$name}",
            metadata: [
                'student_id' => $student->id,
                'student_number' => $student->student_number,
                'school_email' => $student->school_email,
            ]
        );

        if ($msError) {
            return redirect()->route('admin.students.index')
                ->with('warning', "Student {$name} deleted from portal, but Azure AD deletion failed: {$msError}");
        }

        return redirect()->route('admin.students.index')
            ->with('success', "Student {$name} deleted from portal and Microsoft 365.");
    }

    public function updateField(Request $request)
    {
        $studentNumber = $request->input('student_number');
        $field = $request->input('field');
        $value = $request->input('value');

        $student = Student::where('student_number', $studentNumber)->first();
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Student not found'], 404);
        }

        $applicant = $student->applicant;
        if (! $applicant) {
            return response()->json(['success' => false, 'message' => 'Applicant profile not found'], 404);
        }

        switch ($field) {
            case 'remarks':
                $applicant->review_remarks = $value;
                break;

            case 'status':
                $applicant->status = $value ? 'approved' : 'under_review';
                break;

            case 'photo':
                if (! $value) {
                    $applicant->photo_2x2_url = null;
                } else {
                    $applicant->photo_2x2_url = $applicant->photo_2x2_url ?: 'storage/uploads/photo_placeholder.jpg';
                }
                break;

            case 'lrn':
                if (! $value) {
                    $applicant->lrn = 'NA';
                } else {
                    $applicant->lrn = ($applicant->lrn && strtoupper($applicant->lrn) !== 'NA' && strtoupper($applicant->lrn) !== 'N/A') ? $applicant->lrn : '466000000000';
                }
                break;

            case 'parents':
                if (! $value) {
                    $applicant->father_first_name = null;
                    $applicant->father_last_name = null;
                    $applicant->mother_first_name = null;
                    $applicant->mother_last_name = null;
                    $applicant->emergency_name = 'Emergency Contact';
                } else {
                    $applicant->father_first_name = $applicant->father_first_name ?: 'FATHER';
                    $applicant->father_last_name = $applicant->father_last_name ?: $applicant->last_name;
                }
                break;

            case 'address':
                if (! $value) {
                    $applicant->address = '';
                    $applicant->street_address = '';
                    $applicant->home_address = '';
                } else {
                    $applicant->address = $applicant->address ?: 'DAVAO CITY';
                }
                break;

            case 'docs':
                if (! $value) {
                    $applicant->birth_cert_url = '';
                    $applicant->report_card_url = '';
                    $applicant->affidavit_url = '';
                } else {
                    $applicant->birth_cert_url = $applicant->birth_cert_url ?: 'storage/uploads/birth_placeholder.pdf';
                }
                break;
        }

        $applicant->save();

        $studentName = trim(($applicant->first_name ?? '').' '.($applicant->last_name ?? ''));
        $valDisplay = is_array($value) ? json_encode($value) : (string) $value;

        AdminAuditLog::record(
            event: 'update_student_field_'.$field,
            successful: true,
            message: "Updated student {$field} to '{$valDisplay}' for student {$studentName} ({$studentNumber})",
            metadata: [
                'student_id' => $student->id,
                'student_number' => $studentNumber,
                'school_email' => $student->school_email,
                'applicant_id' => $applicant->id,
                'field' => $field,
                'value' => $value,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Field updated successfully',
            'has_lrn' => ! empty($applicant->lrn) && strtoupper($applicant->lrn) !== 'N/A' && strtoupper($applicant->lrn) !== 'NA',
            'has_photo' => ! empty($applicant->photo_2x2_url),
            'has_parents' => ! empty($applicant->father_first_name) || ! empty($applicant->mother_first_name) || (! empty($applicant->emergency_name) && strtolower(trim($applicant->emergency_name)) !== 'emergency contact'),
            'has_address' => ! empty($applicant->street_address) || ! empty($applicant->home_address) || ! empty($applicant->address),
            'has_documents' => ! empty($applicant->birth_cert_url) || ! empty($applicant->report_card_url) || ! empty($applicant->affidavit_url),
        ]);
    }

    public function updateProfile(Request $request, Student $student)
    {
        abort_if(auth()->user()?->isTeacherAdminViewer(), 403);

        $request->validate([
            'student_type' => 'nullable|string',
            'grade_level' => 'nullable|string',
            'learning_mode' => 'nullable|string',
            'amis_student_id' => 'nullable|string',
            'first_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'gender' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string|max:255',
            'religion' => 'nullable|string|max:255',
            'ethnicity' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:50',
            'parent_email' => 'nullable|email|max:255',
            'parent_mobile' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'home_address' => 'nullable|string',
            'father_name' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'emergency_name' => 'nullable|string|max:255',
            'emergency_relationship' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:50',
            'emergency_address' => 'nullable|string|max:1000',
            'lrn' => 'nullable|string|max:50',
        ]);

        DB::transaction(function () use ($request, $student) {
            if ($request->filled('grade_level')) {
                $student->update([
                    'grade_level' => $request->grade_level,
                ]);
            }

            $applicant = $student->applicant;
            if (! $applicant) {
                $applicant = EnrollmentApplicant::create([
                    'user_id' => $student->user_id,
                    'first_name' => $request->first_name ? mb_strtoupper($request->first_name) : 'STUDENT',
                    'last_name' => $request->last_name ? mb_strtoupper($request->last_name) : 'PROFILE',
                    'grade_level' => $student->grade_level,
                    'status' => 'approved',
                ]);
                $student->update([
                    'enrollment_applicant_id' => $applicant->id,
                ]);
            }

            $updateData = [];

            if ($request->has('student_type')) {
                $updateData['student_type'] = $request->student_type ? mb_strtoupper($request->student_type) : null;
            }
            if ($request->has('grade_level')) {
                $updateData['grade_level'] = $request->grade_level;
            }
            if ($request->has('learning_mode')) {
                $updateData['learning_mode'] = $request->learning_mode ? mb_strtoupper($request->learning_mode) : null;
            }
            if ($request->has('amis_student_id')) {
                $updateData['amis_student_id'] = $request->amis_student_id ? mb_strtoupper($request->amis_student_id) : null;
            }
            if ($request->filled('first_name')) {
                $updateData['first_name'] = mb_strtoupper($request->first_name);
            }
            if ($request->has('middle_name')) {
                $updateData['middle_name'] = $request->middle_name ? mb_strtoupper($request->middle_name) : null;
            }
            if ($request->filled('last_name')) {
                $updateData['last_name'] = mb_strtoupper($request->last_name);
            }
            if ($request->has('suffix')) {
                $updateData['suffix'] = $request->suffix ? mb_strtoupper($request->suffix) : null;
            }
            if ($request->has('gender')) {
                $updateData['gender'] = $request->gender ? mb_strtoupper($request->gender) : null;
            }
            if ($request->has('date_of_birth')) {
                $updateData['date_of_birth'] = $request->date_of_birth ?: null;
            }
            if ($request->has('place_of_birth')) {
                $updateData['place_of_birth'] = $request->place_of_birth ? mb_strtoupper($request->place_of_birth) : null;
            }
            if ($request->has('religion')) {
                $updateData['religion'] = $request->religion ? mb_strtoupper($request->religion) : null;
            }
            if ($request->has('ethnicity')) {
                $updateData['ethnicity'] = $request->ethnicity ? mb_strtoupper($request->ethnicity) : null;
            }
            if ($request->has('email')) {
                $updateData['email'] = $request->email ? strtolower($request->email) : null;
            }
            if ($request->has('mobile')) {
                $updateData['mobile_number'] = $request->mobile;
            }
            if ($request->has('parent_email')) {
                $updateData['parent_email'] = $request->parent_email ? strtolower($request->parent_email) : null;
            }
            if ($request->has('parent_mobile')) {
                $updateData['parent_mobile'] = $request->parent_mobile;
            }
            if ($request->has('address')) {
                $updateData['address'] = $request->address ? mb_strtoupper($request->address) : null;
                $updateData['street_address'] = $request->address ? mb_strtoupper($request->address) : null;
            }
            if ($request->has('home_address')) {
                $updateData['home_address'] = $request->home_address ? mb_strtoupper($request->home_address) : null;
            }
            if ($request->has('emergency_name')) {
                $updateData['emergency_name'] = $request->emergency_name ? mb_strtoupper($request->emergency_name) : null;
            }
            if ($request->has('emergency_relationship')) {
                $updateData['emergency_relationship'] = $request->emergency_relationship ? mb_strtoupper($request->emergency_relationship) : null;
            }
            if ($request->has('emergency_phone')) {
                $updateData['emergency_phone'] = $request->emergency_phone;
            }
            if ($request->has('emergency_address')) {
                $updateData['emergency_address'] = $request->emergency_address ? mb_strtoupper($request->emergency_address) : null;
            }
            if ($request->has('lrn')) {
                $updateData['lrn'] = $request->lrn ?: 'NA';
            }

            if (! empty($updateData)) {
                $applicant->update($updateData);
            }

            if ($request->filled('father_name')) {
                $parts = explode(' ', trim($request->father_name));
                $applicant->father_last_name = mb_strtoupper(array_pop($parts));
                $applicant->father_first_name = mb_strtoupper(implode(' ', $parts)) ?: 'FATHER';
            }
            if ($request->filled('mother_name')) {
                $parts = explode(' ', trim($request->mother_name));
                $applicant->mother_last_name = mb_strtoupper(array_pop($parts));
                $applicant->mother_first_name = mb_strtoupper(implode(' ', $parts)) ?: 'MOTHER';
            }
            $applicant->save();

            // Sync family-wide shared fields (parents, home address, emergency contact) to all siblings
            $siblingApplicants = EnrollmentApplicant::where(function ($q) use ($applicant) {
                if ($applicant->user_id) {
                    $q->where('user_id', $applicant->user_id);
                }
                if (!empty($applicant->parent_email)) {
                    if ($applicant->user_id) {
                        $q->orWhere('parent_email', $applicant->parent_email);
                    } else {
                        $q->where('parent_email', $applicant->parent_email);
                    }
                }
            })
            ->where('id', '!=', $applicant->id)
            ->get();

            $sharedFamilyUpdates = [];
            $sharedKeys = [
                'parent_email', 'parent_mobile', 'parent_country_code',
                'home_address', 'address', 'street_address',
                'emergency_name', 'emergency_relationship', 'emergency_phone', 'emergency_address',
                'father_first_name', 'father_last_name', 'father_occupation',
                'mother_first_name', 'mother_last_name', 'mother_occupation'
            ];

            foreach ($sharedKeys as $key) {
                if (isset($updateData[$key]) || $applicant->isDirty($key) || $request->filled($key)) {
                    $sharedFamilyUpdates[$key] = $applicant->$key;
                }
            }

            if (!empty($sharedFamilyUpdates) && $siblingApplicants->isNotEmpty()) {
                foreach ($siblingApplicants as $sib) {
                    $sib->update($sharedFamilyUpdates);
                }
            }

            $studentName = trim(($applicant->first_name ?? '').' '.($applicant->last_name ?? ''));
            AdminAuditLog::record(
                event: 'update_student_profile',
                successful: true,
                message: "Updated profile details for student {$studentName} ({$student->student_number})",
                metadata: [
                    'student_id' => $student->id,
                    'student_number' => $student->student_number,
                    'school_email' => $student->school_email,
                    'applicant_id' => $applicant->id,
                    'updated_fields' => array_keys($updateData),
                ]
            );
        });

        return back()->with('success', 'Student profile updated successfully.');
    }
}
