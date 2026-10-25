<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSection;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $mode = $request->query('mode');
        $grade = $request->query('grade');

        $activeSy = app(\App\Services\SchoolYearService::class)->current();

        $query = Section::withCount([
            'students' => function ($q) use ($activeSy) {
                $q->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), function ($sq) use ($activeSy) {
                    $sq->whereHas('student', fn ($ssq) => $ssq->where('students.school_year', $activeSy));
                });
            }
        ])->with([
            'activeAdvisory.user',
            'coAdvisors.user',
            'students' => function ($q) use ($activeSy) {
                $q->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), function ($sq) use ($activeSy) {
                    $sq->whereHas('student', fn ($ssq) => $ssq->where('students.school_year', $activeSy));
                })->with(['student.applicant']);
            }
        ]);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('official_name', 'like', "%{$search}%")
                    ->orWhere('room_number', 'like', "%{$search}%");
            });
        }

        if (!empty($mode)) {
            if ($mode === 'f2f') {
                $query->where(function ($q) {
                    $q->where('learning_mode', 'like', '%face%')
                        ->orWhere('learning_mode', 'like', '%f2f%');
                });
            } elseif ($mode === 'odl') {
                $query->where(function ($q) {
                    $q->where('learning_mode', 'like', '%online%')
                        ->orWhere('learning_mode', 'like', '%odl%');
                });
            }
        }

        if (!empty($grade)) {
            $query->where('grade_level', $grade);
        }

        $sections = $query->orderBy('grade_level', 'asc')
            ->orderBy('name', 'asc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.students.occupancy', compact('sections'));
    }

    public function gradeIdPrint(Request $request, $grade)
    {
        $grade = urldecode($grade);

        $query = Student::where(function($q) use ($grade) {
            $q->where('grade_level', $grade)
              ->orWhere('grade_level', str_replace('Grade ', 'G', $grade))
              ->orWhere('grade_level', str_replace('G', 'Grade ', $grade));
            
            if (str_contains(strtolower($grade), 'kinder 1') || str_contains(strtolower($grade), 'k1')) {
                $q->orWhere('grade_level', 'K1')->orWhere('grade_level', 'Kinder 1')->orWhere('grade_level', 'K1 (Kinder 1)');
            }
            if (str_contains(strtolower($grade), 'kinder 2') || str_contains(strtolower($grade), 'k2')) {
                $q->orWhere('grade_level', 'K2')->orWhere('grade_level', 'Kinder 2')->orWhere('grade_level', 'K2 (Kinder 2)');
            }
            if (str_contains(strtolower($grade), 'nursery')) {
                $q->orWhere('grade_level', 'Nursery');
            }
        })
        ->with(['applicant', 'studentSection.section']);

        $students = $query->get()
            ->sortBy(function ($student) {
                $applicant = $student->applicant;
                $lastName = strtoupper(trim($applicant?->last_name ?? ''));
                $firstName = strtoupper(trim($applicant?->first_name ?? ''));
                $middleName = strtoupper(trim($applicant?->middle_name ?? ''));

                return $lastName.' '.$firstName.' '.$middleName;
            })->values();

        if ($students->isEmpty()) {
            // Fallback: search by applicant's grade_level
            $applicantIds = \App\Models\EnrollmentApplicant::where(function($q) use ($grade) {
                $q->where('grade_level', $grade)
                  ->orWhere('grade_level', 'LIKE', '%' . $grade . '%');
            })->pluck('id');
            
            $students = Student::whereIn('enrollment_applicant_id', $applicantIds)
                ->with(['applicant', 'studentSection.section'])
                ->get()
                ->sortBy(function ($student) {
                    $applicant = $student->applicant;
                    return strtoupper(trim($applicant?->last_name ?? '')) . ' ' . strtoupper(trim($applicant?->first_name ?? ''));
                })->values();
        }

        if ($students->isEmpty()) {
            abort(404, 'No enrolled students found for grade level: ' . $grade);
        }

        return view('admin.students.grade-id-print', compact('students', 'grade'));
    }

    public function idRosterPrint(Request $request, Section $section)
    {
        $section->load([
            'students.student.applicant',
            'activeAdvisory.user',
        ]);

        return view('admin.students.section-id-roster-print', compact('section'));
    }
}
