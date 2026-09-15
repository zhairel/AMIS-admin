<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\EnrollmentApplicant;
use App\Models\RosterLock;
use App\Models\Section;
use App\Models\SectionSubject;
use App\Models\Student;
use App\Models\StudentSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MasterListController extends Controller
{
    /**
     * Confirmed Grade 3 Face-to-Face roster specification (6 male, 12 female).
     */
    protected array $grade3ConfirmedRoster = [
        'male' => [
            ['student_number' => '260960', 'last_name' => 'ABDULGAFFAR', 'first_name' => 'ABDUZZHAHIR JUHAIMEN', 'gender' => 'male'],
            ['student_number' => '260961', 'last_name' => 'DATUMULOK', 'first_name' => 'SHADEED RYAN MAMANTAR', 'gender' => 'male'],
            ['student_number' => '260878', 'last_name' => 'MALON', 'first_name' => 'ABDULLAH MELICANO', 'gender' => 'male'],
            ['student_number' => '261075', 'last_name' => 'SAMSUDDIN', 'first_name' => 'MUHAMMAD', 'gender' => 'male'],
            ['student_number' => '260172', 'last_name' => 'SUNDANG', 'first_name' => 'IZYAN ASHER UGALINGAN', 'gender' => 'male'],
            ['student_number' => '261076', 'last_name' => 'TORRECAMPO', 'first_name' => 'JEVRIL', 'gender' => 'male'],
        ],
        'female' => [
            ['student_number' => '260275', 'last_name' => 'APLAL', 'first_name' => 'KHYLIE KEEM LUPON', 'gender' => 'female'],
            ['student_number' => '261072', 'last_name' => 'BAULO', 'first_name' => 'NORHADIYA', 'gender' => 'female'],
            ['student_number' => '261077', 'last_name' => 'DIPATUAN', 'first_name' => 'AMERAH', 'gender' => 'female'],
            ['student_number' => '261052', 'last_name' => 'ESPAÑOL', 'first_name' => 'HAYAM', 'gender' => 'female'],
            ['student_number' => '261073', 'last_name' => 'JAINULLA', 'first_name' => 'RAZEYNAH HUSSIN', 'gender' => 'female'],
            ['student_number' => '260525', 'last_name' => 'KESAL', 'first_name' => 'ZEEYA RIZWAN DATUMANONG', 'gender' => 'female'],
            ['student_number' => '260711', 'last_name' => 'MARTINEZ', 'first_name' => 'NOOR JANNAH PORQUEZ', 'gender' => 'female'],
            ['student_number' => '261074', 'last_name' => 'PANTAON', 'first_name' => 'AAISHA', 'gender' => 'female'],
            ['student_number' => '260328', 'last_name' => 'PENDALIDAY', 'first_name' => 'NOOR AURORA DALAYON', 'gender' => 'female'],
            ['student_number' => '260809', 'last_name' => 'SUDAGAR', 'first_name' => 'ZAIRA JIKIRANI', 'gender' => 'female'],
            ['student_number' => '261046', 'last_name' => 'TEÑOSO', 'first_name' => 'YANNAH LUPON', 'gender' => 'female'],
            ['student_number' => '260330', 'last_name' => 'UNOS', 'first_name' => 'SAINA GALANTO', 'gender' => 'female'],
        ],
    ];

    public function index()
    {
        return redirect()->route('admin.master-lists.f2f.index');
    }

    /**
     * Face-to-Face Lists Index: Show all configured grade levels.
     */
    public function f2fIndex(Request $request)
    {
        $schoolYear = $request->get('school_year', '2026-2027');

        $gradeConfigs = [
            ['code' => 'K1', 'name' => 'Kinder 1', 'category' => 'Early Childhood'],
            ['code' => 'K2', 'name' => 'Kinder 2', 'category' => 'Early Childhood'],
            ['code' => 'G1', 'name' => 'Grade 1', 'category' => 'Primary School'],
            ['code' => 'G2', 'name' => 'Grade 2', 'category' => 'Primary School'],
            ['code' => 'G3', 'name' => 'Grade 3', 'category' => 'Primary School'],
            ['code' => 'G4', 'name' => 'Grade 4', 'category' => 'Intermediate School'],
            ['code' => 'G5', 'name' => 'Grade 5', 'category' => 'Intermediate School'],
            ['code' => 'G6', 'name' => 'Grade 6', 'category' => 'Intermediate School'],
            ['code' => 'G7', 'name' => 'Grade 7', 'category' => 'Junior High School'],
            ['code' => 'G8', 'name' => 'Grade 8', 'category' => 'Junior High School'],
            ['code' => 'G9', 'name' => 'Grade 9', 'category' => 'Junior High School'],
            ['code' => 'G10', 'name' => 'Grade 10', 'category' => 'Junior High School'],
            ['code' => 'G11', 'name' => 'Grade 11', 'category' => 'Senior High School'],
            ['code' => 'G12', 'name' => 'Grade 12', 'category' => 'Senior High School'],
        ];

        $grades = [];
        foreach ($gradeConfigs as $gc) {
            $gradeName = $gc['name'];
            $adviser = $this->resolveGradeAdviser($gradeName);

            // Calculate student count
            if ($gc['code'] === 'G3') {
                $studentCount = 18; // 6 male + 12 female
            } else {
                $studentCount = Student::where('grade_level', $gradeName)
                    ->whereHas('studentSection.section', function ($q) {
                        $q->where('learning_mode', 'Face-to-Face')
                          ->orWhere('name', 'like', '%FACE TO FACE%');
                    })
                    ->count();
            }

            $lock = RosterLock::getLock('f2f', $gradeName, $schoolYear);

            $grades[] = [
                'code' => $gc['code'],
                'name' => $gc['name'],
                'category' => $gc['category'],
                'adviser' => $adviser,
                'student_count' => $studentCount,
                'is_empty' => $studentCount === 0,
                'is_locked' => $lock?->status === 'locked',
                'lock' => $lock,
            ];
        }

        return view('admin.master-lists.f2f.index', compact('grades', 'schoolYear'));
    }

    /**
     * Face-to-Face List Detail: Each grade opens its own student list without requiring a section.
     */
    public function f2fShow(Request $request, string $grade)
    {
        $schoolYear = $request->get('school_year', '2026-2027');
        $normalizedGrade = $this->normalizeGradeName($grade);
        $adviser = $this->resolveGradeAdviser($normalizedGrade);
        $lock = RosterLock::getLock('f2f', $normalizedGrade, $schoolYear);
        $subjects = $this->resolveGradeSubjects($normalizedGrade);

        $verifiedMale = collect();
        $verifiedFemale = collect();
        $missingStudents = collect();
        $isEmpty = false;

        if ($normalizedGrade === 'Grade 3') {
            // Process confirmed 18-student roster for Grade 3
            foreach ($this->grade3ConfirmedRoster['male'] as $item) {
                $student = $this->lookupStudentRecord($item['student_number'], $item['last_name'], $item['first_name']);
                if ($student) {
                    $verifiedMale->push([
                        'student_number' => $student->student_number,
                        'name' => $student->applicant ? trim($student->applicant->last_name . ', ' . $student->applicant->first_name . ' ' . $student->applicant->middle_name) : ($item['last_name'] . ', ' . $item['first_name']),
                        'lrn' => $student->applicant?->lrn ?: '260000',
                        'gender' => 'Male',
                        'section_name' => $student->studentSection?->section?->name ?: 'Face-to-Face (Unassigned Section)',
                        'status' => 'Enrolled & Verified',
                        'student_id' => $student->id,
                        'profile_url' => route('admin.students.show', $student->id),
                    ]);
                } else {
                    $missingStudents->push([
                        'student_number' => $item['student_number'],
                        'name' => $item['last_name'] . ', ' . $item['first_name'],
                        'gender' => 'Male',
                        'status' => 'Missing Database Record',
                    ]);
                }
            }

            foreach ($this->grade3ConfirmedRoster['female'] as $item) {
                $student = $this->lookupStudentRecord($item['student_number'], $item['last_name'], $item['first_name']);
                if ($student) {
                    $verifiedFemale->push([
                        'student_number' => $student->student_number,
                        'name' => $student->applicant ? trim($student->applicant->last_name . ', ' . $student->applicant->first_name . ' ' . $student->applicant->middle_name) : ($item['last_name'] . ', ' . $item['first_name']),
                        'lrn' => $student->applicant?->lrn ?: '260000',
                        'gender' => 'Female',
                        'section_name' => $student->studentSection?->section?->name ?: 'Face-to-Face (Unassigned Section)',
                        'status' => 'Enrolled & Verified',
                        'student_id' => $student->id,
                        'profile_url' => route('admin.students.show', $student->id),
                    ]);
                } else {
                    $missingStudents->push([
                        'student_number' => $item['student_number'],
                        'name' => $item['last_name'] . ', ' . $item['first_name'],
                        'gender' => 'Female',
                        'status' => 'Missing Database Record',
                    ]);
                }
            }
        } else {
            // General query for other grades
            $students = Student::where('grade_level', $normalizedGrade)
                ->where(function ($q) {
                    $q->whereHas('studentSection.section', function ($sq) {
                        $sq->where('learning_mode', 'Face-to-Face')
                           ->orWhere('name', 'like', '%FACE TO FACE%');
                    })->orWhereNull('section');
                })
                ->with(['applicant', 'studentSection.section'])
                ->get();

            if ($students->isEmpty()) {
                $isEmpty = true;
            } else {
                foreach ($students as $student) {
                    $gender = strtolower($student->applicant?->gender ?? 'male');
                    $record = [
                        'student_number' => $student->student_number,
                        'name' => $student->applicant ? trim($student->applicant->last_name . ', ' . $student->applicant->first_name . ' ' . $student->applicant->middle_name) : 'Student #' . $student->student_number,
                        'lrn' => $student->applicant?->lrn ?: 'N/A',
                        'gender' => ucfirst($gender),
                        'section_name' => $student->studentSection?->section?->name ?: 'Unassigned Section',
                        'status' => 'Enrolled',
                        'student_id' => $student->id,
                        'profile_url' => route('admin.students.show', $student->id),
                    ];

                    if ($gender === 'female') {
                        $verifiedFemale->push($record);
                    } else {
                        $verifiedMale->push($record);
                    }
                }
            }
        }

        $totalMale = $verifiedMale->count();
        $totalFemale = $verifiedFemale->count();
        $totalVerified = $totalMale + $totalFemale;
        $totalMissing = $missingStudents->count();

        return view('admin.master-lists.f2f.show', compact(
            'normalizedGrade',
            'grade',
            'adviser',
            'schoolYear',
            'lock',
            'subjects',
            'verifiedMale',
            'verifiedFemale',
            'missingStudents',
            'totalMale',
            'totalFemale',
            'totalVerified',
            'totalMissing',
            'isEmpty'
        ));
    }

    /**
     * Section Lists Index: Organize section-based classes by learning modality and grade.
     */
    public function sectionIndex(Request $request)
    {
        $schoolYear = $request->get('school_year', '2026-2027');

        $sections = Section::withCount('students')
            ->with(['subjects'])
            ->orderBy('learning_mode')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        $sectionsByModality = $sections->groupBy(function ($sec) {
            $mode = trim($sec->learning_mode ?? '');
            if (empty($mode) || str_contains(strtolower($mode), 'face')) {
                return 'Face-to-Face Modality';
            }
            return 'Flexible Online Learning';
        });

        // Add lock info and adviser for each section
        $sections->each(function ($sec) use ($schoolYear) {
            $sec->resolved_adviser = $sec->grade_advisor;
            $sec->lock_state = RosterLock::getLock('section', (string) $sec->id, $schoolYear);
        });

        return view('admin.master-lists.sections.index', compact('sectionsByModality', 'schoolYear'));
    }

    /**
     * Section List Detail: Opens its student list and assigned adviser.
     */
    public function sectionShow(Request $request, Section $section)
    {
        $schoolYear = $request->get('school_year', '2026-2027');
        $lock = RosterLock::getLock('section', (string) $section->id, $schoolYear);
        // Resolve adviser name safely
        $adviserName = 'Assigned Faculty';
        if ($section->grade_level === 'Grade 3' && (empty($section->learning_mode) || str_contains(strtolower($section->learning_mode), 'face'))) {
            $adviserName = 'Jerlyn R. Mijares, LPT';
        } elseif (is_object($section->grade_advisor)) {
            $raw = $section->grade_advisor->teacher_name ?? ($section->grade_advisor->teacher?->name ?? null);
            if ($raw) {
                $adviserName = ucwords(strtolower(str_ireplace('TEACHER ', '', $raw)));
            } else {
                $adviserName = $this->resolveGradeAdviser($section->grade_level ?? '');
            }
        } elseif (is_string($section->grade_advisor) && !empty($section->grade_advisor)) {
            $adviserName = ucwords(strtolower(str_ireplace('TEACHER ', '', $section->grade_advisor)));
        } else {
            $adviserName = $this->resolveGradeAdviser($section->grade_level ?? '');
        }
        $adviser = $adviserName;

        // Categorize subjects into Academic and Islamic
        $subjects = $section->subjects;
        $academicSubjects = collect();
        $islamicSubjects = collect();

        foreach ($subjects as $subj) {
            $name = strtolower($subj->subject_name ?? '');
            if (
                str_contains($name, 'qur') ||
                str_contains($name, 'arabic') ||
                str_contains($name, 'shaf') ||
                str_contains($name, 'hadith') ||
                str_contains($name, 'sirah') ||
                str_contains($name, 'aqida') ||
                str_contains($name, 'fiqh') ||
                str_contains($name, 'tahfeez') ||
                str_contains($name, 'islamic')
            ) {
                $islamicSubjects->push($subj);
            } else {
                $academicSubjects->push($subj);
            }
        }

        if ($academicSubjects->isEmpty() && $islamicSubjects->isEmpty()) {
            $fallback = $this->resolveGradeSubjects($section->grade_level ?? '');
            $academicSubjects = $fallback['academic'];
            $islamicSubjects = $fallback['islamic'];
        }

        // Students in this section
        $studentSections = StudentSection::where('section_id', $section->id)
            ->with(['student.applicant', 'student.user'])
            ->get();

        $maleStudents = collect();
        $femaleStudents = collect();

        foreach ($studentSections as $item) {
            $st = $item->student;
            if (!$st) continue;

            $gender = strtolower($st->applicant?->gender ?? 'male');
            $record = [
                'student_number' => $st->student_number,
                'name' => $st->applicant ? trim($st->applicant->last_name . ', ' . $st->applicant->first_name . ' ' . $st->applicant->middle_name) : 'Student #' . $st->student_number,
                'lrn' => $st->applicant?->lrn ?: 'N/A',
                'gender' => ucfirst($gender),
                'email' => $st->school_email ?? $st->email,
                'status' => 'Enrolled',
                'student_id' => $st->id,
                'profile_url' => route('admin.students.show', $st->id),
            ];

            if ($gender === 'female') {
                $femaleStudents->push($record);
            } else {
                $maleStudents->push($record);
            }
        }

        $totalMale = $maleStudents->count();
        $totalFemale = $femaleStudents->count();
        $totalStudents = $totalMale + $totalFemale;

        return view('admin.master-lists.sections.show', compact(
            'section',
            'schoolYear',
            'lock',
            'adviser',
            'academicSubjects',
            'islamicSubjects',
            'maleStudents',
            'femaleStudents',
            'totalMale',
            'totalFemale',
            'totalStudents'
        ));
    }

    /**
     * Finalize & Lock action.
     */
    public function lockRoster(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'roster_type' => 'required|in:f2f,section',
            'roster_identifier' => 'required|string',
            'school_year' => 'required|string',
        ]);

        $lock = RosterLock::updateOrCreate(
            [
                'roster_type' => $request->roster_type,
                'roster_identifier' => $request->roster_identifier,
                'school_year' => $request->school_year,
            ],
            [
                'status' => 'locked',
                'locked_at' => now(),
                'locked_by' => Auth::id(),
            ]
        );

        $typeLabel = $request->roster_type === 'f2f' ? 'Face-to-Face' : 'Section';
        $title = $this->getIdentifierLabel($request->roster_type, $request->roster_identifier);

        AdminAuditLog::record(
            event: 'lock_roster',
            successful: true,
            message: "Finalized and locked {$typeLabel} roster for {$title} (SY {$request->school_year})",
            metadata: [
                'roster_type' => $request->roster_type,
                'roster_identifier' => $request->roster_identifier,
                'school_year' => $request->school_year,
                'locked_by' => Auth::user()?->email,
            ]
        );

        return back()->with('success', "Roster for {$title} has been finalized and locked successfully.");
    }

    /**
     * Unlock Roster action with mandatory justification.
     */
    public function unlockRoster(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'roster_type' => 'required|in:f2f,section',
            'roster_identifier' => 'required|string',
            'school_year' => 'required|string',
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $lock = RosterLock::where('roster_type', $request->roster_type)
            ->where('roster_identifier', $request->roster_identifier)
            ->where('school_year', $request->school_year)
            ->first();

        if ($lock) {
            $lock->update([
                'status' => 'draft',
                'unlocked_at' => now(),
                'unlocked_by' => Auth::id(),
                'unlock_reason' => $request->reason,
            ]);
        }

        $typeLabel = $request->roster_type === 'f2f' ? 'Face-to-Face' : 'Section';
        $title = $this->getIdentifierLabel($request->roster_type, $request->roster_identifier);

        AdminAuditLog::record(
            event: 'unlock_roster',
            successful: true,
            message: "Unlocked {$typeLabel} roster for {$title} (SY {$request->school_year}). Reason: {$request->reason}",
            metadata: [
                'roster_type' => $request->roster_type,
                'roster_identifier' => $request->roster_identifier,
                'school_year' => $request->school_year,
                'reason' => $request->reason,
                'unlocked_by' => Auth::user()?->email,
            ]
        );

        return back()->with('success', "Roster for {$title} has been unlocked. Section modifications are now permitted.");
    }

    /**
     * Helper to lookup existing student by student number or fuzzy name matching.
     */
    protected function lookupStudentRecord(?string $studentNumber, string $lastName, string $firstName): ?Student
    {
        if ($studentNumber) {
            $student = Student::where('student_number', $studentNumber)
                ->with(['applicant', 'studentSection.section'])
                ->first();
            if ($student) return $student;
        }

        // Fuzzy match via applicant
        $cleanLast = str_replace(['ñ', 'Ñ'], 'n', $lastName);
        $firstPart = explode(' ', $firstName)[0];

        $app = EnrollmentApplicant::where(function ($q) use ($lastName, $cleanLast) {
            $q->where('last_name', 'like', "%{$lastName}%")
              ->orWhere('last_name', 'like', "%{$cleanLast}%");
        })->where(function ($q) use ($firstName, $firstPart) {
            $q->where('first_name', 'like', "%{$firstName}%")
              ->orWhere('first_name', 'like', "%{$firstPart}%");
        })->first();

        if ($app) {
            return Student::where('enrollment_applicant_id', $app->id)
                ->orWhere('student_number', $app->amis_student_id)
                ->with(['applicant', 'studentSection.section'])
                ->first();
        }

        return null;
    }

    /**
     * Resolve grade adviser name.
     */
    protected function resolveGradeAdviser(string $gradeName): string
    {
        if ($gradeName === 'Grade 3') {
            return 'Jerlyn R. Mijares, LPT';
        }

        $elementary = config('class_advisories.elementary', []);
        $highSchool = config('class_advisories.high_school', []);
        $all = array_merge($elementary, $highSchool);

        foreach ($all as $adv) {
            if ($adv['grade_level'] === $gradeName) {
                return ucwords(strtolower(str_ireplace('TEACHER ', '', $adv['teacher'])));
            }
        }

        return 'Assigned Faculty';
    }

    /**
     * Resolve linked subjects for a grade level.
     */
    protected function resolveGradeSubjects(string $gradeName): array
    {
        $academic = collect();
        $islamic = collect();

        // Find F2F section for this grade
        $sec = Section::where('grade_level', $gradeName)
            ->where(function ($q) {
                $q->where('name', 'like', '%FACE TO FACE%')
                  ->orWhere('learning_mode', 'Face-to-Face');
            })
            ->with('subjects')
            ->first();

        if ($sec && $sec->subjects->isNotEmpty()) {
            foreach ($sec->subjects as $s) {
                $name = strtolower($s->subject_name ?? '');
                if (
                    str_contains($name, 'qur') ||
                    str_contains($name, 'arabic') ||
                    str_contains($name, 'shaf') ||
                    str_contains($name, 'hadith') ||
                    str_contains($name, 'sirah') ||
                    str_contains($name, 'aqida') ||
                    str_contains($name, 'fiqh') ||
                    str_contains($name, 'tahfeez') ||
                    str_contains($name, 'islamic')
                ) {
                    $islamic->push($s);
                } else {
                    $academic->push($s);
                }
            }
        } else {
            // Default curriculum fallback for Grade 3
            if ($gradeName === 'Grade 3') {
                $academic = collect([
                    (object) ['subject_name' => 'Science', 'teacher_name' => 'Teacher Jerlyn', 'schedule' => 'Mon-Fri'],
                    (object) ['subject_name' => 'Makabansa', 'teacher_name' => 'Teacher Zara', 'schedule' => 'Mon-Fri'],
                    (object) ['subject_name' => 'English', 'teacher_name' => 'Teacher Marham', 'schedule' => 'Mon-Fri'],
                    (object) ['subject_name' => 'Filipino', 'teacher_name' => 'Teacher Normylah', 'schedule' => 'Mon-Fri'],
                    (object) ['subject_name' => 'GMRC', 'teacher_name' => 'Ustadh Silfah', 'schedule' => 'Mon-Fri'],
                    (object) ['subject_name' => 'Math', 'teacher_name' => 'Teacher Jerlyn', 'schedule' => 'Mon-Fri'],
                ]);
                $islamic = collect([
                    (object) ['subject_name' => "Qur'an", 'teacher_name' => 'Ustadh Obaydah', 'schedule' => 'Mon-Fri'],
                    (object) ['subject_name' => 'Arabic', 'teacher_name' => 'Ustadh Silfah', 'schedule' => 'Mon-Fri'],
                    (object) ['subject_name' => 'Sirah/Hadith', 'teacher_name' => 'Ustadh Ersahad', 'schedule' => 'Mon-Fri'],
                    (object) ['subject_name' => 'Aqida/Fiqh', 'teacher_name' => 'Ustadh Ersahad', 'schedule' => 'Mon-Fri'],
                ]);
            }
        }

        return [
            'academic' => $academic,
            'islamic' => $islamic,
        ];
    }

    protected function normalizeGradeName(string $grade): string
    {
        $clean = strtoupper(trim($grade));
        if ($clean === 'G3' || $clean === 'GRADE 3' || $clean === 'GRADE-3') {
            return 'Grade 3';
        }
        if ($clean === 'G1' || $clean === 'GRADE 1' || $clean === 'GRADE-1') {
            return 'Grade 1';
        }
        if ($clean === 'G2' || $clean === 'GRADE 2') return 'Grade 2';
        if ($clean === 'G4' || $clean === 'GRADE 4') return 'Grade 4';
        if ($clean === 'G5' || $clean === 'GRADE 5') return 'Grade 5';
        if ($clean === 'G6' || $clean === 'GRADE 6') return 'Grade 6';
        if ($clean === 'G7' || $clean === 'GRADE 7') return 'Grade 7';
        if ($clean === 'G8' || $clean === 'GRADE 8') return 'Grade 8';
        if ($clean === 'G9' || $clean === 'GRADE 9') return 'Grade 9';
        if ($clean === 'G10' || $clean === 'GRADE 10') return 'Grade 10';
        if ($clean === 'G11' || $clean === 'GRADE 11') return 'Grade 11';
        if ($clean === 'G12' || $clean === 'GRADE 12') return 'Grade 12';
        if ($clean === 'K1' || $clean === 'KINDER 1') return 'Kinder 1';
        if ($clean === 'K2' || $clean === 'KINDER 2') return 'Kinder 2';

        return ucwords(strtolower(str_replace('-', ' ', $grade)));
    }

    protected function getIdentifierLabel(string $type, string $identifier): string
    {
        if ($type === 'f2f') {
            return $identifier;
        }

        $sec = Section::find($identifier);
        return $sec ? "{$sec->name} ({$sec->grade_level})" : "Section #{$identifier}";
    }

    protected function authorizeAdmin(): void
    {
        $role = Auth::user()?->role;
        abort_unless(in_array($role, ['super_admin', 'admin']), 403, 'Unauthorized. Administrator access required.');
    }
}
