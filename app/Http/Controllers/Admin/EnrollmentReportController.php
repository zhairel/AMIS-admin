<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassAdvisoryAssignment;
use App\Models\EnrollmentApplicant;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSection;
use App\Models\User;
use App\Services\GoogleDriveUploadService;
use App\Services\MicrosoftGraphService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnrollmentReportController extends Controller
{
    public function reports(Request $request)
    {
        $schoolYears = app(\App\Services\SchoolYearService::class)->available();
        $gradeLevels = ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];

        $dbAdvisors = ClassAdvisoryAssignment::where('status', 'active')
            ->whereNotNull('teacher_email')
            ->select('teacher_name as name', 'teacher_email as email')
            ->distinct()
            ->get()
            ->toArray();

        $configAdvisors = [];
        $elementary = config('class_advisories.elementary', []);
        $highSchool = config('class_advisories.high_school', []);
        $allConfig = array_merge($elementary, $highSchool);
        foreach ($allConfig as $adv) {
            if (! empty($adv['teacher'])) {
                $teacherName = $adv['teacher'];
                $cleanName = trim(str_ireplace('TEACHER ', '', $teacherName));
                $user = User::where('role', 'teacher')
                    ->where(function ($query) use ($cleanName) {
                        $query->where('name', $cleanName)
                            ->orWhere('name', 'like', '%'.$cleanName.'%');
                    })
                    ->first();
                if ($user && $user->email) {
                    $configAdvisors[] = [
                        'name' => $teacherName,
                        'email' => $user->email,
                    ];
                }
            }
        }

        $advisors = collect(array_merge($dbAdvisors, $configAdvisors))
            ->unique('email')
            ->sortBy('name')
            ->values()
            ->toArray();

        return view('admin.students.reports', compact('schoolYears', 'gradeLevels', 'advisors'));
    }

    public function attendance(Request $request)
    {
        $schoolYears = app(\App\Services\SchoolYearService::class)->available();
        $gradeLevels = ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];

        $dbAdvisors = ClassAdvisoryAssignment::where('status', 'active')
            ->whereNotNull('teacher_email')
            ->select('teacher_name as name', 'teacher_email as email')
            ->distinct()
            ->get()
            ->toArray();

        $configAdvisors = [];
        $elementary = config('class_advisories.elementary', []);
        $highSchool = config('class_advisories.high_school', []);
        $allConfig = array_merge($elementary, $highSchool);
        foreach ($allConfig as $adv) {
            if (! empty($adv['teacher'])) {
                $teacherName = $adv['teacher'];
                $cleanName = trim(str_ireplace('TEACHER ', '', $teacherName));
                $user = User::where('role', 'teacher')
                    ->where(function ($query) use ($cleanName) {
                        $query->where('name', $cleanName)
                            ->orWhere('name', 'like', '%'.$cleanName.'%');
                    })
                    ->first();
                if ($user && $user->email) {
                    $configAdvisors[] = [
                        'name' => $teacherName,
                        'email' => $user->email,
                    ];
                }
            }
        }

        $advisors = collect(array_merge($dbAdvisors, $configAdvisors))
            ->unique('email')
            ->sortBy('name')
            ->values()
            ->toArray();

        return view('admin.students.attendance', compact('schoolYears', 'gradeLevels', 'advisors'));
    }

    public function reportsData(Request $request)
    {
        $query = Student::query()
            ->with(['applicant', 'studentSection.section']);

        $activeSy = app(\App\Services\SchoolYearService::class)->current();
        if ($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy)) {
            $query->where('school_year', $activeSy);
        }

        if ($request->filled('grade_level')) {
            $query->where('grade_level', $request->grade_level);
        }

        if ($request->filled('gender')) {
            $gender = $request->gender;
            $query->whereHas('applicant', function ($q) use ($gender) {
                $q->where('gender', $gender);
            });
        }

        if ($request->filled('learning_mode')) {
            $mode = $request->learning_mode;
            if ($mode === 'f2f') {
                $query->where(function ($q) {
                    $q->whereHas('studentSection.section', function ($qs) {
                        $qs->where('learning_mode', 'Face-to-Face');
                    })->orWhereHas('applicant', function ($qa) {
                        $qa->where('learning_mode', 'Face-to-Face');
                    });
                });
            } elseif ($mode === 'odl_1st') {
                $query->where(function ($q) {
                    $q->whereHas('studentSection.section', function ($qs) {
                        $qs->where('learning_mode', 'Flexible Online Learning')
                            ->where('shift', '1st Shift');
                    })->orWhereHas('applicant', function ($qa) {
                        $qa->where('learning_mode', 'like', '%1st Shift%');
                    });
                });
            } elseif ($mode === 'odl_2nd') {
                $query->where(function ($q) {
                    $q->whereHas('studentSection.section', function ($qs) {
                        $qs->where('learning_mode', 'Flexible Online Learning')
                            ->where('shift', '2nd Shift');
                    })->orWhereHas('applicant', function ($qa) {
                        $qa->where('learning_mode', 'like', '%2nd Shift%');
                    });
                });
            }
        }

        if ($request->filled('adviser')) {
            $adviserEmail = $request->adviser;

            $sectionIds = ClassAdvisoryAssignment::where('status', 'active')
                ->where('teacher_email', $adviserEmail)
                ->pluck('section_id')
                ->toArray();

            $elementary = config('class_advisories.elementary', []);
            $highSchool = config('class_advisories.high_school', []);
            $allConfig = array_merge($elementary, $highSchool);
            $configGradeLevels = [];
            foreach ($allConfig as $adv) {
                if (! empty($adv['teacher'])) {
                    $teacherName = $adv['teacher'];
                    $cleanName = trim(str_ireplace('TEACHER ', '', $teacherName));
                    $user = User::where('role', 'teacher')
                        ->where(function ($query) use ($cleanName) {
                            $query->where('name', $cleanName)
                                ->orWhere('name', 'like', '%'.$cleanName.'%');
                        })
                        ->first();
                    if ($user && $user->email === $adviserEmail) {
                        $configGradeLevels[] = $adv['grade_level'];
                    }
                }
            }

            $query->where(function ($q) use ($sectionIds, $configGradeLevels) {
                $q->whereHas('studentSection', function ($qs) use ($sectionIds) {
                    $qs->whereIn('section_id', $sectionIds);
                });
                if (! empty($configGradeLevels)) {
                    $q->orWhereIn('grade_level', $configGradeLevels);
                }
            });
        }

        if ($request->filled('status')) {
            $status = $request->status;
            switch ($status) {
                case 'has_account':
                    $query->whereNotNull('ms_user_id');
                    break;
                case 'no_account':
                    $query->whereNull('ms_user_id');
                    break;
                case 'active_account':
                    $query->whereNotNull('ms_user_id')->where('ms_account_enabled', true);
                    break;
                case 'inactive_account':
                    $query->whereNotNull('ms_user_id')->where('ms_account_enabled', false);
                    break;
                case 'logged_in':
                    $query->where(function ($q) {
                        $q->whereNotNull('last_login_at')
                            ->orWhereNotNull('ms_last_sign_in_at')
                            ->orWhereNotNull('password_changed_at');
                    });
                    break;
                case 'never_signed_in':
                    $query->whereNotNull('ms_user_id')
                        ->whereNull('last_login_at')
                        ->whereNull('ms_last_sign_in_at')
                        ->whereNull('password_changed_at');
                    break;
                case 'joined_teams':
                    $query->whereNotNull('ms_user_id')->whereNotNull('ms_teams_enrolled_at');
                    break;
                case 'not_joined_teams':
                    $query->whereNotNull('ms_user_id')->whereNull('ms_teams_enrolled_at');
                    break;
                case 'licensed':
                    $query->whereNotNull('ms_user_id')->where('ms_license_active', true);
                    break;
                case 'unlicensed':
                    $query->whereNotNull('ms_user_id')->where('ms_license_active', false);
                    break;
                case 'assigned_class':
                    $query->whereHas('studentSection');
                    break;
                case 'no_class':
                    $query->whereDoesntHave('studentSection');
                    break;
                case 'joined_class':
                    $query->whereHas('studentSection', function ($q) {
                        $q->where('ms_status', 'enrolled');
                    });
                    break;
                case 'not_joined_class':
                    $query->whereHas('studentSection', function ($q) {
                        $q->where('ms_status', '!=', 'enrolled');
                    });
                    break;
                case 'temp_password':
                    $query->whereNotNull('temp_password')->whereNull('password_changed_at');
                    break;
                case 'password_changed':
                    $query->whereNotNull('password_changed_at');
                    break;
                case 'joined_call':
                    $query->where('ms_teams_meetings_attended', '>', 0);
                    break;
                case 'no_call':
                    $query->where(fn ($q) => $q->whereNull('ms_teams_meetings_attended')->orWhere('ms_teams_meetings_attended', 0));
                    break;
            }
        }

        if ($request->filled('date_range')) {
            $parts = explode(' to ', $request->date_range);
            if (count($parts) === 2) {
                $start = trim($parts[0]);
                $end = trim($parts[1]).' 23:59:59';
                $query->whereBetween('created_at', [$start, $end]);
            } else {
                $date = trim($parts[0]);
                $query->whereDate('created_at', $date);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('student_number', 'like', "%{$search}%")
                    ->orWhere('school_email', 'like', "%{$search}%")
                    ->orWhere('ms_email', 'like', "%{$search}%")
                    ->orWhereHas('applicant', function ($qa) use ($search) {
                        $qa->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$search}%"])
                            ->orWhereRaw("CONCAT(last_name, ', ', first_name) like ?", ["%{$search}%"]);
                    });
            });
        }

        $allFilteredStudents = $query->get();

        $totalRegistered = $allFilteredStudents->count();
        $accountsGenerated = $allFilteredStudents->whereNotNull('ms_user_id')->count();
        $licensedCount = $allFilteredStudents->whereNotNull('ms_user_id')->where('ms_license_active', true)->count();
        $unlicensedCount = $accountsGenerated - $licensedCount;
        $activeAccounts = $allFilteredStudents->whereNotNull('ms_user_id')->where('ms_account_enabled', true)->count();
        $inactiveAccounts = $accountsGenerated - $activeAccounts;
        $loggedInCount = $allFilteredStudents->whereNotNull('ms_user_id')->filter(fn ($s) => $s->last_login_at !== null || $s->password_changed_at !== null || $s->ms_last_sign_in_at !== null)->count();
        $neverSignedIn = $accountsGenerated - $loggedInCount;

        $joinedTeams = $allFilteredStudents->whereNotNull('ms_user_id')->whereNotNull('ms_teams_enrolled_at')->count();
        $notJoinedTeams = $accountsGenerated - $joinedTeams;

        $assignedClass = $allFilteredStudents->filter(fn ($s) => $s->studentSection !== null)->count();
        $withoutClass = $totalRegistered - $assignedClass;

        $joinedClass = $allFilteredStudents->filter(fn ($s) => $s->studentSection !== null && $s->studentSection->ms_status === 'enrolled')->count();
        $notJoinedClass = $assignedClass - $joinedClass;

        $getLearningModeClass = function ($s) {
            $mode = null;
            $shift = null;
            if ($s->studentSection && $s->studentSection->section) {
                $mode = $s->studentSection->section->learning_mode;
                $shift = $s->studentSection->section->shift;
            } elseif ($s->applicant) {
                $mode = $s->applicant->learning_mode;
            }

            $modeStr = strtolower((string) $mode);
            $shiftStr = strtolower((string) $shift);

            if (str_contains($modeStr, 'face')) {
                return 'f2f';
            } elseif (str_contains($modeStr, 'flexible') || str_contains($modeStr, 'odl') || str_contains($modeStr, 'online')) {
                if (str_contains($shiftStr, '1st') || str_contains($modeStr, '1st')) {
                    return 'odl_1st';
                } elseif (str_contains($shiftStr, '2nd') || str_contains($modeStr, '2nd')) {
                    return 'odl_2nd';
                } else {
                    return 'odl_1st';
                }
            }

            return 'unknown';
        };

        $odl1Count = $allFilteredStudents->filter(fn ($s) => $getLearningModeClass($s) === 'odl_1st')->count();
        $odl2Count = $allFilteredStudents->filter(fn ($s) => $getLearningModeClass($s) === 'odl_2nd')->count();
        $f2fCount = $allFilteredStudents->filter(fn ($s) => $getLearningModeClass($s) === 'f2f')->count();

        $boyCount = $allFilteredStudents->filter(fn ($s) => $s->applicant && strtolower($s->applicant->gender ?? '') === 'male')->count();
        $girlCount = $allFilteredStudents->filter(fn ($s) => $s->applicant && strtolower($s->applicant->gender ?? '') === 'female')->count();

        $tempPasswordNotChanged = $allFilteredStudents->whereNotNull('ms_user_id')->whereNotNull('temp_password')->whereNull('password_changed_at')->count();
        $passwordChanged = $allFilteredStudents->whereNotNull('ms_user_id')->whereNotNull('password_changed_at')->count();

        $latestSignIn = $allFilteredStudents->max('last_login_at') ?? $allFilteredStudents->max('ms_last_sign_in_at');
        $latestSignInStr = $latestSignIn ? Carbon::parse($latestSignIn)->diffForHumans() : 'No activity';

        $teamsAppUsed = $allFilteredStudents->filter(fn ($s) => ! is_null($s->ms_teams_last_activity_at))->count();
        $teamsAppNeverUsed = $totalRegistered - $teamsAppUsed;

        $summary = [
            'total_registered' => $totalRegistered,
            'accounts_generated' => $accountsGenerated,
            'licensed_count' => $licensedCount,
            'unlicensed_count' => $unlicensedCount,
            'active_accounts' => $activeAccounts,
            'inactive_accounts' => $inactiveAccounts,
            'logged_in' => $loggedInCount,
            'never_signed_in' => $neverSignedIn,
            'joined_teams' => $joinedTeams,
            'not_joined_teams' => $notJoinedTeams,
            'assigned_class' => $assignedClass,
            'without_class' => $withoutClass,
            'joined_class' => $joinedClass,
            'not_joined_class' => $notJoinedClass,
            'temp_password' => $tempPasswordNotChanged,
            'password_changed' => $passwordChanged,
            'last_sign_in_activity' => $latestSignInStr,
            'odl_1st_count' => $odl1Count,
            'odl_2nd_count' => $odl2Count,
            'f2f_count' => $f2fCount,
            'boy_count' => $boyCount,
            'girl_count' => $girlCount,
            'teams_app_used' => $teamsAppUsed,
            'teams_app_never_used' => $teamsAppNeverUsed,
        ];

        $gradeOrder = ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
        $studentsByGrade = $allFilteredStudents->groupBy('grade_level');

        $gradeBreakdown = collect($gradeOrder)->map(function ($gradeLevel) use ($studentsByGrade, $getLearningModeClass) {
            $gradeStudents = $studentsByGrade->get($gradeLevel, collect());
            $gradeMs = $gradeStudents->whereNotNull('ms_user_id');

            return [
                'grade_level' => $gradeLevel,
                'total' => $gradeStudents->count(),
                'odl_1st' => $gradeStudents->filter(fn ($s) => $getLearningModeClass($s) === 'odl_1st')->count(),
                'odl_2nd' => $gradeStudents->filter(fn ($s) => $getLearningModeClass($s) === 'odl_2nd')->count(),
                'f2f' => $gradeStudents->filter(fn ($s) => $getLearningModeClass($s) === 'f2f')->count(),
                'accounts' => $gradeMs->count(),
                'logged_in' => $gradeMs->filter(fn ($s) => $s->last_login_at !== null || $s->password_changed_at !== null || $s->ms_last_sign_in_at !== null)->count(),
                'joined_teams' => $gradeMs->whereNotNull('ms_teams_enrolled_at')->count(),
                'joined_class' => $gradeStudents->filter(fn ($s) => $s->studentSection !== null && $s->studentSection->ms_status === 'enrolled')->count(),
                'password_changed' => $gradeMs->whereNotNull('password_changed_at')->count(),
            ];
        })->filter(fn ($g) => $g['total'] > 0)->values();

        $charts = [
            'gradeDistribution' => [
                'labels' => $gradeBreakdown->pluck('grade_level')->toArray(),
                'data' => $gradeBreakdown->pluck('total')->toArray(),
            ],
            'accountStatus' => [
                'labels' => ['Provisioned', 'Unprovisioned'],
                'data' => [$accountsGenerated, $totalRegistered - $accountsGenerated],
            ],
            'loginStatus' => [
                'labels' => ['Active Logins', 'Never Signed In'],
                'data' => [$loggedInCount, $neverSignedIn],
            ],
            'teamsAdoption' => [
                'labels' => ['Joined Teams', 'Not Joined'],
                'data' => [$joinedTeams, $notJoinedTeams],
            ],
            'classJoinStatus' => [
                'labels' => ['Joined Class', 'Not Joined Class'],
                'data' => [$joinedClass, $notJoinedClass],
            ],
            'passwordChangeStatus' => [
                'labels' => ['Changed', 'Temporary Password'],
                'data' => [$passwordChanged, $tempPasswordNotChanged],
            ],
            'activeInactive' => [
                'labels' => ['Enabled', 'Disabled'],
                'data' => [$activeAccounts, $inactiveAccounts],
            ],
            'licenseStatus' => [
                'labels' => ['Licensed', 'Unlicensed'],
                'data' => [$licensedCount, $unlicensedCount],
            ],
        ];

        $perPage = $request->get('per_page', 10);
        $paginated = $query->orderBy('grade_level')
            ->orderBy('id')
            ->paginate($perPage);

        $studentsList = collect($paginated->items())->map(function ($s) {
            $name = $s->applicant ? strtoupper($s->applicant->last_name.', '.$s->applicant->first_name) : 'UNREGISTERED';
            $lastSignIn = $s->last_login_at ? $s->last_login_at->format('Y-m-d H:i A') : ($s->ms_last_sign_in_at ? $s->ms_last_sign_in_at->format('Y-m-d H:i A') : 'NEVER');
            $teamsLastActivity = $s->ms_teams_last_activity_at ? $s->ms_teams_last_activity_at->format('Y-m-d') : null;

            return [
                'id' => $s->id,
                'student_number' => $s->student_number ?? 'N/A',
                'name' => html_entity_decode($name, ENT_QUOTES, 'UTF-8'),
                'grade' => $s->grade_level ?? 'N/A',
                'gender' => $s->applicant ? (strtolower($s->applicant->gender ?? '') === 'male' ? 'Boy' : (strtolower($s->applicant->gender ?? '') === 'female' ? 'Girl' : 'N/A')) : 'N/A',
                'email' => $s->school_email ?? $s->ms_email ?? 'N/A',
                'license' => $s->ms_license_active ? 'Licensed' : 'Unlicensed',
                'account_enabled' => $s->ms_account_enabled ? 'Enabled' : 'Disabled',
                'last_sign_in' => $lastSignIn,
                'teams_joined' => $s->ms_teams_enrolled_at ? 'Joined' : 'Not Joined',
                'class_joined' => ($s->studentSection && $s->studentSection->ms_status === 'enrolled') ? 'Joined' : 'Not Joined',
                'password_changed' => $s->password_changed_at ? 'Changed' : 'Temporary',
                'temp_password' => $s->temp_password ?? 'N/A',
                'teams_app_used' => $teamsLastActivity ? true : false,
                'teams_last_activity' => $teamsLastActivity ?? 'Never',
                'teams_meetings_attended' => $s->ms_teams_meetings_attended ?? 0,
            ];
        });

        $paginatedData = [
            'data' => $studentsList,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'gradeBreakdown' => $gradeBreakdown,
            'charts' => $charts,
            'students' => $paginatedData,
            'last_sync' => Cache::get('ms_last_sync_time', 'Never'),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function syncNow(Request $request)
    {
        $graph = new MicrosoftGraphService;
        try {
            $azureUsers = $graph->listTenantStudents();
            $studentSkuId = config('services.microsoft.student_sku_id');

            if (! $studentSkuId) {
                throw new \Exception('Student SKU ID is not configured in services.php.');
            }

            $azureByEmail = collect($azureUsers)->keyBy(fn ($u) => strtolower($u['userPrincipalName'] ?? ''));

            Student::whereNotNull('school_email')->chunkById(100, function ($students) use ($azureByEmail, $studentSkuId) {
                foreach ($students as $student) {
                    $email = strtolower($student->school_email ?? '');
                    $azUser = $azureByEmail->get($email);

                    if ($azUser) {
                        $hasLicense = collect($azUser['assignedLicenses'] ?? [])
                            ->contains(fn ($lic) => strtolower($lic['skuId'] ?? '') === strtolower($studentSkuId));

                        $updateData = [
                            'ms_user_id' => $azUser['id'],
                            'ms_email' => $azUser['userPrincipalName'],
                            'ms_license_active' => $hasLicense,
                            'ms_account_enabled' => (bool) ($azUser['accountEnabled'] ?? true),
                        ];

                        if (! empty($azUser['createdDateTime'])) {
                            $updateData['ms_account_created_at'] = Carbon::parse($azUser['createdDateTime']);
                        }

                        if (! empty($azUser['signInActivity']['lastSignInDateTime'])) {
                            $updateData['ms_last_sign_in_at'] = Carbon::parse($azUser['signInActivity']['lastSignInDateTime']);
                        }

                        $student->update($updateData);
                    }
                }
            });

            $sections = Section::whereNotNull('ms_team_id')->get()->filter(function ($section) {
                return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $section->ms_team_id);
            });

            foreach ($sections as $section) {
                try {
                    $members = $graph->listTeamMembers($section->ms_team_id);
                    $studentMsIds = [];
                    $studentEmails = [];
                    foreach ($members as $member) {
                        $roles = $member['roles'] ?? [];
                        if (! in_array('owner', $roles, true)) {
                            if (! empty($member['userId'])) {
                                $studentMsIds[] = $member['userId'];
                            }
                            if (! empty($member['email'])) {
                                $studentEmails[] = strtolower($member['email']);
                            }
                        }
                    }

                    $studentsInGraph = Student::whereIn('ms_user_id', $studentMsIds)
                        ->orWhere(function ($q) use ($studentEmails) {
                            $q->whereIn('school_email', $studentEmails)
                                ->orWhereIn('ms_email', $studentEmails);
                        })
                        ->get();

                    foreach ($studentsInGraph as $student) {
                        $studentSection = StudentSection::where('student_id', $student->id)
                            ->where('section_id', $section->id)
                            ->first();

                        if (! $studentSection) {
                            StudentSection::where('student_id', $student->id)->delete();
                            StudentSection::create([
                                'student_id' => $student->id,
                                'section_id' => $section->id,
                                'ms_status' => 'enrolled',
                                'ms_enrolled_at' => now(),
                            ]);
                        } elseif ($studentSection->ms_status !== 'enrolled') {
                            $studentSection->update([
                                'ms_status' => 'enrolled',
                                'ms_enrolled_at' => $studentSection->ms_enrolled_at ?? now(),
                            ]);
                        }
                    }

                    $localEnrolled = StudentSection::where('section_id', $section->id)
                        ->where('ms_status', 'enrolled')
                        ->get();

                    foreach ($localEnrolled as $dbSec) {
                        $student = $dbSec->student;
                        $isEnrolledInGraph = false;

                        if ($student) {
                            $studentEmail = strtolower($student->school_email ?? $student->ms_email ?? '');
                            if ($student->ms_user_id && in_array($student->ms_user_id, $studentMsIds)) {
                                $isEnrolledInGraph = true;
                            } elseif ($studentEmail && in_array($studentEmail, $studentEmails)) {
                                $isEnrolledInGraph = true;
                            }
                        }

                        if (! $isEnrolledInGraph) {
                            $dbSec->update(['ms_status' => 'pending']);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("syncNow: Failed to sync team members for section {$section->id}: ".$e->getMessage());
                }
            }

            try {
                $teamsActivity = $graph->getTeamsUserActivityReport('D30');

                if (! empty($teamsActivity)) {
                    Student::whereNotNull('ms_user_id')->chunkById(100, function ($students) use ($teamsActivity) {
                        foreach ($students as $student) {
                            $userId = strtolower($student->ms_user_id ?? '');
                            $email = strtolower($student->school_email ?? $student->ms_email ?? '');

                            $activity = $teamsActivity['id:'.$userId]
                                     ?? $teamsActivity['upn:'.$email]
                                     ?? null;

                            if ($activity) {
                                $updateData = [
                                    'ms_teams_meetings_attended' => $activity['meetings_attended'] ?? 0,
                                ];

                                if (! empty($activity['last_activity_date'])) {
                                    $updateData['ms_teams_last_activity_at'] = Carbon::parse($activity['last_activity_date'])->startOfDay();
                                }

                                $student->update($updateData);
                            }
                        }
                    });
                }
            } catch (\Exception $e) {
                Log::warning('Teams activity sync failed (non-fatal): '.$e->getMessage());
            }

            Cache::put('ms_last_sync_time', now()->format('Y-m-d H:i:s'), 86400);

            return response()->json([
                'success' => true,
                'message' => 'Microsoft Graph data and Team memberships synchronized successfully.',
                'last_sync' => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            Log::error('Microsoft Graph Dashboard syncNow failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function enrollmentPaymentsReportData(Request $request)
    {
        $query = EnrollmentApplicant::with('payment');

        if ($request->filled('search')) {
            $search = '%'.trim($request->search).'%';
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('middle_name', 'like', $search)
                    ->orWhere('id', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('parent_email', 'like', $search)
                    ->orWhere('father_first_name', 'like', $search)
                    ->orWhere('father_last_name', 'like', $search)
                    ->orWhere('mother_first_name', 'like', $search)
                    ->orWhere('mother_last_name', 'like', $search)
                    ->orWhere('contact_number', 'like', $search)
                    ->orWhere('mobile_number', 'like', $search)
                    ->orWhereHas('payment', function ($qp) use ($search) {
                        $qp->where('reference_no', 'like', $search);
                    });
            });
        }

        $activeSy = app(\App\Services\SchoolYearService::class)->current();
        if ($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy)) {
            $query->where('school_year', $activeSy);
        }

        if ($request->filled('grade_level')) {
            $query->where('grade_level', $request->grade_level);
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('learning_mode')) {
            $mode = $request->learning_mode;
            if ($mode === 'f2f') {
                $query->where('learning_mode', 'Face-to-Face');
            } elseif ($mode === 'odl_1st') {
                $query->where('learning_mode', 'like', '%1st Shift%');
            } elseif ($mode === 'odl_2nd') {
                $query->where('learning_mode', 'like', '%2nd Shift%');
            }
        }

        $applicants = $query->orderBy('created_at', 'desc')->get();
        $groups = [];

        foreach ($applicants as $app) {
            $fullName = trim($app->first_name.' '.$app->middle_name.' '.$app->last_name);
            $fullName = strtoupper($fullName);
            $studentInfo = [
                'id' => $app->id,
                'name' => $fullName,
                'grade' => $app->grade_level ?: 'N/A',
                'status' => $app->status,
            ];

            $familyId = $app->family_application_id !== null ? trim($app->family_application_id) : '';
            $groupKey = ($familyId !== '') ? 'fam_'.$familyId : 'solo_'.$app->id;

            if (! isset($groups[$groupKey])) {
                $hasPaymentProof = false;
                $paymentDetails = null;

                if ($app->payment && (! empty($app->payment->receipt_url) || ! empty($app->payment->reference_no))) {
                    $hasPaymentProof = true;
                    $paymentDetails = [
                        'method' => strtoupper($app->payment->method ?: 'N/A'),
                        'ref_no' => $app->payment->reference_no ?: 'N/A',
                        'amount' => $app->payment->amount ? number_format($app->payment->amount, 2) : 'N/A',
                        'receipt' => $app->payment->receipt_url ?: null,
                        'status' => $app->payment->status ?: 'pending',
                    ];
                }

                $parentName = trim(($app->father_first_name ? $app->father_first_name.' '.$app->father_last_name : '').' / '.($app->mother_first_name ? $app->mother_first_name.' '.$app->mother_last_name : ''));
                $parentName = trim($parentName, ' /');

                $groups[$groupKey] = [
                    'family_id' => $familyId,
                    'parent' => $parentName ?: 'N/A',
                    'email' => $app->parent_email ?: $app->email ?: 'N/A',
                    'mobile' => $app->parent_mobile ?: $app->mobile_number ?: 'N/A',
                    'students' => [],
                    'payment' => $paymentDetails,
                    'has_payment' => $hasPaymentProof,
                    'status' => $app->status,
                ];
            }

            $groups[$groupKey]['students'][] = $studentInfo;

            if ($app->payment && (! empty($app->payment->receipt_url) || ! empty($app->payment->reference_no))) {
                $groups[$groupKey]['has_payment'] = true;
                $groups[$groupKey]['payment'] = [
                    'method' => strtoupper($app->payment->method ?: 'N/A'),
                    'ref_no' => $app->payment->reference_no ?: 'N/A',
                    'amount' => $app->payment->amount ? number_format($app->payment->amount, 2) : 'N/A',
                    'receipt' => $app->payment->receipt_url ?: null,
                    'status' => $app->payment->status ?: 'pending',
                ];
            }
        }

        $withPaymentProof = [];
        $approvedNoPayment = [];

        foreach ($groups as $groupKey => $group) {
            usort($group['students'], function ($a, $b) {
                return $a['id'] <=> $b['id'];
            });

            $statuses = array_unique(array_column($group['students'], 'status'));
            $group['status_label'] = implode(', ', array_map('strtoupper', $statuses));

            if ($group['has_payment']) {
                $withPaymentProof[] = $group;
            } else {
                $anyApproved = false;
                foreach ($group['students'] as $s) {
                    if ($s['status'] === 'approved') {
                        $anyApproved = true;
                        break;
                    }
                }
                if ($anyApproved) {
                    $approvedNoPayment[] = $group;
                }
            }
        }

        return response()->json([
            'success' => true,
            'with_payment' => $withPaymentProof,
            'approved_no_payment' => $approvedNoPayment,
        ]);
    }

    public function syncGoogleDrive(Request $request)
    {
        $query = EnrollmentApplicant::with('payment')
            ->where('status', 'approved');

        if ($request->filled('search')) {
            $search = '%'.trim($request->search).'%';
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('id', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if ($request->filled('grade_level')) {
            $query->where('grade_level', $request->grade_level);
        }

        $activeSy = app(\App\Services\SchoolYearService::class)->current();
        if ($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy)) {
            $query->where('school_year', $activeSy);
        }

        $applicants = $query->get();
        $driveUploadService = app(GoogleDriveUploadService::class);

        if (! $driveUploadService) {
            return response()->json([
                'success' => false,
                'message' => 'Google Drive upload service could not be initialized.',
            ]);
        }

        $syncedCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($applicants as $applicant) {
            $res = $driveUploadService->uploadApplicantFiles($applicant);
            if ($res['success']) {
                $syncedCount++;
            } else {
                $failedCount++;
                $errors[] = $applicant->full_name.': '.$res['message'];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully synced {$syncedCount} enrollees to Google Drive.".($failedCount > 0 ? " Failed for {$failedCount} enrollees." : ''),
            'errors' => $errors,
        ]);
    }
}
