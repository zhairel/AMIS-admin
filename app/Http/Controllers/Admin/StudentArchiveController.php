<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentArchiveController extends Controller
{
    public function index(Request $request)
    {
        $gradeOrder = ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];

        $gradeFilter = $request->input('grade');
        $modeFilter = $request->input('mode');
        $search = trim((string) $request->input('search', ''));

        $activeSy = app(\App\Services\SchoolYearService::class)->current();

        $query = Student::onlyTrashed()
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))
            ->with(['applicant.user', 'studentSection.section']);

        if (!empty($gradeFilter)) {
            $query->where('students.grade_level', $gradeFilter);
        }

        if (!empty($modeFilter)) {
            if ($modeFilter === 'f2f') {
                $query->whereHas('applicant', function ($q) {
                    $q->where('learning_mode', 'like', '%face%')
                      ->orWhere('learning_mode', 'like', '%f2f%');
                });
            } elseif ($modeFilter === 'odl') {
                $query->whereHas('applicant', function ($q) {
                    $q->where('learning_mode', 'not like', '%face%')
                      ->where('learning_mode', 'not like', '%f2f%');
                });
            }
        }

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

        $archivedStudents = $query
            ->orderBy('deleted_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        $totalArchived = Student::onlyTrashed()
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))
            ->count();

        $archivedByGrade = Student::onlyTrashed()
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))
            ->select('grade_level', DB::raw('count(*) as count'))
            ->groupBy('grade_level')
            ->pluck('count', 'grade_level')
            ->toArray();

        return view('admin.students.archive', compact(
            'archivedStudents',
            'gradeOrder',
            'gradeFilter',
            'modeFilter',
            'search',
            'totalArchived',
            'archivedByGrade'
        ));
    }

    public function archive(Request $request, $id)
    {
        $student = Student::with('applicant')->findOrFail($id);
        $name = $student->applicant ? trim($student->applicant->first_name . ' ' . $student->applicant->last_name) : "Student #{$student->student_number}";

        $student->delete();

        AdminAuditLog::record('student_archived', true, "Archived student record #{$student->student_number} ({$name}).", [
            'student_id' => $student->id,
            'student_number' => $student->student_number,
            'name' => $name,
            'grade_level' => $student->grade_level,
        ]);

        return back()->with('success', "Student {$name} (#{$student->student_number}) has been moved to archive.");
    }

    public function restore(Request $request, $id)
    {
        $student = Student::onlyTrashed()->with('applicant')->findOrFail($id);
        $name = $student->applicant ? trim($student->applicant->first_name . ' ' . $student->applicant->last_name) : "Student #{$student->student_number}";

        $student->restore();

        AdminAuditLog::record('student_restored', true, "Restored student record #{$student->student_number} ({$name}) from archive.", [
            'student_id' => $student->id,
            'student_number' => $student->student_number,
            'name' => $name,
            'grade_level' => $student->grade_level,
        ]);

        return back()->with('success', "Student {$name} (#{$student->student_number}) has been restored successfully.");
    }

    public function bulkArchive(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        $count = 0;
        foreach ($request->student_ids as $id) {
            $student = Student::find($id);
            if ($student) {
                $student->delete();
                $count++;
            }
        }

        AdminAuditLog::record('student_bulk_archived', true, "Bulk archived {$count} student record(s).", [
            'count' => $count,
            'student_ids' => $request->student_ids,
        ]);

        return back()->with('success', "Successfully archived {$count} student(s).");
    }

    public function bulkRestore(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array',
        ]);

        $count = 0;
        foreach ($request->student_ids as $id) {
            $student = Student::onlyTrashed()->find($id);
            if ($student) {
                $student->restore();
                $count++;
            }
        }

        AdminAuditLog::record('student_bulk_restored', true, "Bulk restored {$count} student record(s) from archive.", [
            'count' => $count,
            'student_ids' => $request->student_ids,
        ]);

        return back()->with('success', "Successfully restored {$count} student(s) from archive.");
    }

    public function forceDelete(Request $request, $id)
    {
        $student = Student::onlyTrashed()->with('applicant')->findOrFail($id);
        $name = $student->applicant ? trim($student->applicant->first_name . ' ' . $student->applicant->last_name) : "Student #{$student->student_number}";

        // Remove student sections link
        DB::table('student_sections')->where('student_id', $student->id)->delete();

        $student->forceDelete();

        AdminAuditLog::record('student_permanently_deleted', true, "Permanently deleted student record #{$student->student_number} ({$name}).", [
            'student_id' => $id,
            'student_number' => $student->student_number,
            'name' => $name,
        ]);

        return back()->with('success', "Student {$name} has been permanently deleted.");
    }
}
