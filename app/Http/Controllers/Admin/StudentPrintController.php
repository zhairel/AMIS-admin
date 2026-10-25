<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentApplicant;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentPrintController extends Controller
{
    public function printEnrolmentForm(Student $student)
    {
        abort_unless(auth()->user()?->canViewAdminGrade($student->grade_level), 403);

        try {
            $relations = ['applicant.user', 'applicant.payment', 'studentSection.section'];
            if (\Illuminate\Support\Facades\Schema::hasTable('student_documents')) {
                $relations[] = 'officialEnrollmentForm';
            }
            $student->loadMissing($relations);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Could not eager load relations for student {$student->id}: ".$e->getMessage());
        }

        $applicant = null;
        try {
            $applicant = $student->applicant;
        } catch (\Throwable $e) {
            // fallback
        }

        $isApproved = ($applicant?->status === 'approved');

        $siblings = collect();
        if ($applicant && $applicant->user_id) {
            try {
                $siblings = EnrollmentApplicant::where('user_id', $applicant->user_id)
                    ->where('id', '!=', $applicant->id)
                    ->get();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Could not query siblings for applicant {$applicant->id}: ".$e->getMessage());
            }
        }

        // If approved, ensure official permanent PDF is generated
        $officialDoc = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('student_documents')) {
            try {
                $officialDoc = $student->officialEnrollmentForm;
                if ($isApproved && ! $officialDoc && $applicant) {
                    $docService = app(\App\Services\Admin\Enrollment\EnrollmentDocumentService::class);
                    $officialDoc = $docService->generateApprovedEnrollmentForm($student, $applicant, auth()->id());
                    $docService->queueRequirements($student, $applicant);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Could not auto-generate missing official PDF for student {$student->id}: ".$e->getMessage());
            }
        }

        return view('admin.students.print-enrolment-form', [
            'student' => $student,
            'applicant' => $applicant,
            'siblings' => $siblings,
            'isApproved' => $isApproved,
            'officialDocument' => $officialDoc,
        ]);
    }

    public function downloadOfficialForm(Student $student, \Illuminate\Http\Request $request)
    {
        abort_unless(auth()->user()?->canViewAdminGrade($student->grade_level), 403);

        $student->loadMissing(['applicant.user', 'applicant.payment', 'studentSection.section', 'officialEnrollmentForm']);
        $docService = app(\App\Services\Admin\Enrollment\EnrollmentDocumentService::class);

        if ($request->boolean('with_attachments') && $student->applicant) {
            return $docService->generateAndDownloadWithAttachments($student, $student->applicant);
        }

        if ($student->applicant) {
            $officialDoc = $docService->generateApprovedEnrollmentForm($student, $student->applicant, auth()->id(), true);
        } else {
            $officialDoc = $student->officialEnrollmentForm;
        }

        if (! $officialDoc) {
            abort(404, 'Official Enrollment Form has not been finalized yet. The application must be approved first.');
        }

        return $docService->streamOrDownload($officialDoc, true);
    }

    public function viewOfficialForm(Student $student)
    {
        abort_unless(auth()->user()?->canViewAdminGrade($student->grade_level), 403);

        $student->loadMissing(['applicant.user', 'applicant.payment', 'studentSection.section', 'officialEnrollmentForm']);
        $docService = app(\App\Services\Admin\Enrollment\EnrollmentDocumentService::class);

        if ($student->applicant) {
            $officialDoc = $docService->generateApprovedEnrollmentForm($student, $student->applicant, auth()->id(), true);
        } else {
            $officialDoc = $student->officialEnrollmentForm;
        }

        if (! $officialDoc) {
            abort(404, 'Official Enrollment Form has not been finalized yet. The application must be approved first.');
        }

        return $docService->streamOrDownload($officialDoc, false);
    }

    public function downloadDocument(\App\Models\StudentDocument $document)
    {
        abort_unless(auth()->user()?->canViewAdminGrade($document->student?->grade_level), 403);

        $docService = app(\App\Services\Admin\Enrollment\EnrollmentDocumentService::class);
        return $docService->streamOrDownload($document, true);
    }

    public function viewDocument(\App\Models\StudentDocument $document)
    {
        abort_unless(auth()->user()?->canViewAdminGrade($document->student?->grade_level), 403);

        $docService = app(\App\Services\Admin\Enrollment\EnrollmentDocumentService::class);
        return $docService->streamOrDownload($document, false);
    }

    public function printEnrolmentFormsBatch(Request $request)
    {
        ini_set('memory_limit', '1024M');
        $activeSy = app(\App\Services\SchoolYearService::class)->current();
        $query = Student::with([
            'applicant.user',
            'applicant.payment',
            'studentSection.section',
            'officialEnrollmentForm'
        ])->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy));

        if ($request->filled('section_id')) {
            $query->whereHas('studentSection', function ($q) use ($request) {
                $q->where('section_id', $request->section_id);
            });
        } elseif ($request->filled('grade')) {
            $query->where('students.grade_level', $request->grade);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('mode')) {
            $mode = $request->mode;
            $query->whereHas('applicant', fn ($q) => $q->where('learning_mode', 'like', "%{$mode}%"));
        }

        if ($request->filled('gender')) {
            $gender = strtolower((string) $request->gender);
            if (in_array($gender, ['male', 'female'], true)) {
                $query->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(gender) = ?', [$gender]));
            }
        }

        $gradeOrder = ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
        $students = $query
            ->leftJoin('enrollment_applicants as sort_ea', 'sort_ea.id', '=', 'students.enrollment_applicant_id')
            ->select('students.*')
            ->orderByRaw('FIELD(students.grade_level, '.implode(',', array_fill(0, count($gradeOrder), '?')).')', $gradeOrder)
            ->orderBy('sort_ea.last_name', 'asc')
            ->orderBy('sort_ea.first_name', 'asc')
            ->get();

        $userIds = $students->pluck('applicant.user_id')->filter()->unique();
        $allSiblings = $userIds->isNotEmpty() ? EnrollmentApplicant::withoutGlobalScopes()->whereIn('user_id', $userIds)->get()->groupBy('user_id') : collect();

        $siblingsMap = [];
        foreach ($students as $s) {
            $app = $s->applicant;
            if ($app && $app->user_id) {
                $siblingsMap[$s->id] = ($allSiblings[$app->user_id] ?? collect())->reject(fn ($a) => $a->id === $app->id);
            } else {
                $siblingsMap[$s->id] = collect();
            }
        }

        $section = $request->filled('section_id') ? Section::find($request->section_id) : null;
        $gradeTitle = $section ? ($section->grade_level.' - '.($section->official_name ?: $section->name)) : ($request->grade ?: 'All Grades');

        if ($request->get('download') === 'pdf' || $request->get('format') === 'pdf' || $request->get('action') === 'batch_pdf') {
            $docService = app(\App\Services\Admin\Enrollment\EnrollmentDocumentService::class);
            $pdfOutput = $docService->generateBatchGradeEnrollmentPdf($students, $gradeTitle);
            $gradeClean = preg_replace('/[^A-Za-z0-9]+/', '-', trim($gradeTitle ?: 'All-Grades'));
            $filename = "AMIS-Enrollment-Forms-{$gradeClean}-SY-2026-2027.pdf";

            return response($pdfOutput, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        return view('admin.students.print-enrolment-form-batch', [
            'students' => $students,
            'gradeTitle' => $gradeTitle,
            'siblingsMap' => $siblingsMap,
        ]);
    }

    public function bulkPrintList(Request $request)
    {
        $request->validate([
            'student_numbers' => 'required|string',
            'print_type' => 'required|in:print_id,print_info,print_credentials',
        ]);

        $raw = $request->input('student_numbers');
        $numbers = array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[\r\n,;\t]+/', $raw))
        )));

        abort_if(empty($numbers), 422, 'No student numbers provided.');
        abort_if(count($numbers) > 500, 422, 'Maximum 500 students per bulk print.');

        $students = Student::with(['applicant.user', 'studentSection.section'])
            ->whereIn('student_number', $numbers)
            ->when(auth()->user()?->isTeacherAdminViewer(), fn ($q) => $q->whereIn('grade_level', auth()->user()->adminVisibleGradeLevels())
            )
            ->leftJoin('enrollment_applicants as sort_ea', 'sort_ea.id', '=', 'students.enrollment_applicant_id')
            ->select('students.*')
            ->orderByRaw('FIELD(students.student_number, '.implode(',', array_fill(0, count($numbers), '?')).')', $numbers)
            ->get();

        $isPrint = true;
        $printType = $request->input('print_type');

        return view('admin.students.index', compact('students', 'isPrint'))->with([
            'stats' => [],
            'analytics' => [],
            'gradeOrder' => ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'],
            $printType => true,
        ]);
    }

    public function printExport(Request $request)
    {
        $isTeacherAdminViewer = $request->user()?->isTeacherAdminViewer() ?? false;
        $visibleGrades = $isTeacherAdminViewer ? $request->user()->adminVisibleGradeLevels() : [];
        $teacherGradeScope = null;
        if ($isTeacherAdminViewer && ! empty($visibleGrades)) {
            $teacherGradeScope = $visibleGrades[0];
            if ($request->filled('grade') && in_array((string) $request->input('grade'), $visibleGrades, true)) {
                $teacherGradeScope = (string) $request->input('grade');
            } elseif ($request->filled('grade')) {
                $teacherGradeScope = null;
            }
        }

        $activeSy = app(\App\Services\SchoolYearService::class)->current();
        $query = Student::query()
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy));

        if ($isTeacherAdminViewer) {
            $teacherGradeScope === null
                ? $query->whereRaw('1 = 0')
                : $query->where('students.grade_level', $teacherGradeScope);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $terms = array_filter(explode(' ', $s));
            $query->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->where(function ($sub) use ($term) {
                        $sub->where('students.student_number', 'like', "%{$term}%")
                            ->orWhere('students.school_email', 'like', "%{$term}%")
                            ->orWhereHas('applicant', function ($a) use ($term) {
                                $a->where('first_name', 'like', "%{$term}%")
                                    ->orWhere('middle_name', 'like', "%{$term}%")
                                    ->orWhere('last_name', 'like', "%{$term}%");
                            });
                    });
                }
            });
        }

        if ($request->filled('grade')) {
            $query->where('students.grade_level', $request->grade);
        }

        if ($request->filled('mode')) {
            $mode = trim($request->mode);
            $query->whereHas('applicant', fn ($q) => $q->where('learning_mode', 'like', "%{$mode}%"));
        }

        if ($request->filled('gender')) {
            $gender = strtolower((string) $request->gender);
            if (in_array($gender, ['male', 'female'], true)) {
                $query->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(gender) = ?', [$gender]));
            }
        }

        $totalStudents = (clone $query)->count();
        $previewStudents = (clone $query)->with(['applicant', 'officialEnrollmentForm', 'studentSection.section'])
            ->leftJoin('enrollment_applicants', 'students.enrollment_applicant_id', '=', 'enrollment_applicants.id')
            ->select('students.*')
            ->orderBy('enrollment_applicants.last_name')
            ->orderBy('enrollment_applicants.first_name')
            ->paginate(15)
            ->withQueryString();

        $gradeCounts = Student::query()
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))
            ->selectRaw('grade_level, count(*) as total')
            ->groupBy('grade_level')
            ->pluck('total', 'grade_level')
            ->toArray();

        $f2fCount = (clone $query)->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(learning_mode) LIKE ?', ['%face%'])->orWhereRaw('LOWER(learning_mode) LIKE ?', ['%f2f%']))->count();
        $odlCount = (clone $query)->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(learning_mode) LIKE ?', ['%online%'])->orWhereRaw('LOWER(learning_mode) LIKE ?', ['%odl%'])->orWhereRaw('LOWER(learning_mode) LIKE ?', ['%flexible%']))->count();

        $currentGrade = (string) $request->input('grade', '');
        $currentMode = (string) $request->input('mode', '');
        $currentGender = (string) $request->input('gender', '');
        $currentSearch = (string) $request->input('search', '');

        return view('admin.students.print-export', compact('totalStudents', 'previewStudents', 'gradeCounts', 'f2fCount', 'odlCount', 'currentGrade', 'currentMode', 'currentGender', 'currentSearch'));
    }

    public function previewDocxEnrolmentForm(Student $student)
    {
        abort_unless(auth()->user()?->canViewAdminGrade($student->grade_level), 403);

        $student->load([
            'applicant.user',
            'applicant.payment',
            'studentSection.section',
        ]);

        $applicant = $student->applicant;
        $siblings = [];
        if ($applicant && $applicant->user_id) {
            $siblings = EnrollmentApplicant::where('user_id', $applicant->user_id)
                ->where('id', '!=', $applicant->id)
                ->get();
        }

        return view('admin.students.print-enrolment-form', [
            'student' => $student,
            'applicant' => $applicant,
            'siblings' => $siblings,
            'autoDocx' => true,
        ]);
    }
}
