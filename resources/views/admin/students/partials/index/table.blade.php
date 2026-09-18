@php
    $msSyncColor = ['enrolled' => 'green', 'failed' => 'red', 'pending' => 'yellow'];
    $msSyncLabel = ['enrolled' => 'Synced', 'failed' => 'Sync Failed', 'pending' => 'Pending Teams'];
    $isTeacherAdminViewer = auth()->user()?->isTeacherAdminViewer() ?? false;
    $actionButtonClass = 'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400';
@endphp

<!-- Main Table Container -->
<div id="tableContainer">
    <div class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full min-w-[1180px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                <tr class="border-b border-slate-100">
                    <th class="w-32 px-5 py-4 font-bold">
                        <a href="{{ $sortUrl('student_id') }}" class="flex items-center gap-1 hover:text-slate-600 transition print:hidden">
                            AMIS ID
                            <i data-lucide="{{ $sortIcon('student_id') }}" class="h-3 w-3"></i>
                        </a>
                        <span class="hidden print:inline">AMIS ID</span>
                    </th>
                    <th class="w-80 px-5 py-4 font-bold">
                        <a href="{{ $sortUrl('name') }}" class="flex items-center gap-1 hover:text-slate-600 transition print:hidden">
                            Student Name
                            <i data-lucide="{{ $sortIcon('name') }}" class="h-3 w-3"></i>
                        </a>
                        <span class="hidden print:inline">Student Name</span>
                    </th>
                    <th class="w-28 px-5 py-4 font-bold">Type</th>
                    <th class="w-44 px-5 py-4 font-bold">Mode</th>
                    <th class="w-28 px-5 py-4 font-bold">
                        <a href="{{ $sortUrl('gender') }}" class="flex items-center gap-1 hover:text-slate-600 transition print:hidden">
                            Gender
                            <i data-lucide="{{ $sortIcon('gender') }}" class="h-3 w-3"></i>
                        </a>
                        <span class="hidden print:inline">Gender</span>
                    </th>
                    <th class="w-24 px-5 py-4 font-bold">
                        <a href="{{ $sortUrl('grade') }}" class="flex items-center gap-1 hover:text-slate-600 transition print:hidden">
                            Grade
                            <i data-lucide="{{ $sortIcon('grade') }}" class="h-3 w-3"></i>
                        </a>
                        <span class="hidden print:inline">Grade</span>
                    </th>
                    <th class="w-48 px-5 py-4 font-bold">Section</th>
                    <th class="w-44 px-5 py-4 text-right font-bold print:hidden">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($students as $student)
                     @php
                        $firstName = trim($student->applicant->first_name ?? '');
                        $middleName = trim($student->applicant->middle_name ?? '');
                        $lastName = trim($student->applicant->last_name ?? '');
                        $suffix = trim($student->applicant->suffix ?? '');
                        $middleInitial = \App\Models\EnrollmentApplicant::formatMiddleInitial($middleName) ?? '';
                        $fullName = html_entity_decode(implode(' ', array_filter([$firstName, $middleInitial, $lastName, $suffix])), ENT_QUOTES, 'UTF-8');
                        $name = $fullName ? \Illuminate\Support\Str::upper($fullName) : 'STUDENT PROFILE';
                        $initials = collect(explode(' ', $name))->filter()->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->join('');
                        $photoUrl = \App\Support\EnrollmentStorage::url($student->applicant->photo_2x2_url ?? null);
                        $msStatus = $student->studentSection->ms_status ?? ($student->ms_user_id && $student->ms_license_active ? 'enrolled' : 'pending');
                        $gender = strtolower((string) ($student->applicant->gender ?? ''));
                        $genderLabel = $gender === 'male' ? 'MALE' : ($gender === 'female' ? 'FEMALE' : 'NOT SET');
                        
                        $studentType = $student->applicant ? ($student->applicant->student_type ?? 'New') : 'New';
                        $learningMode = $student->applicant ? ($student->applicant->learning_mode ?? 'F2F') : 'F2F';
                        $lmLower = strtolower((string) $learningMode);
                        $isF2f = str_contains($lmLower, 'face') || str_contains($lmLower, 'f2f');
                        
                        if ($isF2f) {
                            $modeBadge = 'F2F';
                        } elseif (str_contains($lmLower, '1st')) {
                            $modeBadge = 'ODL - 1ST SHIFT';
                        } elseif (str_contains($lmLower, '2nd')) {
                            $modeBadge = 'ODL - 2ND SHIFT';
                        } else {
                            $modeBadge = 'ODL';
                        }

                        $isHashed = str_starts_with($student->temp_password ?? '', '$');
                        $studentTypeLabel = strtoupper($studentType ?: 'NEW');
                        $typeLower = strtolower($studentType ?: '');
                        $typeBadgeClass = match (true) {
                            str_contains($typeLower, 'transferee') => 'bg-sky-50 text-sky-700 border-sky-200',
                            str_contains($typeLower, 'new') => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            str_contains($typeLower, 'old') => 'bg-slate-100 text-slate-700 border-slate-200',
                            str_contains($typeLower, 'returnee') => 'bg-amber-50 text-amber-800 border-amber-200',
                            default => 'bg-slate-50 text-slate-600 border-slate-200',
                        };
                    @endphp
                    <tr class="transition-colors duration-100 ease-in-out hover:bg-slate-50">
                        <!-- Student Number -->
                        <td class="whitespace-nowrap px-5 py-4">
                            <span class="inline-flex items-center font-mono font-black text-xs text-emerald-800 bg-emerald-50 border border-emerald-200/80 px-2.5 py-1 rounded-lg tracking-tight">
                                {{ $student->student_number ?? '-' }}
                            </span>
                        </td>

                        <!-- Student Photo & Name -->
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <x-smart-image
                                    :src="$photoUrl"
                                    :alt="$name"
                                    :fallback-initials="$initials ?: 'ST'"
                                    size="40"
                                    rounded="rounded-md"
                                    containerClass="bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 font-extrabold print:hidden"
                                    :eager="false"
                                />
                                <div>
                                    <div class="font-extrabold text-slate-950 break-words max-w-[240px] leading-tight">{{ $name }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs font-medium text-slate-500 print:hidden">
                                         <span>SY {{ $student->school_year ?? '-' }}</span>
                                         @if (!$student->applicant || $student->applicant->completion_percentage < 100)
                                             <span class="text-slate-300">•</span>
                                             @php
                                                 $missingList = $student->applicant ? implode(', ', $student->applicant->incomplete_fields) : 'No profile';
                                             @endphp
                                             <span class="inline-flex cursor-help items-center gap-1 font-semibold text-amber-700" title="Missing: {{ $missingList }}">
                                                 <i data-lucide="triangle-alert" class="h-3 w-3"></i>
                                                 Profile incomplete
                                             </span>
                                         @endif
                                     </div>
                                </div>
                            </div>
                        </td>

                        <!-- Student Type -->
                        <td class="whitespace-nowrap px-5 py-4">
                            <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-black border uppercase {{ $typeBadgeClass }}">
                                {{ $studentTypeLabel }}
                            </span>
                        </td>

                        <!-- Mode -->
                        <td class="whitespace-nowrap px-5 py-4">
                            @if ($isF2f)
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-teal-50 px-2.5 py-1 text-xs font-black text-teal-700 border border-teal-200" title="Face to Face">
                                    <i data-lucide="building-2" class="h-3.5 w-3.5"></i>
                                    F2F
                                </span>
                            @elseif ($modeBadge === 'ODL - 1ST SHIFT')
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-black text-indigo-700 border border-indigo-200" title="Online - 1st Shift">
                                    <i data-lucide="laptop" class="h-3.5 w-3.5"></i>
                                    ODL - 1ST SHIFT
                                </span>
                            @elseif ($modeBadge === 'ODL - 2ND SHIFT')
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-purple-50 px-2.5 py-1 text-xs font-black text-purple-700 border border-purple-200" title="Online - 2nd Shift">
                                    <i data-lucide="laptop" class="h-3.5 w-3.5"></i>
                                    ODL - 2ND SHIFT
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-black text-indigo-700 border border-indigo-200" title="Online Distance Learning">
                                    <i data-lucide="laptop" class="h-3.5 w-3.5"></i>
                                    ODL
                                </span>
                            @endif
                        </td>

                        <!-- Gender -->
                        <td class="whitespace-nowrap px-5 py-4">
                            @if ($gender === 'female')
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-rose-50 px-2.5 py-1 text-xs font-black text-rose-700 border border-rose-200">
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                    FEMALE
                                </span>
                            @elseif ($gender === 'male')
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-blue-50 px-2.5 py-1 text-xs font-black text-blue-700 border border-blue-200">
                                    <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                    MALE
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-md bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-400 border border-slate-200">
                                    NOT SET
                                </span>
                            @endif
                        </td>

                        <!-- Grade -->
                        <td class="whitespace-nowrap px-5 py-4">
                            @php
                                $gradeRaw = $student->grade_level ?? '-';
                                $gradeAbbr = preg_replace(
                                    ['/^Kinder\s*1$/i', '/^Kinder\s*2$/i', '/^Grade\s*(\d+)$/i'],
                                    ['K1', 'K2', 'G$1'],
                                    $gradeRaw
                                );
                            @endphp
                            <span class="inline-flex items-center justify-center font-black text-xs text-slate-800 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-md min-w-[38px]">
                                {{ $gradeAbbr }}
                            </span>
                        </td>

                        <!-- Section / Class Occupancy -->
                        <td class="whitespace-nowrap px-5 py-4">
                            @if($student->studentSection && $student->studentSection->section)
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1 text-xs font-black text-slate-800 border border-slate-200 shadow-2xs">
                                    <i data-lucide="users" class="h-3.5 w-3.5 text-slate-400"></i>
                                    {{ $student->studentSection->section->name }}
                                </span>
                            @elseif(!empty($student->section))
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1 text-xs font-black text-slate-800 border border-slate-200 shadow-2xs">
                                    <i data-lucide="users" class="h-3.5 w-3.5 text-slate-400"></i>
                                    {{ $student->section }}
                                </span>
                            @elseif($isF2f)
                                <span class="text-xs text-slate-300 font-mono">—</span>
                            @else
                                <span class="inline-flex items-center rounded-md bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 border border-amber-200 italic">
                                    Unassigned
                                </span>
                            @endif
                        </td>

                        <!-- Action -->
                         <td class="whitespace-nowrap px-5 py-4 text-right print:hidden">
                            @php
                                $applicant = $student->applicant;
                                $emergencyName = trim($applicant?->emergency_name ?? $applicant?->mother_name ?? $applicant?->father_name ?? $applicant?->guardian_name ?? 'PARENT / GUARDIAN');
                                $emergencyRelationship = trim($applicant?->emergency_relationship ?? ($applicant?->emergency_name ? 'PARENT / GUARDIAN' : ($applicant?->mother_name ? 'MOTHER' : ($applicant?->father_name ? 'FATHER' : 'GUARDIAN'))));
                                $emergencyPhone = trim($applicant?->emergency_phone ?? $applicant?->contact_number ?? $applicant?->mother_contact ?? $applicant?->father_contact ?? $applicant?->guardian_contact ?? '-');
                                $emergencyAddress = trim($applicant?->emergency_address ?? $applicant?->address ?? $applicant?->home_address ?? $applicant?->street_address ?? '-');

                                $gradeColors = [
                                    'kinder 1' => '#f59e0b', 'k1' => '#f59e0b',
                                    'kinder 2' => '#f97316', 'k2' => '#f97316',
                                    'grade 1' => '#3b82f6',
                                    'grade 2' => '#06b6d4',
                                    'grade 3' => '#10b981',
                                    'grade 4' => '#8b5cf6',
                                    'grade 5' => '#ec4899',
                                    'grade 6' => '#6366f1',
                                    'grade 7' => '#14b8a6',
                                    'grade 8' => '#f43f5e',
                                    'grade 9' => '#84cc16',
                                    'grade 10' => '#a855f7',
                                    'grade 11' => '#eab308',
                                    'grade 12' => '#0284c7',
                                ];
                                $gLow = strtolower($student->grade_level ?? '');
                                $matchedColor = '#10b981';
                                foreach ($gradeColors as $k => $c) {
                                    if (str_contains($gLow, $k)) { $matchedColor = $c; break; }
                                }

                                $hash = base64_encode(((int)($student->student_number ?? 0)) + 987654);
                                $idCardData = [
                                    'id' => $student->id,
                                    'student_number' => $student->student_number ?? '-',
                                    'first_name' => $firstName,
                                    'last_name' => $lastName,
                                    'middle_initial' => $middleInitial,
                                    'suffix' => $suffix,
                                    'full_name' => $name,
                                    'grade' => $student->grade_level ?? 'Grade 1',
                                    'section' => $student->studentSection?->section?->name ?? 'Unassigned',
                                    'photo_url' => $photoUrl,
                                    'lrn' => $applicant?->lrn ?? '',
                                    'emergency_name' => $emergencyName,
                                    'emergency_relationship' => $emergencyRelationship,
                                    'emergency_phone' => $emergencyPhone,
                                    'emergency_address' => $emergencyAddress,
                                    'grade_color' => $matchedColor,
                                    'qr_url' => 'https://quickchart.io/qr?text=' . urlencode('https://amis.edu.ph/v/' . $hash) . '&dark=000000&light=ffffff&margin=1&format=png&size=300',
                                    'signature_qr' => 'https://quickchart.io/qr?text=' . urlencode('https://amis.edu.ph/signature') . '&dark=000000&light=ffffff&margin=1&format=png&size=300',
                                    'print_url' => route(request()->routeIs('admin.students.temporary*') ? 'admin.students.temporary' : 'admin.students.index', ['search' => $student->student_number, 'print_id' => 1]),
                                    'edit_layout_url' => route('admin.students.id-editor', $student),
                                ];
                            @endphp
                            <div class="flex items-center justify-end gap-1.5">
                                @if (str_contains($student->applicant?->last_name ?? '', '[TEMP') || str_contains($student->applicant?->first_name ?? '', '[TEMP') || str_contains($student->user?->name ?? '', '[TEMP'))
                                    <form method="POST" action="{{ route('admin.students.temporary.toggle-tag', $student) }}" onsubmit="return confirm('Remove temporary account tag [TEMP ACC] from this student and return to standard records?')" class="inline-block">
                                        @csrf
                                        <button type="submit" class="{{ $actionButtonClass }} hover:border-amber-300 hover:bg-amber-50 hover:text-amber-800 cursor-pointer" title="Remove Temporary Status [TEMP ACC]">
                                            <i data-lucide="user-minus" class="h-4 w-4 text-amber-600"></i>
                                        </button>
                                    </form>
                                @endif
                                <button type="button" onclick="openStudentIdCardModal({{ json_encode($idCardData) }})" class="{{ $actionButtonClass }} cursor-pointer" title="Preview & Print Official Student ID Card (Dual-Sided PNG)">
                                    <i data-lucide="contact-round" class="h-4 w-4 text-emerald-600"></i>
                                </button>
                                <button type="button" onclick="openStudentCredentialsModal({{ json_encode([
                                    'id' => $student->id,
                                    'student_number' => $student->student_number ?? '-',
                                    'name' => $name,
                                    'photo_url' => $photoUrl,
                                    'grade' => $student->grade_level ?? '-',
                                    'section' => $student->studentSection?->section?->name ?? 'Unassigned',
                                    'email' => $student->school_email ?? 'No Email Set',
                                    'temp_password' => $student->temp_password ?? 'N/A',
                                    'password_changed' => !empty($student->password_changed_at),
                                    'password_changed_at' => $student->password_changed_at ? $student->password_changed_at->format('M d, Y h:i A') : null,
                                    'ms_user_id' => $student->ms_user_id,
                                    'resend_url' => route('admin.students.resend', $student),
                                    'sync_url' => route('admin.ms-sync.student', $student),
                                    'print_url' => route(request()->routeIs('admin.students.temporary*') ? 'admin.students.temporary' : 'admin.students.index', ['search' => $student->student_number, 'print_credentials' => 1]),
                                ]) }})" class="{{ $actionButtonClass }} cursor-pointer" title="View Microsoft Account Credentials & Password">
                                    <i data-lucide="key-round" class="h-4 w-4 text-amber-600"></i>
                                </button>
                                <a href="{{ route('admin.students.download-docs-zip', ['search' => $student->student_number]) }}" download class="{{ $actionButtonClass }}" title="Download Student Documents ZIP (2x2 Photo, Birth Cert, Report Card, etc.)">
                                    <i data-lucide="archive" class="h-4 w-4 text-sky-600"></i>
                                </a>
                                <a href="{{ route('admin.students.print-enrolment-form', $student) }}" target="_blank" class="{{ $actionButtonClass }}" title="Print Enrollment Application Form">
                                    <i data-lucide="file-signature" class="h-4 w-4 text-blue-600"></i>
                                </a>
                                <a href="{{ route('admin.students.show', $student) }}" class="{{ $actionButtonClass }}" title="{{ $isTeacherAdminViewer ? 'View' : 'Manage' }}">
                                    <i data-lucide="file-search" class="h-4 w-4 text-indigo-600"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <!-- Premium Empty State -->
                    <tr>
                        <td colspan="8" class="px-5 py-16 text-center">
                            <div class="flex flex-col items-center justify-center space-y-4">
                                <div class="rounded-full bg-slate-50 p-4 text-slate-400 ring-8 ring-slate-50/50">
                                    <i data-lucide="users-2" class="h-10 w-10"></i>
                                </div>
                                <div class="space-y-1">
                                    <h3 class="text-base font-bold text-slate-800">No student records found</h3>
                                    <p class="text-sm text-slate-500 max-w-sm mx-auto">We couldn't find any students matching your filters or search query. Try adjusting your parameters.</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <!-- Pagination links -->
    <div class="mt-5">{{ $students->links() }}</div>
</div>

@include('admin.students.partials.index.styles')
