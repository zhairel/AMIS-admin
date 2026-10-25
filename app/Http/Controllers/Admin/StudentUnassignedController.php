<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentUnassignedController extends Controller
{
    public function index(Request $request)
    {
        $gradeOrder = ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
        
        $modeFilter = $request->input('mode', 'odl'); // default to ODL
        $gradeFilter = $request->input('grade');
        $shiftFilter = $request->input('shift');
        $genderFilter = $request->input('gender');
        $search = trim((string) $request->input('search', ''));

        $activeSy = app(\App\Services\SchoolYearService::class)->current();

        // Base query for unassigned students (section is null or empty)
        $query = Student::with(['applicant.user', 'studentSection.section'])
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))
            ->where(function ($q) {
                $q->whereNull('students.section')
                  ->orWhere('students.section', '');
            });

        // Mode filter
        if ($modeFilter === 'odl') {
            $query->whereHas('applicant', function ($q) {
                $q->where('learning_mode', 'not like', '%face%')
                  ->where('learning_mode', 'not like', '%f2f%');
            });
        } elseif ($modeFilter === 'f2f') {
            $query->whereHas('applicant', function ($q) {
                $q->where('learning_mode', 'like', '%face%')
                  ->orWhere('learning_mode', 'like', '%f2f%');
            });
        }

        // Grade filter
        if (!empty($gradeFilter)) {
            $query->where('students.grade_level', $gradeFilter);
        }

        // Shift filter
        if (!empty($shiftFilter)) {
            $query->whereHas('applicant', function ($q) use ($shiftFilter) {
                $q->where('learning_mode', 'like', "%{$shiftFilter}%");
            });
        }

        // Gender filter
        if (!empty($genderFilter)) {
            $query->whereHas('applicant', function ($q) use ($genderFilter) {
                $q->whereRaw('LOWER(gender) = ?', [strtolower($genderFilter)]);
            });
        }

        // Search filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('students.student_number', 'like', "%{$search}%")
                  ->orWhere('students.school_email', 'like', "%{$search}%")
                  ->orWhereHas('applicant', function ($a) use ($search) {
                      $a->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('lrn', 'like', "%{$search}%");
                  });
            });
        }

        // Order by grade then name
        $gradeField = "FIELD(students.grade_level, 'Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12')";
        $students = $query
            ->leftJoin('enrollment_applicants as sort_app', 'sort_app.id', '=', 'students.enrollment_applicant_id')
            ->select('students.*')
            ->orderByRaw("CASE WHEN {$gradeField} = 0 THEN 99 ELSE {$gradeField} END ASC")
            ->orderBy('sort_app.last_name', 'asc')
            ->orderBy('sort_app.first_name', 'asc')
            ->paginate(50)
            ->withQueryString();

        // Overall Unassigned Stats
        $baseUnassigned = Student::when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))
            ->where(function ($q) {
                $q->whereNull('section')->orWhere('section', '');
            });

        $totalUnassigned = (clone $baseUnassigned)->count();
        
        $totalUnassignedOdl = (clone $baseUnassigned)->whereHas('applicant', function ($q) {
            $q->where('learning_mode', 'not like', '%face%')
              ->where('learning_mode', 'not like', '%f2f%');
        })->count();

        $totalUnassignedF2f = (clone $baseUnassigned)->whereHas('applicant', function ($q) {
            $q->where('learning_mode', 'like', '%face%')
              ->orWhere('learning_mode', 'like', '%f2f%');
        })->count();

        // Counts per grade for ODL
        $odlByGrade = Student::when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))
            ->where(function ($q) {
                $q->whereNull('section')->orWhere('section', '');
            })
            ->whereHas('applicant', function ($q) {
                $q->where('learning_mode', 'not like', '%face%')
                  ->where('learning_mode', 'not like', '%f2f%');
            })
            ->select('grade_level', DB::raw('count(*) as count'))
            ->groupBy('grade_level')
            ->pluck('count', 'grade_level')
            ->toArray();

        // Active Sections grouped by Grade
        $sections = Section::orderBy('name', 'asc')
            ->get()
            ->groupBy('grade_level');

        // Formatted Text for Group Chat
        $allUnassignedOdl = Student::with('applicant')
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))
            ->where(function ($q) {
                $q->whereNull('section')->orWhere('section', '');
            })
            ->whereHas('applicant', function ($q) {
                $q->where('learning_mode', 'not like', '%face%')
                  ->where('learning_mode', 'not like', '%f2f%');
            })
            ->leftJoin('enrollment_applicants as sort_app', 'sort_app.id', '=', 'students.enrollment_applicant_id')
            ->select('students.*')
            ->orderByRaw("CASE WHEN {$gradeField} = 0 THEN 99 ELSE {$gradeField} END ASC")
            ->orderBy('sort_app.last_name', 'asc')
            ->orderBy('sort_app.first_name', 'asc')
            ->get()
            ->groupBy('grade_level');

        $gcTextLines = [];
        $gcTextLines[] = "Assalamu Alaikum wa Rahmatullahi wa Barakatuh, Dear Class Advisers,";
        $gcTextLines[] = "";
        $gcTextLines[] = "A gentle reminder: The following Online Distance Learning (ODL) students currently do not have an assigned section in the AMIS portal. Please check and advise their official section assignments ASAP:";
        $gcTextLines[] = "";

        foreach ($gradeOrder as $g) {
            if (isset($allUnassignedOdl[$g]) && $allUnassignedOdl[$g]->isNotEmpty()) {
                $gcTextLines[] = "📋 " . strtoupper($g) . " (" . $allUnassignedOdl[$g]->count() . " Students):";
                foreach ($allUnassignedOdl[$g] as $st) {
                    $app = $st->applicant;
                    $name = $app ? trim($app->last_name . ', ' . $app->first_name . ' ' . ($app->middle_name ?: '')) : ('Student #' . $st->student_number);
                    $shift = $app && $app->learning_mode ? $app->learning_mode : 'ODL';
                    $gcTextLines[] = "  * " . $name . " — " . $shift;
                }
                $gcTextLines[] = "";
            }
        }
        $gcTextLines[] = "Jazakumullahu Khayran!";
        $gcTextContent = implode("\n", $gcTextLines);

        return view('admin.students.unassigned', compact(
            'students',
            'gradeOrder',
            'modeFilter',
            'gradeFilter',
            'shiftFilter',
            'genderFilter',
            'search',
            'totalUnassigned',
            'totalUnassignedOdl',
            'totalUnassignedF2f',
            'odlByGrade',
            'sections',
            'gcTextContent'
        ));
    }

    public function assign(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $student = Student::with('applicant')->findOrFail($request->student_id);

        if ($request->filled('section_id')) {
            $section = Section::findOrFail($request->section_id);
            $student->section = $section->name;
            $student->saveQuietly();

            DB::table('student_sections')->updateOrInsert(
                ['student_id' => $student->id],
                [
                    'section_id' => $section->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $msg = "Assigned {$student->applicant?->first_name} {$student->applicant?->last_name} to section {$section->name}.";
        } else {
            $student->section = '';
            $student->saveQuietly();
            DB::table('student_sections')->where('student_id', $student->id)->delete();
            $msg = "Cleared section for {$student->applicant?->first_name} {$student->applicant?->last_name}.";
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }

    public function bulkAssign(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'section_id' => 'required|exists:sections,id',
        ]);

        $section = Section::findOrFail($request->section_id);
        $count = 0;

        foreach ($request->student_ids as $studentId) {
            $student = Student::find($studentId);
            if ($student) {
                $student->section = $section->name;
                $student->saveQuietly();

                DB::table('student_sections')->updateOrInsert(
                    ['student_id' => $student->id],
                    [
                        'section_id' => $section->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $count++;
            }
        }

        $msg = "Successfully assigned {$count} student(s) to section {$section->name}.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'count' => $count, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }

    public function exportCsv(Request $request)
    {
        $modeFilter = $request->input('mode', 'odl');
        $gradeFilter = $request->input('grade');

        $activeSy = app(\App\Services\SchoolYearService::class)->current();
        $query = Student::with('applicant')
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))
            ->where(function ($q) {
                $q->whereNull('section')->orWhere('section', '');
            });

        if ($modeFilter === 'odl') {
            $query->whereHas('applicant', function ($q) {
                $q->where('learning_mode', 'not like', '%face%')
                  ->where('learning_mode', 'not like', '%f2f%');
            });
        } elseif ($modeFilter === 'f2f') {
            $query->whereHas('applicant', function ($q) {
                $q->where('learning_mode', 'like', '%face%')
                  ->orWhere('learning_mode', 'like', '%f2f%');
            });
        }

        if (!empty($gradeFilter)) {
            $query->where('grade_level', $gradeFilter);
        }

        $students = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="unassigned_students_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($students) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Student ID', 'LRN', 'Last Name', 'First Name', 'Middle Name', 'Grade Level', 'Gender', 'Learning Mode', 'Parent Mobile', 'Parent Email']);

            foreach ($students as $st) {
                $app = $st->applicant;
                fputcsv($file, [
                    $st->student_number,
                    $app?->lrn ?? '',
                    $app?->last_name ?? '',
                    $app?->first_name ?? '',
                    $app?->middle_name ?? '',
                    $st->grade_level,
                    $app?->gender ?? '',
                    $app?->learning_mode ?? '',
                    $app?->parent_mobile ?? $app?->mobile_number ?? '',
                    $app?->parent_email ?? '',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
