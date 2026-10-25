<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSection;
use Illuminate\Support\Facades\DB;

class StudentDashboardController extends Controller
{
    public function dashboard()
    {
        $activeSy = app(\App\Services\SchoolYearService::class)->current();
        $studentBase = fn () => Student::query()
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy));

        $totalStudents = $studentBase()->count();

        $f2fStudents = $studentBase()->whereHas('applicant', function ($q) {
            $q->where('learning_mode', 'like', '%face-to-face%')
                ->orWhere('learning_mode', 'like', '%f2f%')
                ->orWhere('learning_mode', 'like', '%face_to_face%');
        })->count();

        $flexibleStudents = $studentBase()->whereHas('applicant', function ($q) {
            $q->where('learning_mode', 'like', '%flexible%')
                ->orWhere('learning_mode', 'like', '%online%');
        })->count();

        $msSynced = $studentBase()->whereNotNull('ms_user_id')->count();

        $stats = [
            'total_students' => $totalStudents,
            'f2f_students' => $f2fStudents,
            'flexible_students' => $flexibleStudents,
            'ms_synced' => $msSynced,
            'passwords_changed' => $studentBase()->whereNotNull('password_changed_at')->count(),
            'total_sections' => Section::count(),
            'allocated_slots' => StudentSection::query()
                ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), function ($q) use ($activeSy) {
                    $q->whereHas('student', fn ($sq) => $sq->where('students.school_year', $activeSy));
                })->count(),
        ];

        $sections = Section::with([
            'students' => function ($q) use ($activeSy) {
                $q->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), function ($sq) use ($activeSy) {
                    $sq->whereHas('student', fn ($ssq) => $ssq->where('students.school_year', $activeSy));
                })->with(['student.applicant']);
            },
            'activeAdvisory.user',
        ])->withCount([
            'students' => function ($q) use ($activeSy) {
                $q->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), function ($sq) use ($activeSy) {
                    $sq->whereHas('student', fn ($ssq) => $ssq->where('students.school_year', $activeSy));
                });
            }
        ])->get()->map(function ($section) {
            $isF2f = str_contains(strtolower((string) $section->learning_mode), 'face') ||
                     str_contains(strtolower((string) $section->learning_mode), 'f2f') ||
                     strtoupper((string) $section->shift) === 'F2F';
            $section->is_f2f = $isF2f;
            $section->capacity_limit = $isF2f ? 30 : 45;
            $section->occupied = $section->students_count;
            $section->remaining = max(0, $section->capacity_limit - $section->occupied);
            $section->fill_rate = $section->capacity_limit > 0 ? min(100, round(($section->occupied / $section->capacity_limit) * 100)) : 0;

            return $section;
        });

        $f2fSections = $sections->where('is_f2f', true);
        $flexibleSections = $sections->where('is_f2f', false);

        $f2fStats = [
            'sections_count' => $f2fSections->count(),
            'occupied' => $f2fSections->sum('occupied'),
            'capacity' => $f2fSections->count() * 30,
            'remaining' => max(0, ($f2fSections->count() * 30) - $f2fSections->sum('occupied')),
            'fill_rate' => ($f2fSections->count() * 30) > 0 ? min(100, round(($f2fSections->sum('occupied') / ($f2fSections->count() * 30)) * 100)) : 0,
        ];

        $flexibleStats = [
            'sections_count' => $flexibleSections->count(),
            'occupied' => $flexibleSections->sum('occupied'),
            'capacity' => $flexibleSections->count() * 45,
            'remaining' => max(0, ($flexibleSections->count() * 45) - $flexibleSections->sum('occupied')),
            'fill_rate' => ($flexibleSections->count() * 45) > 0 ? min(100, round(($flexibleSections->sum('occupied') / ($flexibleSections->count() * 45)) * 100)) : 0,
        ];

        $gradeCounts = $studentBase()->select('grade_level', DB::raw('count(*) as count'))
            ->groupBy('grade_level')
            ->orderByRaw("FIELD(grade_level, 'Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12')")
            ->get();

        $studentsCharts = [
            'gender' => [
                'labels' => ['Male', 'Female'],
                'data' => [
                    (int) $studentBase()->whereHas('applicant', fn ($q) => $q->where('gender', 'male'))->count(),
                    (int) $studentBase()->whereHas('applicant', fn ($q) => $q->where('gender', 'female'))->count(),
                ],
            ],
            'mode' => [
                'labels' => ['Face-to-Face', 'Flexible Learning'],
                'data' => [
                    (int) $f2fStudents,
                    (int) $flexibleStudents,
                ],
            ],
            'gradeDistribution' => [
                'labels' => $gradeCounts->pluck('grade_level')->toArray(),
                'data' => $gradeCounts->map(fn ($item) => (int) $item->count)->toArray(),
            ],
        ];

        return view('admin.students.dashboard', compact('stats', 'sections', 'f2fStats', 'flexibleStats', 'studentsCharts'));
    }
}
