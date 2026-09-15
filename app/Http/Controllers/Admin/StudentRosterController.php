<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSection;
use App\Services\Admin\Enrollment\EnrollmentApprovalService;
use App\Services\MicrosoftGraphService;
use App\Services\MsTeamsEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentRosterController extends Controller
{
    public function updateSection(Request $request, Student $student)
    {
        abort_if(auth()->user()?->isTeacherAdminViewer(), 403);

        $request->validate([
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $sectionId = $request->section_id;
        $oldSectionId = $student->studentSection->section_id ?? null;
        $schoolYear = $student->school_year ?: config('app.school_year', '2026-2027');

        // Enforce roster locking in backend: Check if current section is locked
        if ($oldSectionId && \App\Models\RosterLock::isLocked('section', (string) $oldSectionId, $schoolYear)) {
            $oldSec = Section::find($oldSectionId);
            return back()->with('error', "Cannot modify section assignment: The roster for {$oldSec?->name} is finalized and locked. Authorized administrators must unlock the roster first.");
        }

        // Check if target new section is locked
        if ($sectionId && \App\Models\RosterLock::isLocked('section', (string) $sectionId, $schoolYear)) {
            $newSec = Section::find($sectionId);
            return back()->with('error', "Cannot modify section assignment: The roster for {$newSec?->name} is finalized and locked. Authorized administrators must unlock the roster first.");
        }

        // Check if the student's Grade Level Face-to-Face roster is locked
        $grade = $student->grade_level;
        if ($grade && \App\Models\RosterLock::isLocked('f2f', (string) $grade, $schoolYear)) {
            return back()->with('error', "Cannot modify section assignment: The Face-to-Face roster for {$grade} is finalized and locked. Authorized administrators must unlock the roster first.");
        }

        DB::transaction(function () use ($student, $sectionId) {
            if (empty($sectionId)) {
                StudentSection::where('student_id', $student->id)->delete();
            } else {
                StudentSection::updateOrCreate(
                    ['student_id' => $student->id],
                    [
                        'section_id' => $sectionId,
                        'ms_status' => 'pending',
                    ]
                );
            }
        });

        $secName = $sectionId ? (Section::find($sectionId)->name ?? "Section #{$sectionId}") : 'Unassigned';
        $studentName = $student->applicant ? trim($student->applicant->first_name.' '.$student->applicant->last_name) : $student->student_number;

        AdminAuditLog::record(
            event: 'update_student_section',
            successful: true,
            message: "Assigned student {$studentName} ({$student->student_number}) to section: {$secName}",
            metadata: [
                'student_id' => $student->id,
                'student_number' => $student->student_number,
                'school_email' => $student->school_email,
                'applicant_id' => $student->enrollment_applicant_id,
                'section_id' => $sectionId,
                'section_name' => $secName,
            ]
        );

        if ($student->ms_user_id) {
            try {
                $graph = new MicrosoftGraphService;
                $service = new MsTeamsEnrollmentService($graph);

                if ($oldSectionId && $oldSectionId != $sectionId) {
                    $oldSection = Section::find($oldSectionId);
                    if ($oldSection && $oldSection->ms_team_id) {
                        try {
                            $members = $graph->listTeamMembers($oldSection->ms_team_id);
                            $membershipId = null;
                            foreach ($members as $m) {
                                if (isset($m['userId']) && strtolower($m['userId']) === strtolower($student->ms_user_id)) {
                                    $membershipId = $m['id'];
                                    break;
                                }
                            }
                            if ($membershipId) {
                                $graph->removeTeamMember($oldSection->ms_team_id, $membershipId);
                            }
                        } catch (\Exception $removeEx) {
                            Log::warning("Could not remove student {$student->id} from old Team: ".$removeEx->getMessage());
                        }
                    }
                }

                if ($sectionId) {
                    $newSection = Section::find($sectionId);
                    if ($newSection && $newSection->ms_team_id) {
                        $service->enrollStudent($student);
                    }
                }

                if ($student->applicant) {
                    app(EnrollmentApprovalService::class)->backfillMicrosoftPhoto($student->applicant);
                }
            } catch (\Exception $e) {
                Log::warning('Microsoft Teams/Photo sync during section update failed: '.$e->getMessage());

                return back()->with('success', 'Student section updated in portal database, but M365 sync failed: '.$e->getMessage());
            }
        }

        return back()->with('success', 'Student section updated and Microsoft Teams memberships synchronized successfully.');
    }
}
