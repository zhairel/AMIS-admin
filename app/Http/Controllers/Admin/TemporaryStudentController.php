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

        $filterType = $request->input('filter_type', 'tagged');
        $gradeFilter = $request->input('grade');
        $modeFilter = $request->input('mode');
        $sectionFilter = $request->input('section');
        $search = trim((string) $request->input('search', ''));
        $perPage = min(max((int) $request->input('per_page', 25), 10), 100);

        // Base query for temporary accounts
        $query = Student::with(['applicant.user', 'applicant.payment', 'studentSection.section'])
            ->whereNull('students.deleted_at');

        // Apply filter tab
        switch ($filterType) {
            case 'tagged':
            default:
                $query->where(function ($q) {
                    $q->whereHas('applicant', function ($a) {
                        $a->where('last_name', 'like', '%[TEMP%')
                          ->orWhere('first_name', 'like', '%[TEMP%');
                    })->orWhereHas('user', function ($u) {
                        $u->where('name', 'like', '%[TEMP%');
                    })->orWhere('students.school_email', 'like', '%temp%');
                });
                break;

            case 'missing_all':
                $query->whereHas('applicant', function ($a) {
                    $a->where(function ($d) {
                        $d->whereNull('photo_2x2_url')->orWhere('photo_2x2_url', '');
                    })->where(function ($d) {
                        $d->whereNull('birth_cert_url')->orWhere('birth_cert_url', '');
                    })->where(function ($d) {
                        $d->whereNull('report_card_url')->orWhere('report_card_url', '');
                    })->where(function ($d) {
                        $d->whereNull('enrollment_fee_receipt_url')->orWhere('enrollment_fee_receipt_url', '');
                    });
                });
                break;

            case 'missing_payment':
                $query->whereHas('applicant', function ($a) {
                    $a->whereNull('enrollment_fee_receipt_url')->orWhere('enrollment_fee_receipt_url', '');
                });
                break;

            case 'incomplete_docs':
                $query->where(function ($mainQ) {
                    $mainQ->whereHas('applicant', function ($a) {
                        $a->whereNull('photo_2x2_url')->orWhere('photo_2x2_url', '')
                          ->orWhereNull('birth_cert_url')->orWhere('birth_cert_url', '')
                          ->orWhereNull('report_card_url')->orWhere('report_card_url', '')
                          ->orWhereNull('enrollment_fee_receipt_url')->orWhere('enrollment_fee_receipt_url', '');
                    });
                });
                break;

            case 'all_temporary_pool':
                $query->where(function ($mainQ) {
                    $mainQ->whereHas('applicant', function ($a) {
                        $a->where('last_name', 'like', '%[TEMP%')
                          ->orWhere('first_name', 'like', '%[TEMP%')
                          ->orWhereNull('photo_2x2_url')->orWhere('photo_2x2_url', '')
                          ->orWhereNull('birth_cert_url')->orWhere('birth_cert_url', '')
                          ->orWhereNull('report_card_url')->orWhere('report_card_url', '')
                          ->orWhereNull('enrollment_fee_receipt_url')->orWhere('enrollment_fee_receipt_url', '');
                    })->orWhereHas('user', function ($u) {
                        $u->where('name', 'like', '%[TEMP%');
                    });
                });
                break;
        }

        // Grade level filter
        if (! empty($gradeFilter)) {
            $query->where('students.grade_level', $gradeFilter);
        }

        // Learning Mode filter
        if (! empty($modeFilter)) {
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

        // Section filter
        if (! empty($sectionFilter)) {
            if ($sectionFilter === 'unassigned') {
                $query->where(function ($q) {
                    $q->whereNull('students.section')
                      ->orWhere('students.section', '');
                });
            } elseif ($sectionFilter === 'assigned') {
                $query->whereNotNull('students.section')
                      ->where('students.section', '!=', '');
            } else {
                $query->where('students.section', $sectionFilter);
            }
        }

        // Search filter
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('students.student_number', 'like', "%{$search}%")
                  ->orWhere('students.school_email', 'like', "%{$search}%")
                  ->orWhereHas('applicant', function ($a) use ($search) {
                      $a->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('lrn', 'like', "%{$search}%")
                        ->orWhere('parent_email', 'like', "%{$search}%")
                        ->orWhere('parent_mobile', 'like', "%{$search}%")
                        ->orWhere('father_first_name', 'like', "%{$search}%")
                        ->orWhere('father_last_name', 'like', "%{$search}%")
                        ->orWhere('mother_first_name', 'like', "%{$search}%")
                        ->orWhere('mother_last_name', 'like', "%{$search}%");
                  })->orWhereHas('user', function ($u) use ($search) {
                      $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Telemetry calculation
        $baseStudentQuery = Student::whereNull('deleted_at');

        $taggedCount = (clone $baseStudentQuery)->where(function ($q) {
            $q->whereHas('applicant', function ($a) {
                $a->where('last_name', 'like', '%[TEMP%')
                  ->orWhere('first_name', 'like', '%[TEMP%');
            })->orWhereHas('user', function ($u) {
                $u->where('name', 'like', '%[TEMP%');
            })->orWhere('school_email', 'like', '%temp%');
        })->count();

        $missingAllCount = (clone $baseStudentQuery)->whereHas('applicant', function ($a) {
            $a->where(function ($d) {
                $d->whereNull('photo_2x2_url')->orWhere('photo_2x2_url', '');
            })->where(function ($d) {
                $d->whereNull('birth_cert_url')->orWhere('birth_cert_url', '');
            })->where(function ($d) {
                $d->whereNull('report_card_url')->orWhere('report_card_url', '');
            })->where(function ($d) {
                $d->whereNull('enrollment_fee_receipt_url')->orWhere('enrollment_fee_receipt_url', '');
            });
        })->count();

        $missingPaymentCount = (clone $baseStudentQuery)->whereHas('applicant', function ($a) {
            $a->whereNull('enrollment_fee_receipt_url')->orWhere('enrollment_fee_receipt_url', '');
        })->count();

        $incompleteCount = (clone $baseStudentQuery)->where(function ($mainQ) {
            $mainQ->whereHas('applicant', function ($a) {
                $a->whereNull('photo_2x2_url')->orWhere('photo_2x2_url', '')
                  ->orWhereNull('birth_cert_url')->orWhere('birth_cert_url', '')
                  ->orWhereNull('report_card_url')->orWhere('report_card_url', '')
                  ->orWhereNull('enrollment_fee_receipt_url')->orWhere('enrollment_fee_receipt_url', '');
            });
        })->count();

        $totalActiveStudents = (clone $baseStudentQuery)->count();

        // Order results
        $gradeField = "FIELD(students.grade_level, 'Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12')";
        $students = $query
            ->leftJoin('enrollment_applicants as sort_app', 'sort_app.id', '=', 'students.enrollment_applicant_id')
            ->select('students.*')
            ->orderByRaw("CASE WHEN {$gradeField} = 0 THEN 99 ELSE {$gradeField} END ASC")
            ->orderBy('sort_app.last_name', 'asc')
            ->orderBy('sort_app.first_name', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        // Active sections for filter dropdown
        $sections = Section::orderBy('name', 'asc')->get();

        // Build Announcement / Follow-up text (STRICTLY ZERO EMOJIS)
        $announcementLines = [];
        $announcementLines[] = "AMIS TEMPORARY STUDENT RECORDS AND PENDING REQUIREMENTS";
        $announcementLines[] = "School Year 2026-2027";
        $announcementLines[] = "Date Generated: " . now()->format('F d, Y h:i A');
        $announcementLines[] = "Category: " . strtoupper(str_replace('_', ' ', $filterType));
        $announcementLines[] = "Total Listed: " . $students->total();
        $announcementLines[] = "------------------------------------------------------------";
        $announcementLines[] = "";

        foreach ($students as $idx => $st) {
            $app = $st->applicant;
            $name = $app ? trim($app->last_name . ', ' . $app->first_name . ' ' . ($app->middle_name ?: '')) : ('Student #' . $st->student_number);
            $grade = $st->grade_level ?: 'Unassigned Grade';
            $section = $st->section ?: 'Unassigned Section';
            $mode = ($app && $app->learning_mode) ? $app->learning_mode : 'ODL';

            $missingDocs = [];
            if (empty($app?->photo_2x2_url)) $missingDocs[] = '2x2 Photo';
            if (empty($app?->birth_cert_url)) $missingDocs[] = 'PSA Birth Certificate';
            if (empty($app?->report_card_url)) $missingDocs[] = 'Form 138 / SF9 Report Card';
            if (empty($app?->enrollment_fee_receipt_url)) $missingDocs[] = 'Enrollment Fee Receipt';

            $docStatus = empty($missingDocs) ? 'Requirements Complete (4/4)' : 'Missing: ' . implode(', ', $missingDocs);
            $isTagged = ($app && (str_contains($app->last_name, '[TEMP') || str_contains($app->first_name, '[TEMP')));

            $announcementLines[] = ($idx + 1) . ". [" . $st->student_number . "] " . $name . " | " . $grade . " - " . $section . " (" . $mode . ")";
            if ($isTagged) {
                $announcementLines[] = "   Status: Tagged Temporary Account";
            }
            $announcementLines[] = "   Documents: " . $docStatus;
            if ($app && ($app->parent_email || $app->parent_mobile)) {
                $parentName = trim(($app->father_first_name ? $app->father_first_name . ' ' . $app->father_last_name : '') ?: ($app->mother_first_name ? $app->mother_first_name . ' ' . $app->mother_last_name : 'Parent/Guardian'));
                $announcementLines[] = "   Contact: " . $parentName . " | Email: " . ($app->parent_email ?: 'N/A') . " | Phone: " . ($app->parent_mobile ?: 'N/A');
            }
            $announcementLines[] = "";
        }

        $announcementLines[] = "------------------------------------------------------------";
        $announcementLines[] = "Please coordinate with the AMIS Registrar to submit all pending requirements.";
        $announcementText = implode("\n", $announcementLines);

        return view('admin.students.temporary', compact(
            'students',
            'gradeOrder',
            'filterType',
            'gradeFilter',
            'modeFilter',
            'sectionFilter',
            'search',
            'perPage',
            'taggedCount',
            'missingAllCount',
            'missingPaymentCount',
            'incompleteCount',
            'totalActiveStudents',
            'sections',
            'announcementText'
        ));
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

            AdminAuditLog::record('temporary_student_cleared', true, "Removed temporary tag from student #{$student->student_number} ({$applicant->full_name}).");

            return back()->with('success', "Temporary tag successfully removed for student #{$student->student_number}. Record is now in standard student records.");
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

            return back()->with('success', "Student #{$student->student_number} moved to Temporary Accounts [TEMP ACC].");
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

        $actionWord = $action === 'tag' ? 'moved to Temporary Accounts [TEMP ACC]' : 'cleared of [TEMP ACC] tag and returned to Student Records';
        AdminAuditLog::record('temporary_student_bulk_' . $action, true, "Bulk {$actionWord} for {$count} student records.");

        return back()->with('success', "Successfully {$actionWord} for {$count} student(s).");
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filterType = $request->input('filter_type', 'tagged');
        $gradeFilter = $request->input('grade');
        $modeFilter = $request->input('mode');
        $sectionFilter = $request->input('section');
        $search = trim((string) $request->input('search', ''));

        $query = Student::with(['applicant.user', 'applicant.payment', 'studentSection.section'])
            ->whereNull('students.deleted_at');

        if ($filterType === 'tagged') {
            $query->where(function ($q) {
                $q->whereHas('applicant', function ($a) {
                    $a->where('last_name', 'like', '%[TEMP%')
                      ->orWhere('first_name', 'like', '%[TEMP%');
                })->orWhereHas('user', function ($u) {
                    $u->where('name', 'like', '%[TEMP%');
                })->orWhere('students.school_email', 'like', '%temp%');
            });
        } elseif ($filterType === 'missing_all') {
            $query->whereHas('applicant', function ($a) {
                $a->where(function ($d) {
                    $d->whereNull('photo_2x2_url')->orWhere('photo_2x2_url', '');
                })->where(function ($d) {
                    $d->whereNull('birth_cert_url')->orWhere('birth_cert_url', '');
                })->where(function ($d) {
                    $d->whereNull('report_card_url')->orWhere('report_card_url', '');
                })->where(function ($d) {
                    $d->whereNull('enrollment_fee_receipt_url')->orWhere('enrollment_fee_receipt_url', '');
                });
            });
        } elseif ($filterType === 'missing_payment') {
            $query->whereHas('applicant', function ($a) {
                $a->whereNull('enrollment_fee_receipt_url')->orWhere('enrollment_fee_receipt_url', '');
            });
        }

        if (! empty($gradeFilter)) {
            $query->where('students.grade_level', $gradeFilter);
        }

        if (! empty($modeFilter)) {
            if ($modeFilter === 'f2f') {
                $query->whereHas('applicant', fn($q) => $q->where('learning_mode', 'like', '%face%')->orWhere('learning_mode', 'like', '%f2f%'));
            } elseif ($modeFilter === 'odl') {
                $query->whereHas('applicant', fn($q) => $q->where('learning_mode', 'not like', '%face%')->where('learning_mode', 'not like', '%f2f%'));
            }
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('students.student_number', 'like', "%{$search}%")
                  ->orWhere('students.school_email', 'like', "%{$search}%")
                  ->orWhereHas('applicant', fn($a) => $a->where('last_name', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%"));
            });
        }

        $filename = 'amis_temporary_students_' . date('Y_m_d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Student Number',
                'Last Name',
                'First Name',
                'Middle Name',
                'Temporary Tagged',
                'Grade Level',
                'Section',
                'Learning Mode',
                'School Email',
                '2x2 Photo Status',
                'PSA Birth Cert Status',
                'Report Card Status',
                'Payment Receipt Status',
                'Total Requirements Submitted',
                'Parent Name',
                'Parent Email',
                'Parent Mobile',
            ]);

            $query->chunk(100, function ($students) use ($handle) {
                foreach ($students as $st) {
                    $app = $st->applicant;
                    $hasPhoto = ! empty($app?->photo_2x2_url);
                    $hasBirthCert = ! empty($app?->birth_cert_url);
                    $hasReportCard = ! empty($app?->report_card_url);
                    $hasReceipt = ! empty($app?->enrollment_fee_receipt_url);
                    $docCount = ($hasPhoto ? 1 : 0) + ($hasBirthCert ? 1 : 0) + ($hasReportCard ? 1 : 0) + ($hasReceipt ? 1 : 0);
                    $isTagged = ($app && (str_contains($app->last_name, '[TEMP') || str_contains($app->first_name, '[TEMP'))) ? 'YES' : 'NO';

                    $parentName = trim(($app?->father_first_name ? $app->father_first_name . ' ' . $app->father_last_name : '') ?: ($app?->mother_first_name ? $app->mother_first_name . ' ' . $app->mother_last_name : ''));

                    fputcsv($handle, [
                        $st->student_number,
                        $app?->last_name ?? '',
                        $app?->first_name ?? '',
                        $app?->middle_name ?? '',
                        $isTagged,
                        $st->grade_level ?? '',
                        $st->section ?? 'Unassigned',
                        $app?->learning_mode ?? 'ODL',
                        $st->school_email ?? '',
                        $hasPhoto ? 'Uploaded' : 'Missing',
                        $hasBirthCert ? 'Uploaded' : 'Missing',
                        $hasReportCard ? 'Uploaded' : 'Missing',
                        $hasReceipt ? 'Uploaded' : 'Missing',
                        "{$docCount} of 4",
                        $parentName,
                        $app?->parent_email ?? '',
                        $app?->parent_mobile ?? '',
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
