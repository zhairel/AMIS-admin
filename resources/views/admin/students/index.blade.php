@php
    $inputClass = 'h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100';
    $sort = request('sort', 'latest');
    $direction = request('direction', $sort === 'name' ? 'asc' : 'desc') === 'asc' ? 'asc' : 'desc';
    $sortUrl = fn ($key) => route('admin.students.index', array_merge(request()->except('page'), [
        'sort' => $key,
        'direction' => $sort === $key && $direction === 'asc' ? 'desc' : 'asc',
    ]));
    $sortIcon = fn ($key) => $sort !== $key ? 'arrow-up-down' : ($direction === 'asc' ? 'arrow-up' : 'arrow-down');
    $gradeOrder = ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
    $isTeacherAdminViewer = auth()->user()?->isTeacherAdminViewer() ?? false;
@endphp

@if ($isPrint)
    @include('admin.students.partials.index.print')
@else
<x-admin-layout
    title="Student Records"
    :breadcrumbs="[
        ['label' => 'Students', 'href' => route('admin.students.index')],
        ['label' => 'Student Records', 'href' => null],
    ]"
>
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <!-- Section Header -->
        <div class="flex flex-col gap-4 border-b border-slate-100 px-6 py-5 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-emerald-700">Students Workspace</p>
                <h1 class="mt-1 text-xl font-bold text-slate-950">Student Records</h1>
                <p class="mt-1 text-sm text-slate-500">View enrolled student accounts, credentials, and synchronized teams channels. <span class="font-semibold text-slate-700">({{ number_format($analytics['filtered_total'] ?? $students->total()) }} of {{ number_format($stats['total_students'] ?? 0) }} total)</span></p>
            </div>
            <div class="flex flex-wrap items-center gap-2 print:hidden lg:justify-end">
                <form method="POST" action="{{ route('admin.ms-sync.sync-all-licenses') }}" onsubmit="return confirm('Sync and assign licenses for the currently filtered student list? This may take a few minutes.')" class="inline-block">
                    @csrf
                    @foreach (request()->only(['search', 'grade', 'type', 'gender', 'mode', 'ms_status']) as $key => $value)
                        @if (filled($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <button type="submit" class="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 cursor-pointer">
                        <i data-lucide="shield-check" class="h-4 w-4 text-emerald-600"></i>
                        <span>{{ request()->hasAny(['search', 'grade', 'type', 'gender', 'mode', 'ms_status']) ? 'Sync Filtered Licenses' : 'Sync Pending Licenses' }}</span>
                    </button>
                </form>
                <!-- Temporary Accounts Button -->
                <a href="{{ route('admin.students.temporary') }}" class="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-lg border border-amber-200 bg-amber-50 px-3.5 text-xs font-bold text-amber-800 shadow-sm transition hover:border-amber-300 hover:bg-amber-100" title="View temporary student records and pending requirements">
                    <i data-lucide="user-clock" class="h-4 w-4 text-amber-600"></i>
                    <span>Temporary Records</span>
                    <span class="inline-flex items-center rounded-full bg-amber-200 px-2 py-0.5 text-[10px] font-black text-amber-900">{{ number_format($stats['temp_accounts'] ?? 0) }}</span>
                </a>
                <!-- Unassigned Students Button -->
                <a href="{{ route('admin.students.unassigned') }}" class="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-lg border border-rose-200 bg-rose-50 px-3.5 text-xs font-bold text-rose-800 shadow-sm transition hover:border-rose-300 hover:bg-rose-100" title="View students without an assigned section">
                    <i data-lucide="user-x" class="h-4 w-4 text-rose-600"></i>
                    <span>Unassigned Students</span>
                </a>
                <!-- Archived Students Button -->
                <a href="{{ route('admin.students.archive') }}" class="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-lg border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-100" title="View archived / removed student records">
                    <i data-lucide="archive" class="h-4 w-4 text-slate-500"></i>
                    <span>Archived</span>
                </a>
                <!-- Download Docs ZIP Button -->
                <a href="{{ route('admin.students.download-docs-zip') }}" onclick="location.href='{{ route('admin.students.download-docs-zip') }}' + window.location.search; return false;" class="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-800" title="Download ZIP archive of 2x2 Photos, Birth Certificates, Report Cards, and Enrollment Forms for filtered students">
                    <i data-lucide="archive" class="h-4 w-4 text-sky-600"></i>
                    <span>Download Docs ZIP</span>
                </a>
                <!-- Print Records Modal Hub Button -->
                <button type="button" onclick="openPrintRecordsModal()" class="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-lg bg-emerald-700 hover:bg-emerald-800 px-4 text-xs font-bold text-white shadow-sm transition active:scale-[0.98] cursor-pointer">
                    <i data-lucide="printer" class="h-4 w-4"></i>
                    <span>Print Records</span>
                </button>
            </div>
        </div>

        <!-- Hidden Print Header -->
        <div class="hidden print:block mb-6 text-center border-b-2 border-slate-350 pb-4">
            <h1 class="uppercase tracking-tight text-slate-900 font-bold" style="font-family: Arial, sans-serif; font-size: 14px;">STUDENT RECORDS MASTERS LIST</h1>
            <h2 class="uppercase tracking-wide text-slate-700 font-bold mt-1" style="font-family: Arial, sans-serif; font-size: 11px;">
                @if(request('grade'))
                    Grade Level: {{ request('grade') }}
                @else
                    All Grades
                @endif
                @if(request('type'))
                    | Type: {{ strtoupper(request('type')) }}
                @endif
                @if(request('mode'))
                    | Mode: {{ request('mode') }}
                @endif
            </h2>
            <div class="mt-3 flex justify-center gap-6 text-slate-500 font-normal" style="font-family: Arial, sans-serif; font-size: 9px;">
                <span>Total Filtered: {{ $students->total() }}</span>
            </div>
        </div>

        <div class="px-6 py-5">
            <!-- Telemetry Summary -->
            @include('admin.students.partials.index.telemetry')

            <!-- Filter Bar Form -->
            @include('admin.students.partials.index.filters')

            <!-- Table of Enrollees -->
            @include('admin.students.partials.index.table')
        </div>
    </section>

    <!-- Bulk Print from List Modal -->
    <div id="bulk-print-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-md">
        <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-8 shadow-2xl">
            <button type="button" onclick="document.getElementById('bulk-print-modal').classList.add('hidden')" class="absolute right-4 top-4 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
            <div class="mb-6">
                <div class="mb-1 text-xs font-extrabold uppercase tracking-wider text-violet-600">Bulk Print</div>
                <h2 class="text-xl font-bold text-slate-900">Bulk Print from List</h2>
                <p class="mt-1 text-sm text-slate-500">Paste student numbers from Excel — one per line, or separated by commas. The system will auto-generate all their records.</p>
            </div>

            <form method="POST" action="{{ route('admin.students.bulk-print-list') }}" target="_blank" id="bulk-print-form">
                @csrf
                <div class="mb-4">
                    <label class="mb-1.5 block text-xs font-bold text-slate-700">Student Numbers</label>
                    <textarea
                        name="student_numbers"
                        id="bulk-student-numbers"
                        rows="8"
                        placeholder="260429&#10;260430&#10;260431&#10;...paste from Excel here"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 font-mono text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-violet-400 focus:ring-4 focus:ring-violet-100 resize-none"
                        required
                    ></textarea>
                    <p id="bulk-count-label" class="mt-1.5 text-xs text-slate-400">0 student numbers detected</p>
                </div>
                <div class="mb-6">
                    <label class="mb-1.5 block text-xs font-bold text-slate-700">Print Type</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex cursor-pointer flex-col items-center gap-1.5 rounded-xl border border-violet-200 bg-violet-50 p-3 text-center transition has-[:checked]:border-violet-500 has-[:checked]:bg-violet-100 has-[:checked]:ring-2 has-[:checked]:ring-violet-300">
                            <input type="radio" name="print_type" value="print_id" class="sr-only" checked>
                            <i data-lucide="contact" class="h-5 w-5 text-violet-600"></i>
                            <span class="text-xs font-bold text-violet-700">ID Cards</span>
                        </label>
                        <label class="flex cursor-pointer flex-col items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 p-3 text-center transition has-[:checked]:border-violet-500 has-[:checked]:bg-violet-100 has-[:checked]:ring-2 has-[:checked]:ring-violet-300">
                            <input type="radio" name="print_type" value="print_credentials" class="sr-only">
                            <i data-lucide="key" class="h-5 w-5 text-slate-500"></i>
                            <span class="text-xs font-bold text-slate-600">Credentials</span>
                        </label>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="flex-1 inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-violet-600 px-6 text-sm font-bold text-white transition hover:bg-violet-700 active:scale-[0.98]">
                        <i data-lucide="printer" class="h-4 w-4"></i>
                        Generate &amp; Print
                    </button>
                    <button type="button" onclick="document.getElementById('bulk-print-modal').classList.add('hidden')" class="h-11 rounded-xl border border-slate-200 px-5 text-sm font-bold text-slate-600 transition hover:bg-slate-50">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sync Loading Modal -->
    <div id="sync-loading-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-md transition-all duration-300">
        <div class="relative w-full max-w-md scale-95 transform rounded-2xl border border-slate-200/80 bg-white p-8 shadow-2xl transition-all duration-300 dark:border-slate-800 dark:bg-slate-900 text-center">
            <!-- Spinner -->
            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-950/30">
                <svg class="h-8 w-8 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            
            <!-- Text -->
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Syncing Microsoft Licenses</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">We are updating status and assigning licenses to all student accounts. This may take 1-3 minutes. Please do not close or refresh this page.</p>
            
            <!-- Progress bar simulation (subtle animation) -->
            <div class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                <div class="h-full rounded-full bg-emerald-600 animate-[loading-bar_2s_infinite_ease-in-out]" style="width: 30%"></div>
            </div>
        </div>
    </div>

    <style>
    @keyframes loading-bar {
        0% { transform: translateX(-100%); width: 30%; }
        50% { width: 60%; }
        100% { transform: translateX(350%); width: 30%; }
    }
    </style>

    <script>
    function copyToClipboard(text, button) {
        navigator.clipboard.writeText(text).then(() => {
            const originalHtml = button.innerHTML;
            button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check text-emerald-600"><path d="M20 6 9 17l-5-5"/></svg>';
            button.disabled = true;
            setTimeout(() => {
                button.innerHTML = originalHtml;
                button.disabled = false;
            }, 1500);
        }).catch(err => {
            console.error('Failed to copy text: ', err);
        });
    }

    // Bulk print list — live count
    const bulkTextarea = document.getElementById('bulk-student-numbers');
    const bulkCountLabel = document.getElementById('bulk-count-label');
    if (bulkTextarea) {
        bulkTextarea.addEventListener('input', function() {
            const nums = this.value.split(/[\r\n,;\t]+/).map(s => s.trim()).filter(Boolean);
            const unique = [...new Set(nums)];
            bulkCountLabel.textContent = unique.length + ' student number' + (unique.length !== 1 ? 's' : '') + ' detected';
        });
    }

    // Reinitialize lucide icons whenever the modal is opened
    document.getElementById('bulk-print-modal')?.addEventListener('transitionend', () => {
        if (window.lucide) window.lucide.createIcons();
    });

    document.querySelectorAll('form').forEach(form => {
        if (form.action.includes('ms-sync/sync-all-licenses') || form.action.includes('ms-sync/students')) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const modal = document.getElementById('sync-loading-modal');
                const modalTitle = modal.querySelector('h3');
                const modalText = modal.querySelector('p');
                
                if (form.action.includes('ms-sync/students')) {
                    modalTitle.textContent = "Syncing Student Account";
                    modalText.textContent = "Updating Microsoft account status and license for this student. Please wait...";
                } else {
                    modalTitle.textContent = "Syncing Microsoft Licenses";
                    modalText.textContent = "We are updating Microsoft status and licenses for the current student filter. Please do not close or refresh this page.";
                }
                
                modal.classList.remove('hidden');
                
                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    window.location.href = response.url || window.location.href;
                })
                .catch(err => {
                    console.error('Sync error:', err);
                    window.location.reload();
                });
            });
        }
    });
    </script>

    <!-- Microsoft Credentials & Password Quick Modal -->
    <div id="credentials-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm" onclick="if(event.target === this) closeStudentCredentialsModal()">
        <div class="w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
                <div class="flex min-w-0 items-center gap-3.5">
                    <div id="cred-modal-avatar" class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-emerald-50 text-sm font-extrabold text-emerald-700 ring-1 ring-emerald-200">
                        ST
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Microsoft account</p>
                        <h3 id="cred-modal-name" class="mt-0.5 truncate text-base font-extrabold leading-tight text-slate-950 dark:text-white">STUDENT NAME</h3>
                        <p class="mt-1 text-xs font-medium text-slate-500">
                            <span id="cred-modal-id" class="font-bold text-slate-700 dark:text-slate-300">#261125</span>
                            <span class="px-1 text-slate-300">·</span>
                            <span id="cred-modal-grade">Grade 6</span>
                            <span class="px-1 text-slate-300">·</span>
                            <span id="cred-modal-section">Section</span>
                        </p>
                    </div>
                </div>
                <button type="button" onclick="closeStudentCredentialsModal()" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-800" aria-label="Close credentials">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <div class="space-y-5 px-6 py-5">
                <section>
                    <div class="mb-2.5 flex items-center justify-between gap-3">
                        <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Sign-in credentials</h4>
                        <span id="cred-modal-status-badge" class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700">
                            <span id="cred-modal-status-dot" class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            <span id="cred-modal-status-text">Account active</span>
                        </span>
                    </div>

                    <div class="divide-y divide-slate-200 overflow-hidden rounded-xl border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
                        <div class="p-4">
                            <label for="cred-modal-email" class="mb-2 flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300">
                                <i data-lucide="mail" class="h-4 w-4 text-emerald-600"></i>
                                School email
                            </label>
                            <div class="flex h-10 items-center gap-2 rounded-lg bg-slate-50 px-3 ring-1 ring-inset ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                                <input type="text" id="cred-modal-email" readonly class="m-0 w-full border-0 bg-transparent p-0 font-mono text-sm font-semibold text-slate-900 outline-none focus:border-0 focus:outline-none focus:ring-0 dark:text-white" value="s.261125@amis.edu.ph" style="border: none !important; outline: none !important; box-shadow: none !important;">
                                <button type="button" onclick="copyCredText('cred-modal-email', 'Email')" class="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-md px-2 text-xs font-bold text-slate-600 transition hover:bg-white hover:text-emerald-700 hover:shadow-sm dark:hover:bg-slate-700">
                                    <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                    Copy
                                </button>
                            </div>
                        </div>

                        <div class="p-4">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label for="cred-modal-password" class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300">
                                    <i data-lucide="key-round" class="h-4 w-4 text-amber-600"></i>
                                    Password
                                </label>
                                <span id="cred-modal-pass-state" class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700">
                                    <span id="cred-modal-pass-dot" class="h-2 w-2 rounded-full bg-amber-500"></span>
                                    <span id="cred-modal-pass-text">Temporary password</span>
                                </span>
                            </div>
                            <div class="flex h-10 items-center gap-2 rounded-lg bg-slate-50 px-3 ring-1 ring-inset ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                                <div class="relative w-full">
                                    <input type="password" id="cred-modal-password" readonly class="m-0 w-full border-0 bg-transparent p-0 font-mono text-sm font-semibold text-slate-900 outline-none focus:border-0 focus:outline-none focus:ring-0 dark:text-white" value="Amis@98213" style="border: none !important; outline: none !important; box-shadow: none !important;">
                                    <input type="text" id="cred-modal-password-text" readonly class="m-0 hidden w-full border-0 bg-transparent p-0 font-mono text-sm font-semibold text-slate-900 outline-none focus:border-0 focus:outline-none focus:ring-0 dark:text-white" value="Amis@98213" style="border: none !important; outline: none !important; box-shadow: none !important;">
                                </div>
                                <button type="button" onclick="togglePasswordVisibility()" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-white hover:text-slate-700 hover:shadow-sm dark:hover:bg-slate-700" title="Show or hide password">
                                    <i id="cred-modal-eye-icon" data-lucide="eye" class="h-4 w-4"></i>
                                </button>
                                <button type="button" onclick="copyCredText('cred-modal-password-text', 'Password')" class="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-md px-2 text-xs font-bold text-slate-600 transition hover:bg-white hover:text-amber-700 hover:shadow-sm dark:hover:bg-slate-700">
                                    <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                    Copy
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <h4 class="mb-2.5 text-xs font-extrabold uppercase tracking-wider text-slate-500">Account tools</h4>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <form id="cred-modal-reset-form" method="POST" action="" onsubmit="return confirm('Reset Microsoft Office 365 password to default temporary password (Amis@12345)?')">
                            @csrf
                            <input type="hidden" name="reset_format" value="default">
                            <button type="submit" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:border-amber-300 hover:bg-amber-50 hover:text-amber-800">
                                <i data-lucide="rotate-ccw" class="h-4 w-4 text-amber-600"></i>
                                Reset password
                            </button>
                        </form>

                        <form id="cred-modal-resend-form" method="POST" action="">
                            @csrf
                            <button type="submit" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-800">
                                <i data-lucide="send" class="h-4 w-4 text-indigo-600"></i>
                                Resend email
                            </button>
                        </form>

                        <form id="cred-modal-sync-form" method="POST" action="">
                            @csrf
                            <button type="submit" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800">
                                <i data-lucide="refresh-cw" class="h-4 w-4 text-emerald-600"></i>
                                Sync account
                            </button>
                        </form>
                    </div>
                </section>

                <div id="cred-copy-toast" class="hidden rounded-lg bg-emerald-800 px-3 py-2.5 text-center text-xs font-bold text-white shadow-sm">
                    <span id="cred-copy-toast-msg">Copied to clipboard!</span>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-800/50">
                <button type="button" onclick="copyCombinedCredentials()" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-800">
                    <i data-lucide="copy-check" class="h-4 w-4"></i>
                    Copy credentials
                </button>
                <div class="flex items-center gap-2">
                    <a id="cred-modal-zip-btn" href="#" download class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 transition hover:border-sky-300 hover:text-sky-800 sm:flex-none" title="Download student documents">
                        <i data-lucide="archive" class="h-4 w-4 text-sky-600"></i>
                        Documents
                    </a>
                    <a id="cred-modal-print-btn" href="#" target="_blank" class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 transition hover:border-slate-300 hover:text-slate-950 sm:flex-none" title="Print credential slip">
                        <i data-lucide="printer" class="h-4 w-4 text-slate-500"></i>
                        Print
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentModalStudent = null;

        function openStudentCredentialsModal(student) {
            currentModalStudent = student;
            document.getElementById('cred-modal-name').innerText = student.name || 'STUDENT PROFILE';
            document.getElementById('cred-modal-id').innerText = '#' + (student.student_number || '-');
            document.getElementById('cred-modal-grade').innerText = student.grade || '-';
            document.getElementById('cred-modal-section').innerText = student.section || 'Unassigned';

            const zipBtn = document.getElementById('cred-modal-zip-btn');
            if (zipBtn && student.student_number) {
                zipBtn.href = '{{ route('admin.students.download-docs-zip') }}?search=' + encodeURIComponent(student.student_number);
            }
            
            document.getElementById('cred-modal-email').value = student.email || 'No Email Set';
            
            const passVal = student.temp_password || 'N/A';
            document.getElementById('cred-modal-password').value = passVal;
            document.getElementById('cred-modal-password-text').value = passVal;
            
            const passState = document.getElementById('cred-modal-pass-state');
            const passStateText = document.getElementById('cred-modal-pass-text');
            const passStateDot = document.getElementById('cred-modal-pass-dot');
            if (student.password_changed) {
                passStateText.innerText = 'Password changed';
                passState.className = 'inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700';
                passStateDot.className = 'h-2 w-2 rounded-full bg-emerald-500';
            } else {
                passStateText.innerText = 'Temporary password';
                passState.className = 'inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700';
                passStateDot.className = 'h-2 w-2 rounded-full bg-amber-500';
            }

            const statusBadge = document.getElementById('cred-modal-status-badge');
            const statusText = document.getElementById('cred-modal-status-text');
            const statusDot = document.getElementById('cred-modal-status-dot');
            if (student.ms_user_id) {
                statusText.innerText = 'Account active';
                statusBadge.className = 'inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700';
                statusDot.className = 'h-2 w-2 rounded-full bg-emerald-500';
            } else {
                statusText.innerText = 'Setup pending';
                statusBadge.className = 'inline-flex items-center gap-1.5 text-xs font-bold text-slate-600';
                statusDot.className = 'h-2 w-2 rounded-full bg-slate-400';
            }

            const avatarEl = document.getElementById('cred-modal-avatar');
            if (student.photo_url) {
                avatarEl.innerHTML = `<img src="${student.photo_url}" class="h-full w-full object-cover">`;
            } else {
                const initials = (student.name || 'ST').split(' ').map(n => n[0]).join('').substring(0,2).toUpperCase();
                avatarEl.innerText = initials;
            }

            const printBtn = document.getElementById('cred-modal-print-btn');
            if (printBtn && student.print_url) {
                printBtn.href = student.print_url;
            }

            const resendForm = document.getElementById('cred-modal-resend-form');
            if (resendForm && student.resend_url) {
                resendForm.action = student.resend_url;
            }

            const resetForm = document.getElementById('cred-modal-reset-form');
            if (resetForm && student.resend_url) {
                resetForm.action = student.resend_url;
            }

            const syncForm = document.getElementById('cred-modal-sync-form');
            if (syncForm && student.sync_url) {
                syncForm.action = student.sync_url;
            }

            const modal = document.getElementById('credentials-modal');
            modal.classList.remove('hidden');
            if (window.lucide) window.lucide.createIcons();
        }

        function closeStudentCredentialsModal() {
            document.getElementById('credentials-modal').classList.add('hidden');
        }

        function togglePasswordVisibility() {
            const passInput = document.getElementById('cred-modal-password');
            const textInput = document.getElementById('cred-modal-password-text');
            const eyeIcon = document.getElementById('cred-modal-eye-icon');
            
            if (passInput.classList.contains('hidden')) {
                passInput.classList.remove('hidden');
                textInput.classList.add('hidden');
                if (eyeIcon) eyeIcon.setAttribute('data-lucide', 'eye');
            } else {
                passInput.classList.add('hidden');
                textInput.classList.remove('hidden');
                if (eyeIcon) eyeIcon.setAttribute('data-lucide', 'eye-off');
            }
            if (window.lucide) window.lucide.createIcons();
        }

        function copyCredText(elementId, label) {
            const el = document.getElementById(elementId);
            if (!el) return;
            navigator.clipboard.writeText(el.value).then(() => {
                showCredToast(`${label} copied to clipboard!`);
            });
        }

        function copyCombinedCredentials() {
            if (!currentModalStudent) return;
            const email = document.getElementById('cred-modal-email').value;
            const pass = document.getElementById('cred-modal-password-text').value;
            const text = `Student: ${currentModalStudent.name}\nAMIS ID: #${currentModalStudent.student_number}\nEmail: ${email}\nPassword: ${pass}`;
            navigator.clipboard.writeText(text).then(() => {
                showCredToast('Student Email & Password copied to clipboard!');
            });
        }

        function showCredToast(msg) {
            const toast = document.getElementById('cred-copy-toast');
            const msgEl = document.getElementById('cred-copy-toast-msg');
            if (!toast || !msgEl) return;
            msgEl.innerText = msg;
            toast.classList.remove('hidden');
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 2500);
        }

        function openPrintRecordsModal() {
            const modal = document.getElementById('print-records-modal');
            if (modal) {
                modal.classList.remove('hidden');
                if (window.lucide) window.lucide.createIcons();
            }
        }

        function closePrintRecordsModal() {
            const modal = document.getElementById('print-records-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function runPrintRecordAction(actionType) {
            const mode = document.getElementById('p-filter-mode')?.value || '';
            const grade = document.getElementById('p-filter-grade')?.value || '';
            const gender = document.getElementById('p-filter-gender')?.value || '';
            const search = '{{ request('search', '') }}';

            const params = new URLSearchParams();
            if (mode) params.append('mode', mode);
            if (grade) params.append('grade', grade);
            if (gender) params.append('gender', gender);
            if (search) params.append('search', search);

            const queryString = params.toString() ? '?' + params.toString() : '';

            if (actionType === 'forms_batch') {
                window.open('{{ route('admin.students.print-enrolment-forms-batch') }}' + queryString, '_blank');
            } else if (actionType === 'forms_jpg') {
                window.open('{{ route('admin.students.print-enrolment-forms-batch') }}' + (queryString ? queryString + '&auto=jpg' : '?auto=jpg'), '_blank');
            } else if (actionType === 'id_cards') {
                const idParams = new URLSearchParams(params);
                idParams.append('print_id', '1');
                idParams.append('is_print', '1');
                window.open('{{ route('admin.students.index') }}?' + idParams.toString(), '_blank');
            } else if (actionType === 'docs_zip') {
                window.location.href = '{{ route('admin.students.download-docs-zip') }}' + queryString;
            } else if (actionType === 'credentials') {
                const credParams = new URLSearchParams(params);
                credParams.append('print_credentials', '1');
                credParams.append('is_print', '1');
                window.open('{{ route('admin.students.index') }}?' + credParams.toString(), '_blank');
            } else if (actionType === 'masters_list') {
                const listParams = new URLSearchParams(params);
                listParams.append('print', '1');
                listParams.append('is_print', '1');
                window.open('{{ route('admin.students.index') }}?' + listParams.toString(), '_blank');
            } else if (actionType === 'canva') {
                window.location.href = '{{ route('admin.students.export-canva') }}' + queryString;
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeStudentCredentialsModal();
                closePrintRecordsModal();
                closeStudentIdCardModal();
            }
        });
    </script>

    @include('admin.students.partials.index.id_card_modal')
    @include('admin.students.partials.index.print_records_modal')
</x-admin-layout>
@endif
