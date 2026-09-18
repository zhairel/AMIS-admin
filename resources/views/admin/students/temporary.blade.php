@php
    $inputClass = 'h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-amber-400 focus:ring-4 focus:ring-amber-100';
@endphp

<x-admin-layout
    title="Temporary Student Records"
    :breadcrumbs="[
        ['label' => 'Students', 'href' => route('admin.students.index')],
        ['label' => 'Temporary Records', 'href' => null],
    ]"
>
    <div x-data="{
        selectedStudents: [],
        selectAll: false,
        bulkAction: 'tag',
        announcementModalOpen: false,
        copiedToast: false,
        toggleSelectAll() {
            if (this.selectAll) {
                this.selectedStudents = Array.from(document.querySelectorAll('.student-checkbox')).map(cb => cb.value);
            } else {
                this.selectedStudents = [];
            }
        },
        copyAnnouncement() {
            const text = document.getElementById('announcement-text-area').value;
            navigator.clipboard.writeText(text).then(() => {
                this.copiedToast = true;
                setTimeout(() => this.copiedToast = false, 3000);
            });
        }
    }" class="space-y-6">

        <!-- Top Header & Actions -->
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 border border-amber-200">
                            <i data-lucide="clock" class="h-3.5 w-3.5 text-amber-600"></i>
                            Temporary Student Records Workspace
                        </span>
                        <span class="text-xs font-semibold text-slate-400">•</span>
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">S.Y. 2026–2027</span>
                    </div>
                    <h1 class="mt-2 text-2xl font-black text-slate-950 flex items-center gap-3">
                        <span>Temporary Accounts & Pending Requirements</span>
                        <span class="inline-flex items-center justify-center rounded-full bg-amber-100 px-3 py-0.5 text-sm font-black text-amber-800">
                            {{ number_format($students->total()) }}
                        </span>
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Dedicated workspace for managing learners enrolled on temporary status or with pending enrollment documents.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Copy for Announcement Button -->
                    <button type="button" @click="announcementModalOpen = true" class="inline-flex h-10 items-center gap-2 rounded-lg bg-emerald-700 px-4 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-800 active:scale-[0.98] cursor-pointer">
                        <i data-lucide="copy" class="h-4 w-4"></i>
                        <span>Copy Announcement Text</span>
                    </button>

                    <!-- Export CSV Button -->
                    <a href="{{ route('admin.students.temporary.export-csv', request()->query()) }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 shadow-sm transition hover:border-amber-300 hover:bg-amber-50 hover:text-amber-800">
                        <i data-lucide="download" class="h-4 w-4 text-amber-600"></i>
                        <span>Export CSV</span>
                    </a>

                    <!-- Back to Main Student Records -->
                    <a href="{{ route('admin.students.index') }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">
                        <i data-lucide="arrow-left" class="h-4 w-4 text-slate-400"></i>
                        <span>Standard Student Records</span>
                    </a>
                </div>
            </div>

            <!-- Telemetry Stat Cards -->
            <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-4">
                <!-- Tagged [TEMP ACC] Card -->
                <a href="{{ route('admin.students.temporary', array_merge(request()->except(['filter_type', 'page']), ['filter_type' => 'tagged'])) }}" 
                   class="rounded-xl border p-4 transition hover:shadow-md {{ $filterType === 'tagged' ? 'border-amber-400 bg-amber-50/70 ring-2 ring-amber-200' : 'border-amber-100 bg-amber-50/40' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-700">Tagged [TEMP ACC]</span>
                        <div class="rounded-full bg-amber-100 p-2 text-amber-700">
                            <i data-lucide="user-clock" class="h-4 w-4"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-amber-900">{{ number_format($taggedCount) }}</div>
                    <p class="mt-1 text-xs text-amber-700 font-medium">Explicitly tagged temporary accounts</p>
                </a>

                <!-- Missing All Requirements Card -->
                <a href="{{ route('admin.students.temporary', array_merge(request()->except(['filter_type', 'page']), ['filter_type' => 'missing_all'])) }}"
                   class="rounded-xl border p-4 transition hover:shadow-md {{ $filterType === 'missing_all' ? 'border-rose-400 bg-rose-50/70 ring-2 ring-rose-200' : 'border-rose-100 bg-rose-50/40' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-rose-700">Missing All Docs (0%)</span>
                        <div class="rounded-full bg-rose-100 p-2 text-rose-700">
                            <i data-lucide="file-x" class="h-4 w-4"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-rose-900">{{ number_format($missingAllCount) }}</div>
                    <p class="mt-1 text-xs text-rose-700 font-medium">0 of 4 mandatory documents submitted</p>
                </a>

                <!-- Missing Payment Card -->
                <a href="{{ route('admin.students.temporary', array_merge(request()->except(['filter_type', 'page']), ['filter_type' => 'missing_payment'])) }}"
                   class="rounded-xl border p-4 transition hover:shadow-md {{ $filterType === 'missing_payment' ? 'border-indigo-400 bg-indigo-50/70 ring-2 ring-indigo-200' : 'border-indigo-100 bg-indigo-50/40' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-700">Missing Payment Proof</span>
                        <div class="rounded-full bg-indigo-100 p-2 text-indigo-700">
                            <i data-lucide="receipt" class="h-4 w-4"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-indigo-900">{{ number_format($missingPaymentCount) }}</div>
                    <p class="mt-1 text-xs text-indigo-700 font-medium">No enrollment fee receipt submitted</p>
                </a>

                <!-- Incomplete Requirements Card -->
                <a href="{{ route('admin.students.temporary', array_merge(request()->except(['filter_type', 'page']), ['filter_type' => 'incomplete_docs'])) }}"
                   class="rounded-xl border p-4 transition hover:shadow-md {{ $filterType === 'incomplete_docs' ? 'border-slate-400 bg-slate-100 ring-2 ring-slate-300' : 'border-slate-200 bg-slate-50' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Incomplete Requirements</span>
                        <div class="rounded-full bg-slate-200 p-2 text-slate-700">
                            <i data-lucide="alert-triangle" class="h-4 w-4"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-slate-900">{{ number_format($incompleteCount) }}</div>
                    <p class="mt-1 text-xs text-slate-600 font-medium">Has at least 1 missing document</p>
                </a>
            </div>
        </section>

        <!-- Category Tabs -->
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-3">
            <a href="{{ route('admin.students.temporary', array_merge(request()->except(['filter_type', 'page']), ['filter_type' => 'tagged'])) }}"
               class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-bold transition {{ $filterType === 'tagged' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                <i data-lucide="user-clock" class="h-4 w-4"></i>
                <span>Tagged Temporary Accounts [TEMP ACC] ({{ number_format($taggedCount) }})</span>
            </a>

            <a href="{{ route('admin.students.temporary', array_merge(request()->except(['filter_type', 'page']), ['filter_type' => 'missing_all'])) }}"
               class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-bold transition {{ $filterType === 'missing_all' ? 'bg-rose-600 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                <i data-lucide="file-x" class="h-4 w-4"></i>
                <span>Missing All Docs ({{ number_format($missingAllCount) }})</span>
            </a>

            <a href="{{ route('admin.students.temporary', array_merge(request()->except(['filter_type', 'page']), ['filter_type' => 'missing_payment'])) }}"
               class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-bold transition {{ $filterType === 'missing_payment' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                <i data-lucide="receipt" class="h-4 w-4"></i>
                <span>Missing Payment Proof ({{ number_format($missingPaymentCount) }})</span>
            </a>

            <a href="{{ route('admin.students.temporary', array_merge(request()->except(['filter_type', 'page']), ['filter_type' => 'incomplete_docs'])) }}"
               class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-bold transition {{ $filterType === 'incomplete_docs' ? 'bg-slate-800 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                <i data-lucide="alert-triangle" class="h-4 w-4"></i>
                <span>Incomplete Requirements ({{ number_format($incompleteCount) }})</span>
            </a>

            <a href="{{ route('admin.students.temporary', array_merge(request()->except(['filter_type', 'page']), ['filter_type' => 'all_temporary_pool'])) }}"
               class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-bold transition {{ $filterType === 'all_temporary_pool' ? 'bg-slate-800 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                <i data-lucide="list-filter" class="h-4 w-4"></i>
                <span>All Candidates</span>
            </a>
        </div>

        <!-- Filter Form -->
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.students.temporary') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3">
                <input type="hidden" name="filter_type" value="{{ $filterType }}">

                <!-- Search Input -->
                <div class="col-span-1 sm:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Search</label>
                    <div class="relative">
                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"></i>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Search by student #, name, LRN, email, parent..." class="{{ $inputClass }} pl-9 w-full">
                    </div>
                </div>

                <!-- Grade Filter -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Grade Level</label>
                    <select name="grade" class="{{ $inputClass }} w-full">
                        <option value="">All Grades</option>
                        @foreach ($gradeOrder as $g)
                            <option value="{{ $g }}" {{ $gradeFilter === $g ? 'selected' : '' }}>{{ $g }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Learning Mode Filter -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Learning Mode</label>
                    <select name="mode" class="{{ $inputClass }} w-full">
                        <option value="">All Modes</option>
                        <option value="odl" {{ $modeFilter === 'odl' ? 'selected' : '' }}>Online / ODL</option>
                        <option value="f2f" {{ $modeFilter === 'f2f' ? 'selected' : '' }}>Face-to-Face (F2F)</option>
                    </select>
                </div>

                <!-- Filter Actions -->
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-lg bg-amber-600 px-4 text-xs font-bold text-white shadow-sm transition hover:bg-amber-700 cursor-pointer">
                        <i data-lucide="filter" class="h-4 w-4"></i>
                        <span>Filter</span>
                    </button>
                    @if ($search || $gradeFilter || $modeFilter || $sectionFilter)
                        <a href="{{ route('admin.students.temporary', ['filter_type' => $filterType]) }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-100" title="Clear all filters">
                            <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                        </a>
                    @endif
                </div>
            </form>
        </section>

        <!-- Bulk Action Bar -->
        <div x-show="selectedStudents.length > 0" x-transition class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-xs font-bold text-amber-900">
                <i data-lucide="check-square" class="h-4 w-4 text-amber-600"></i>
                <span><strong x-text="selectedStudents.length"></strong> student(s) selected</span>
            </div>

            <form method="POST" action="{{ route('admin.students.temporary.bulk-toggle-tag') }}" class="flex items-center gap-2">
                @csrf
                <template x-for="id in selectedStudents" :key="id">
                    <input type="hidden" name="student_ids[]" :value="id">
                </template>

                <select name="action" class="h-9 rounded-lg border border-amber-300 bg-white px-3 text-xs font-bold text-slate-700 outline-none">
                    <option value="tag">Add [TEMP ACC] Tag</option>
                    <option value="untag">Remove [TEMP ACC] Tag</option>
                </select>

                <button type="submit" onclick="return confirm('Apply bulk tag action to selected students?')" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-amber-700 px-3.5 text-xs font-bold text-white shadow-sm transition hover:bg-amber-800 cursor-pointer">
                    <span>Apply Bulk Action</span>
                </button>
            </form>
        </div>

        <!-- Students Table -->
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3.5 px-4 w-10">
                                <input type="checkbox" x-model="selectAll" @change="toggleSelectAll()" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                            </th>
                            <th class="py-3.5 px-4">Student</th>
                            <th class="py-3.5 px-4">Grade & Section</th>
                            <th class="py-3.5 px-4">Mode</th>
                            <th class="py-3.5 px-4">Requirements Checklist</th>
                            <th class="py-3.5 px-4">Parent Contact</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($students as $student)
                            @php
                                $applicant = $student->applicant;
                                $hasPhoto = ! empty($applicant?->photo_2x2_url);
                                $hasBirthCert = ! empty($applicant?->birth_cert_url);
                                $hasReportCard = ! empty($applicant?->report_card_url);
                                $hasReceipt = ! empty($applicant?->enrollment_fee_receipt_url);
                                $docsSubmitted = ($hasPhoto ? 1 : 0) + ($hasBirthCert ? 1 : 0) + ($hasReportCard ? 1 : 0) + ($hasReceipt ? 1 : 0);
                                $isTagged = ($applicant && (str_contains($applicant->last_name, '[TEMP') || str_contains($applicant->first_name, '[TEMP')));
                                $fullName = $applicant ? trim($applicant->last_name . ', ' . $applicant->first_name . ' ' . ($applicant->middle_name ? $applicant->middle_name[0] . '.' : '')) : 'Student #' . $student->student_number;
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition">
                                <!-- Checkbox -->
                                <td class="py-4 px-4">
                                    <input type="checkbox" value="{{ $student->id }}" x-model="selectedStudents" class="student-checkbox rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                </td>

                                <!-- Student Details -->
                                <td class="py-4 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-full bg-amber-100 text-amber-800 font-black flex items-center justify-center text-xs shrink-0">
                                            {{ strtoupper(substr($applicant?->first_name ?? 'S', 0, 1) . substr($applicant?->last_name ?? 'N', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-900 text-sm">{{ $fullName }}</span>
                                                @if ($isTagged)
                                                    <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-900 border border-amber-300">
                                                        TEMP ACC
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                                                <span class="font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">#{{ $student->student_number }}</span>
                                                @if ($applicant?->lrn)
                                                    <span>LRN: {{ $applicant->lrn }}</span>
                                                @endif
                                                <span>{{ $student->school_email }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Grade & Section -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900">{{ $student->grade_level ?: 'Unassigned' }}</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        @if ($student->section)
                                            <span class="inline-flex items-center gap-1 font-medium text-slate-700">
                                                <i data-lucide="layers" class="h-3 w-3 text-slate-400"></i>
                                                <span>{{ $student->section }}</span>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-200">
                                                Unassigned Section
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <!-- Learning Mode -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    @php
                                        $mode = strtolower($applicant?->learning_mode ?? '');
                                        $isF2f = str_contains($mode, 'face') || str_contains($mode, 'f2f');
                                    @endphp
                                    <span class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-bold border {{ $isF2f ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-purple-50 text-purple-700 border-purple-200' }}">
                                        <i data-lucide="{{ $isF2f ? 'users' : 'laptop' }}" class="h-3 w-3"></i>
                                        <span>{{ $applicant?->learning_mode ?: 'ODL' }}</span>
                                    </span>
                                </td>

                                <!-- Requirements Checklist -->
                                <td class="py-4 px-4">
                                    <div class="space-y-1.5 min-w-[220px]">
                                        <div class="flex items-center justify-between text-[11px]">
                                            <span class="font-bold text-slate-700">Completion: {{ $docsSubmitted }}/4 ({{ round(($docsSubmitted/4)*100) }}%)</span>
                                            <span class="text-[10px] font-semibold {{ $docsSubmitted === 4 ? 'text-emerald-600' : ($docsSubmitted === 0 ? 'text-rose-600' : 'text-amber-600') }}">
                                                {{ $docsSubmitted === 4 ? 'Complete' : ($docsSubmitted === 0 ? '0% Submitted' : 'Pending') }}
                                            </span>
                                        </div>

                                        <!-- Progress Bar -->
                                        <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-300 {{ $docsSubmitted === 4 ? 'bg-emerald-500' : ($docsSubmitted === 0 ? 'bg-rose-500' : 'bg-amber-500') }}" style="width: {{ ($docsSubmitted / 4) * 100 }}%"></div>
                                        </div>

                                        <!-- Document Badges -->
                                        <div class="flex flex-wrap gap-1 pt-0.5">
                                            <!-- Photo 2x2 -->
                                            @if ($hasPhoto)
                                                <a href="{{ $applicant->photo_2x2_url }}" target="_blank" class="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200 hover:bg-emerald-100" title="Click to view 2x2 Photo">
                                                    <i data-lucide="check" class="h-3 w-3"></i>
                                                    <span>Photo</span>
                                                </a>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-200" title="Missing 2x2 ID Photo">
                                                    <i data-lucide="x" class="h-3 w-3"></i>
                                                    <span>Photo</span>
                                                </span>
                                            @endif

                                            <!-- PSA Birth Cert -->
                                            @if ($hasBirthCert)
                                                <a href="{{ $applicant->birth_cert_url }}" target="_blank" class="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200 hover:bg-emerald-100" title="Click to view PSA Birth Certificate">
                                                    <i data-lucide="check" class="h-3 w-3"></i>
                                                    <span>PSA</span>
                                                </a>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-200" title="Missing PSA Birth Certificate">
                                                    <i data-lucide="x" class="h-3 w-3"></i>
                                                    <span>PSA</span>
                                                </span>
                                            @endif

                                            <!-- SF9 Report Card -->
                                            @if ($hasReportCard)
                                                <a href="{{ $applicant->report_card_url }}" target="_blank" class="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200 hover:bg-emerald-100" title="Click to view Report Card / SF9">
                                                    <i data-lucide="check" class="h-3 w-3"></i>
                                                    <span>SF9</span>
                                                </a>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-200" title="Missing Report Card / SF9">
                                                    <i data-lucide="x" class="h-3 w-3"></i>
                                                    <span>SF9</span>
                                                </span>
                                            @endif

                                            <!-- Payment Receipt -->
                                            @if ($hasReceipt)
                                                <a href="{{ $applicant->enrollment_fee_receipt_url }}" target="_blank" class="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200 hover:bg-emerald-100" title="Click to view Enrollment Fee Receipt">
                                                    <i data-lucide="check" class="h-3 w-3"></i>
                                                    <span>Receipt</span>
                                                </a>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-200" title="Missing Enrollment Fee Receipt">
                                                    <i data-lucide="x" class="h-3 w-3"></i>
                                                    <span>Receipt</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <!-- Parent Contact -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    @php
                                        $parentName = trim(($applicant?->father_first_name ? $applicant->father_first_name . ' ' . $applicant->father_last_name : '') ?: ($applicant?->mother_first_name ? $applicant->mother_first_name . ' ' . $applicant->mother_last_name : 'Parent/Guardian'));
                                    @endphp
                                    <div class="font-bold text-slate-800">{{ $parentName }}</div>
                                    <div class="mt-0.5 space-y-0.5 text-[11px]">
                                        @if ($applicant?->parent_email)
                                            <div>
                                                <a href="mailto:{{ $applicant->parent_email }}" class="inline-flex items-center gap-1 text-slate-500 hover:text-amber-700">
                                                    <i data-lucide="mail" class="h-3 w-3"></i>
                                                    <span>{{ $applicant->parent_email }}</span>
                                                </a>
                                            </div>
                                        @endif
                                        @if ($applicant?->parent_mobile)
                                            <div>
                                                <a href="tel:{{ $applicant->parent_mobile }}" class="inline-flex items-center gap-1 text-slate-500 hover:text-emerald-700">
                                                    <i data-lucide="phone" class="h-3 w-3"></i>
                                                    <span>{{ $applicant->parent_mobile }}</span>
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="py-4 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Toggle Tag Action -->
                                        <form method="POST" action="{{ route('admin.students.temporary.toggle-tag', $student) }}" class="inline-block" onsubmit="return confirm('{{ $isTagged ? 'Remove temporary tag and return to standard student records?' : 'Tag as temporary account [TEMP ACC]?' }}')">
                                            @csrf
                                            <button type="submit" class="inline-flex h-8 items-center gap-1 rounded-lg border px-2.5 text-xs font-bold transition {{ $isTagged ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}" title="{{ $isTagged ? 'Remove [TEMP ACC] tag' : 'Add [TEMP ACC] tag' }}">
                                                <i data-lucide="{{ $isTagged ? 'check-check' : 'tag' }}" class="h-3.5 w-3.5"></i>
                                                <span>{{ $isTagged ? 'Untag' : 'Tag' }}</span>
                                            </button>
                                        </form>

                                        <!-- View Profile -->
                                        <a href="{{ route('admin.students.show', $student) }}" class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50" title="View Full Student Profile">
                                            <i data-lucide="eye" class="h-3.5 w-3.5 text-slate-500"></i>
                                            <span>View</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    <div class="mx-auto w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                                        <i data-lucide="user-check" class="h-6 w-6"></i>
                                    </div>
                                    <h3 class="text-sm font-bold text-slate-800">No temporary student records found</h3>
                                    <p class="text-xs text-slate-500 mt-1">There are currently no students matching the selected filter criteria.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($students->hasPages())
                <div class="border-t border-slate-200 bg-white px-4 py-3">
                    {{ $students->links() }}
                </div>
            @endif
        </section>

        <!-- Announcement / GC Modal -->
        <div x-show="announcementModalOpen" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
            <div @click.away="announcementModalOpen = false" class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-2">
                        <div class="rounded-lg bg-emerald-100 p-2 text-emerald-800">
                            <i data-lucide="message-square" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Announcement & Follow-up Text</h3>
                            <p class="text-xs text-slate-500">Formatted text ready to copy and paste into class group chats or parent messages.</p>
                        </div>
                    </div>
                    <button type="button" @click="announcementModalOpen = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <div class="mt-4">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Generated Follow-up Text</label>
                    <textarea id="announcement-text-area" readonly rows="12" class="w-full font-mono text-xs rounded-xl border border-slate-200 bg-slate-50 p-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-400 leading-relaxed">{{ $announcementText }}</textarea>
                </div>

                <div class="mt-5 flex items-center justify-between">
                    <span x-show="copiedToast" x-transition class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700">
                        <i data-lucide="check-circle" class="h-4 w-4"></i>
                        <span>Copied to clipboard!</span>
                    </span>
                    <span x-show="!copiedToast"></span>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="announcementModalOpen = false" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Close
                        </button>
                        <button type="button" @click="copyAnnouncement()" class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-800">
                            <i data-lucide="copy" class="h-4 w-4"></i>
                            <span>Copy to Clipboard</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-admin-layout>
