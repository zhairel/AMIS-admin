<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentExport;
use App\Models\EnrollmentApplicant;
use App\Models\Student;
use App\Jobs\ProcessBatchDocumentExportJob;
use App\Support\EnrollmentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Dompdf\Dompdf;
use Dompdf\Options;

class StudentExportController extends Controller
{
    public function exportCanva(Request $request)
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
        $query = Student::with(['applicant.user', 'studentSection.section'])
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy));

        if ($isTeacherAdminViewer) {
            $teacherGradeScope === null
                ? $query->whereRaw('1 = 0')
                : $query->where('students.grade_level', $teacherGradeScope);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('grade')) {
            $query->where('students.grade_level', $request->grade);
        }

        if ($request->filled('gender')) {
            $gender = strtolower((string) $request->gender);
            if (in_array($gender, ['male', 'female'], true)) {
                $query->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(gender) = ?', [$gender]));
            } elseif ($gender === 'not_set') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('applicant')
                        ->orWhereHas('applicant', fn ($a) => $a->whereNull('gender')->orWhere('gender', ''));
                });
            }
        }

        if ($request->filled('type')) {
            $type = strtolower((string) $request->type);
            if (in_array($type, ['new', 'old', 'transferee'], true)) {
                $query->whereHas('applicant', fn ($q) => $q->whereRaw('LOWER(student_type) LIKE ?', ["%{$type}%"]));
            }
        }

        if ($request->filled('mode')) {
            $mode = $request->mode;
            $query->whereHas('applicant', fn ($q) => $q->where('learning_mode', 'like', "%{$mode}%")
            );
        }

        $gradeOrder = [
            'Kinder 1', 'Kinder 2',
            'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6',
            'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12',
        ];

        $students = $query
            ->leftJoin('enrollment_applicants as sort_applicants', 'sort_applicants.id', '=', 'students.enrollment_applicant_id')
            ->select('students.*')
            ->orderByRaw('FIELD(students.grade_level, '.implode(',', array_fill(0, count($gradeOrder), '?')).')', $gradeOrder)
            ->orderByRaw("FIELD(sort_applicants.gender, 'Male', 'Female')")
            ->orderBy('sort_applicants.last_name', 'asc')
            ->orderBy('sort_applicants.first_name', 'asc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="AMIS_Canva_Bulk_Export_'.date('Ymd_His').'.csv"',
        ];

        $callback = function () use ($students) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Photo_URL',
                'Student_ID',
                'Last_Name',
                'Full_Name',
                'QR_Code_URL',
                'LRN',
                'Grade_Level',
                'Parent_Full_Name',
                'Address',
            ]);

            foreach ($students as $student) {
                $applicant = $student->applicant;
                if (! $applicant) {
                    continue;
                }

                $firstName = mb_strtoupper(trim($applicant->first_name ?? ''));
                $middleName = mb_strtoupper(trim($applicant->middle_name ?? ''));
                $lastName = mb_strtoupper(trim($applicant->last_name ?? ''));
                $suffix = mb_strtoupper(trim($applicant->suffix ?? ''));
                $middleInitial = EnrollmentApplicant::formatMiddleInitial($middleName) ?? '';

                $fullNameParts = array_filter([$firstName, $middleInitial, $lastName, $suffix]);
                $fullName = html_entity_decode(implode(' ', $fullNameParts), ENT_QUOTES, 'UTF-8');

                $photoUrl = 'https://amis.edu.ph/student-photo/'.$student->obfuscated_id.'.jpg';
                $verifyUrl = 'https://amis.edu.ph/v/'.$student->obfuscated_id;
                $qrCodeUrl = 'https://quickchart.io/qr?text='.urlencode($verifyUrl)
                           .'&dark=000000&light=ffffff&margin=1&format=png&size=300';

                $lrn = trim($applicant->lrn ?? '');

                $fatherFirst = mb_strtoupper(trim($applicant->father_first_name ?? ''));
                $fatherMiddle = mb_strtoupper(trim($applicant->father_middle_name ?? ''));
                $fatherLast = mb_strtoupper(trim($applicant->father_last_name ?? ''));
                $fatherMI = $fatherMiddle !== '' ? mb_substr($fatherMiddle, 0, 1).'.' : '';
                $fatherFull = trim(implode(' ', array_filter([$fatherFirst, $fatherMI, $fatherLast])));

                $motherFirst = mb_strtoupper(trim($applicant->mother_first_name ?? ''));
                $motherMiddle = mb_strtoupper(trim($applicant->mother_middle_name ?? ''));
                $motherLast = mb_strtoupper(trim($applicant->mother_last_name ?? ''));
                $motherMI = $motherMiddle !== '' ? mb_substr($motherMiddle, 0, 1).'.' : '';
                $motherFull = trim(implode(' ', array_filter([$motherFirst, $motherMI, $motherLast])));

                $parentFull = $fatherFull ?: $motherFull;
                $address = trim($applicant->address ?? $applicant->home_address ?? '');

                fputcsv($file, [
                    $photoUrl ?: '',
                    $student->student_number,
                    $lastName,
                    $fullName,
                    $qrCodeUrl,
                    $lrn,
                    $student->grade_level,
                    html_entity_decode($parentFull, ENT_QUOTES, 'UTF-8'),
                    $address,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportVerificationDatabase(Request $request)
    {
        $gradeOrder = [
            'Kinder 1', 'Kinder 2',
            'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6',
            'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12',
        ];

        $activeSy = app(\App\Services\SchoolYearService::class)->current();
        $students = Student::with('applicant')
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))
            ->when(auth()->user()?->isTeacherAdminViewer(), fn ($q) => $q->whereIn('grade_level', auth()->user()->adminVisibleGradeLevels())
            )
            ->whereHas('applicant')
            ->leftJoin('enrollment_applicants as sort_ea', 'sort_ea.id', '=', 'students.enrollment_applicant_id')
            ->select('students.*')
            ->orderByRaw('FIELD(students.grade_level, '.implode(',', array_fill(0, count($gradeOrder), '?')).')', $gradeOrder)
            ->orderBy('sort_ea.last_name', 'asc')
            ->orderBy('sort_ea.first_name', 'asc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="AMIS_Verification_Database_'.date('Ymd_His').'.csv"',
            'Cache-Control' => 'max-age=0',
        ];

        $callback = function () use ($students) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Grade_Level',
                'Gender',
                'Student_ID',
                'LRN',
                'Student_Type',
                'Last_Name',
                'First_Name',
                'Middle_Name',
                'Guardian_Name',
                'Contact_No',
                'Address',
            ]);

            foreach ($students as $student) {
                $applicant = $student->applicant;
                if (! $applicant) {
                    continue;
                }

                $lastName = mb_strtoupper(trim($applicant->last_name ?? ''));
                $firstName = mb_strtoupper(trim($applicant->first_name ?? ''));
                $middleName = mb_strtoupper(trim($applicant->middle_name ?? ''));

                $lrn = trim($applicant->lrn ?? '');
                $studentType = ucfirst(strtolower($applicant->student_type ?? 'New'));
                $gender = str_contains(strtolower($applicant->gender ?? 'Male'), 'female') ? 'Female' : 'Male';

                $fatherFirst = mb_strtoupper(trim($applicant->father_first_name ?? ''));
                $fatherMiddle = mb_strtoupper(trim($applicant->father_middle_name ?? ''));
                $fatherLast = mb_strtoupper(trim($applicant->father_last_name ?? ''));
                $fatherMI = $fatherMiddle !== '' ? mb_substr($fatherMiddle, 0, 1).'.' : '';
                $fatherFull = trim(implode(' ', array_filter([$fatherFirst, $fatherMI, $fatherLast])));

                $motherFirst = mb_strtoupper(trim($applicant->mother_first_name ?? ''));
                $motherMiddle = mb_strtoupper(trim($applicant->mother_middle_name ?? ''));
                $motherLast = mb_strtoupper(trim($applicant->mother_last_name ?? ''));
                $motherMI = $motherMiddle !== '' ? mb_substr($motherMiddle, 0, 1).'.' : '';
                $motherFull = trim(implode(' ', array_filter([$motherFirst, $motherMI, $motherLast])));

                $guardianName = html_entity_decode($fatherFull ?: $motherFull, ENT_QUOTES, 'UTF-8');

                $countryCode = trim($applicant->parent_country_code ?? '');
                $mobile = trim($applicant->parent_mobile ?? '');
                $contactNo = $mobile !== '' ? ltrim("$countryCode $mobile") : '';
                $address = trim($applicant->address ?? $applicant->home_address ?? '');

                fputcsv($file, [
                    $student->grade_level,
                    $gender,
                    $student->student_number,
                    $lrn,
                    $studentType,
                    $lastName,
                    $firstName,
                    $middleName,
                    $guardianName,
                    $contactNo,
                    html_entity_decode($address, ENT_QUOTES, 'UTF-8'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function downloadDocumentsZip(Request $request)
    {
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '2048M');

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

        $applyFilters = function ($query) use ($request, $isTeacherAdminViewer, $teacherGradeScope, $activeSy) {
            if ($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy)) {
                $query->where('students.school_year', $activeSy);
            }

            if ($isTeacherAdminViewer) {
                $teacherGradeScope === null
                    ? $query->whereRaw('1 = 0')
                    : $query->where('students.grade_level', $teacherGradeScope);
            }

            if ($request->filled('student_id')) {
                $query->where('students.id', $request->student_id);
            }

            if ($request->filled('student_number')) {
                $query->where('students.student_number', $request->student_number);
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

            return $query;
        };

        $baseCount = $applyFilters(Student::query())->count();
        if ($baseCount === 0) {
            return back()->with('error', 'No student records found matching the selected filters.');
        }

        $zip = new ZipArchive;
        $fileName = 'Official_Student_Records_SY_2026-2027_'.($request->filled('grade') ? str_replace(' ', '_', $request->grade) : 'All_Grades').'_'.date('Ymd_His').'.zip';
        $tempFile = tempnam(sys_get_temp_dir(), 'zip');

        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Could not initialize ZIP archive creation.');
        }

        $filesAdded = 0;

        $includeEnrolmentHtml = ($baseCount <= 10);

        $applyFilters(Student::with(['applicant.payment', 'studentSection.section']))
            ->chunk(50, function ($students) use (&$zip, &$filesAdded, $includeEnrolmentHtml) {
                foreach ($students as $student) {
                    $appl = $student->applicant;
                    if (! $appl) {
                        continue;
                    }

                    $gradeFolder = trim($student->grade_level ?: 'Grade 1');
                    if (preg_match('/^Grade\s*(\d+)$/i', $gradeFolder, $m)) {
                        $gShort = 'G'.$m[1];
                    } elseif (preg_match('/^Kinder\s*(\d+)$/i', $gradeFolder, $m)) {
                        $gShort = 'K'.$m[1];
                    } else {
                        $gShort = $gradeFolder;
                    }

                    $learningMode = strtolower($appl->learning_mode ?? '');
                    $isF2f = str_contains($learningMode, 'face') || str_contains($learningMode, 'f2f');

                    $lastName = mb_strtoupper(trim($appl->last_name ?? $student->last_name ?? 'STUDENT'));
                    $firstName = mb_strtoupper(trim($appl->first_name ?? $student->first_name ?? 'PROFILE'));
                    $studentNum = trim($student->student_number ?? '');

                    $studentFolderName = trim("{$lastName} {$firstName}");
                    if (! empty($studentNum)) {
                        $studentFolderName .= " ({$studentNum})";
                    }
                    if (empty($studentFolderName)) {
                        $studentFolderName = 'STUDENT '.$student->id;
                    }

                    if ($isF2f) {
                        $basePath = "{$gShort}/F2F/{$studentFolderName}";
                    } else {
                        $shiftFolder = '1ST SHIFT';
                        if (str_contains($learningMode, '2nd') || str_contains($learningMode, 'second') || str_contains($learningMode, 'shift 2')) {
                            $shiftFolder = '2ND SHIFT';
                        }
                        $basePath = "{$gShort}/ODL/{$shiftFolder}/{$studentFolderName}";
                    }

                    if ($includeEnrolmentHtml) {
                        try {
                            $officialDoc = $student->officialEnrollmentForm;
                            $localAbsPath = $officialDoc?->local_path ? EnrollmentStorage::getAbsolutePath($officialDoc->local_path) : null;

                            if ($localAbsPath && file_exists($localAbsPath)) {
                                $zip->addFile($localAbsPath, "{$basePath}/{$studentFolderName} - Official Enrollment Form.pdf");
                                $filesAdded++;
                            } else {
                                $siblings = $appl->user_id ? EnrollmentApplicant::where('user_id', $appl->user_id)->where('id', '!=', $appl->id)->get() : [];
                                $enrolmentHtml = view('admin.students.print-enrolment-form', [
                                    'student' => $student,
                                    'applicant' => $appl,
                                    'siblings' => $siblings,
                                    'isPdf' => true,
                                ])->render();
                                $zip->addFromString("{$basePath}/Enrollment Application Form - {$studentFolderName}.html", $enrolmentHtml);
                                $filesAdded++;
                                unset($enrolmentHtml, $siblings);
                            }
                        } catch (\Exception $e) {
                            Log::warning("Failed to attach enrolment form for student {$student->id}: ".$e->getMessage());
                        }
                    }

                    $docTypes = [
                        '2x2_Photo' => $appl->photo_2x2_url,
                        'Birth_Certificate' => $appl->birth_cert_url,
                        'Report_Card' => $appl->report_card_url,
                        'Marriage_Contract' => $appl->marriage_contract_url,
                        'Medical_Record' => $appl->medical_record_url,
                        'Affidavit' => $appl->affidavit_url,
                        'Payment_Receipt' => $appl->payment?->receipt_url,
                        'Student_ID_Card' => $student->student_id_url,
                    ];

                    foreach ($docTypes as $label => $relativeUrl) {
                        if (empty($relativeUrl)) {
                            continue;
                        }

                        $absolutePath = EnrollmentStorage::getAbsolutePath($relativeUrl);
                        if ($absolutePath && file_exists($absolutePath)) {
                            $ext = pathinfo($absolutePath, PATHINFO_EXTENSION);
                            $zipPath = $basePath.'/'.$studentFolderName.' - '.$label.($ext ? '.'.$ext : '');
                            $zip->addFile($absolutePath, $zipPath);
                            $filesAdded++;
                        }
                    }
                }
                gc_collect_cycles();
            });

        $zip->close();

        if ($filesAdded === 0) {
            @unlink($tempFile);

            return back()->with('error', 'No document files or data could be compiled for the matched students.');
        }

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    public function downloadEnrolmentFormsZip(Request $request)
    {
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '2048M');

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

        $applyFilters = function ($query) use ($request, $isTeacherAdminViewer, $teacherGradeScope, $activeSy) {
            if ($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy)) {
                $query->where('students.school_year', $activeSy);
            }

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

            return $query;
        };

        $baseCount = $applyFilters(Student::query())->count();
        if ($baseCount === 0) {
            return back()->with('error', 'No student records found matching the selected filters.');
        }

        $format = strtolower($request->input('format', 'html'));

        $zip = new ZipArchive;
        $extName = ($format === 'docx') ? 'docx' : 'html';
        $fileName = 'Enrollment_Forms_SY_2026-2027_'.($request->filled('grade') ? str_replace(' ', '_', $request->grade) : 'All_Grades').'_'.date('Ymd_His').'.zip';
        $tempFile = tempnam(sys_get_temp_dir(), 'zip');

        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Could not initialize ZIP archive creation.');
        }

        $filesAdded = 0;

        $applyFilters(Student::with(['applicant', 'studentSection.section']))
            ->chunk(50, function ($students) use (&$zip, &$filesAdded, $format) {
                $userCache = $students->pluck('applicant.user_id')->filter()->unique();
                $allSiblings = EnrollmentApplicant::whereIn('user_id', $userCache)->get()->groupBy('user_id');

                foreach ($students as $student) {
                    $appl = $student->applicant;
                    if (! $appl) {
                        continue;
                    }

                    $lastName = mb_strtoupper(trim($appl->last_name ?? $student->last_name ?? 'STUDENT'));
                    $firstName = mb_strtoupper(trim($appl->first_name ?? $student->first_name ?? 'PROFILE'));
                    $studentFolderName = trim("{$lastName} {$firstName}");
                    if (empty($studentFolderName)) {
                        $studentFolderName = 'STUDENT '.$student->student_number;
                    }

                    $gradeFolder = trim($student->grade_level ?: 'Grade 1');
                    if (preg_match('/^Grade\s*(\d+)$/i', $gradeFolder, $m)) {
                        $gShort = 'G'.$m[1];
                    } elseif (preg_match('/^Kinder\s*(\d+)$/i', $gradeFolder, $m)) {
                        $gShort = 'K'.$m[1];
                    } else {
                        $gShort = $gradeFolder;
                    }

                    $learningMode = strtolower($appl->learning_mode ?? '');
                    $isF2f = str_contains($learningMode, 'face') || str_contains($learningMode, 'f2f');

                    if ($isF2f) {
                        $basePath = "{$gShort}/F2F";
                    } else {
                        $shiftFolder = '1ST SHIFT';
                        if (str_contains($learningMode, '2nd') || str_contains($learningMode, 'second') || str_contains($learningMode, 'shift 2')) {
                            $shiftFolder = '2ND SHIFT';
                        }
                        $basePath = "{$gShort}/ODL/{$shiftFolder}";
                    }

                    try {
                        $siblings = $appl->user_id ? ($allSiblings[$appl->user_id] ?? collect())->reject(fn ($a) => $a->id === $appl->id) : collect();
                        $enrolmentHtml = view('admin.students.print-enrolment-form', [
                            'student' => $student,
                            'applicant' => $appl,
                            'siblings' => $siblings,
                            'isPdf' => true,
                        ])->render();

                        if ($format === 'pdf') {
                            $options = new Options();
                            $options->set('isHtml5ParserEnabled', true);
                            $options->set('isRemoteEnabled', true);
                            $options->set('defaultFont', 'sans-serif');
                            $dompdf = new Dompdf($options);
                            $dompdf->loadHtml($enrolmentHtml);
                            $dompdf->setPaper('A4', 'portrait');
                            $dompdf->render();
                            $pdfContent = $dompdf->output();

                            $zip->addFromString("{$basePath}/Enrollment Application Form - {$studentFolderName}.pdf", $pdfContent);
                            unset($dompdf, $pdfContent);
                        } elseif ($format === 'docx') {
                            $wordHtml = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
                            $wordHtml .= '<head><meta charset="utf-8"><title>Enrollment Form</title>';
                            $wordHtml .= '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>90</w:Zoom></w:WordDocument></xml><![endif]--></head>';
                            $wordHtml .= '<body>' . $enrolmentHtml . '</body></html>';

                            $zip->addFromString("{$basePath}/Enrollment Application Form - {$studentFolderName}.docx", $wordHtml);
                            unset($wordHtml);
                        } else {
                            $zip->addFromString("{$basePath}/Enrollment Application Form - {$studentFolderName}.html", $enrolmentHtml);
                        }
                        $filesAdded++;
                        unset($enrolmentHtml, $siblings);
                    } catch (\Exception $e) {
                        Log::warning("Failed to render enrolment form for student {$student->id}: ".$e->getMessage());
                    }
                }
                gc_collect_cycles();
            });

        $zip->close();

        if ($filesAdded === 0) {
            @unlink($tempFile);

            return back()->with('error', 'No files could be added to the ZIP archive.');
        }

        return response()->download($tempFile, $fileName);
    }

    /**
     * Start an asynchronous background document export job with database progress tracking.
     */
    public function startBatchExport(Request $request)
    {
        $request->validate([
            'format' => 'nullable|string|in:html,docx,pdf',
            'grade' => 'nullable|string',
            'mode' => 'nullable|string',
            'gender' => 'nullable|string',
            'search' => 'nullable|string',
        ]);

        $activeSy = app(\App\Services\SchoolYearService::class)->current();
        $query = Student::query()
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy));

        if ($request->filled('grade')) {
            $query->where('grade_level', $request->grade);
        }

        if ($request->filled('gender')) {
            $gender = strtolower($request->gender);
            $query->whereHas('applicant', function ($q) use ($gender) {
                if ($gender === 'male' || $gender === 'm') {
                    $q->whereRaw('LOWER(gender) LIKE ?', ['m%']);
                } elseif ($gender === 'female' || $gender === 'f') {
                    $q->whereRaw('LOWER(gender) LIKE ?', ['f%']);
                }
            });
        }

        if ($request->filled('mode')) {
            $mode = strtolower($request->mode);
            $query->whereHas('applicant', function ($q) use ($mode) {
                if (str_contains($mode, 'face') || str_contains($mode, 'f2f')) {
                    $q->whereRaw('LOWER(learning_mode) LIKE ?', ['%face%'])
                      ->orWhereRaw('LOWER(learning_mode) LIKE ?', ['%f2f%']);
                } elseif (str_contains($mode, 'online') || str_contains($mode, 'odl')) {
                    $q->whereRaw('LOWER(learning_mode) LIKE ?', ['%online%'])
                      ->orWhereRaw('LOWER(learning_mode) LIKE ?', ['%odl%'])
                      ->orWhereRaw('LOWER(learning_mode) LIKE ?', ['%flexible%']);
                }
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('student_number', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%");
            });
        }

        $totalCount = $query->count();

        if ($totalCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No student records match the selected filters.',
            ], 422);
        }

        $export = DocumentExport::create([
            'user_id' => $request->user()?->id,
            'document_type' => 'enrolment_form',
            'format' => strtolower($request->input('format', 'pdf')),
            'filter_grade' => $request->grade,
            'filter_mode' => $request->mode,
            'filter_gender' => $request->gender,
            'filter_search' => $request->search,
            'total_count' => $totalCount,
            'processed_count' => 0,
            'status' => 'pending',
        ]);

        // Dispatch background CLI runner so AJAX POST returns instantly
        $artisanPath = base_path('artisan');
        $phpBinary = (defined('PHP_BINARY') && file_exists(PHP_BINARY)) ? PHP_BINARY : '/usr/local/bin/php';
        if (str_contains(PHP_OS_FAMILY, 'Windows')) {
            pclose(popen("start /B {$phpBinary} {$artisanPath} export:process {$export->id}", "r"));
        } else {
            exec("{$phpBinary} {$artisanPath} export:process {$export->id} > /dev/null 2>&1 &");
        }

        return response()->json([
            'success' => true,
            'export_id' => $export->id,
            'total_count' => $totalCount,
            'status' => $export->status,
        ]);
    }

    /**
     * Poll AJAX export status and progress percentage.
     */
    public function getBatchExportStatus($id)
    {
        $export = DocumentExport::findOrFail($id);

        return response()->json([
            'id' => $export->id,
            'status' => $export->status,
            'total_count' => $export->total_count,
            'processed_count' => $export->processed_count,
            'progress_percentage' => $export->progress_percentage,
            'file_name' => $export->file_name,
            'file_size_formatted' => $export->formatted_file_size,
            'log_message' => $export->status === 'processing' ? $export->error_message : null,
            'error_message' => $export->status === 'failed' ? $export->error_message : null,
            'download_url' => $export->status === 'completed' ? route('admin.students.download-batch-export', ['id' => $export->id]) : null,
        ]);
    }

    /**
     * Download completed export file from public/exports storage.
     */
    public function downloadBatchExportFile($id)
    {
        $export = DocumentExport::findOrFail($id);

        if ($export->status !== 'completed' || empty($export->file_path)) {
            return back()->with('error', 'The document export has not finished or failed.');
        }

        if (!Storage::disk('public')->exists($export->file_path)) {
            return back()->with('error', 'The generated export file could not be found on the server.');
        }

        return Storage::disk('public')->download($export->file_path, $export->file_name);
    }
}
