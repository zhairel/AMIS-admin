<x-admin-layout
    title="Approval Workflow"
    :breadcrumbs="[
        ['label' => 'Applications', 'href' => route('admin.applications.enrollment')],
        ['label' => 'Approval Workflow', 'href' => null],
    ]"
>
    @php
        $inputClass = 'h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100';
        $statusColor = ['approved' => 'green', 'rejected' => 'red', 'under_review' => 'blue', 'ready_for_submission' => 'yellow', 'pending' => 'yellow', 'submitted' => 'purple'];
        $canReviewApplications = auth()->user()?->canReviewEnrollmentApplications() ?? false;
        $readiness = function ($applicant) use ($reviewService) {
            $docsReady = $reviewService->areAllDocumentsApproved($applicant);
            return match (true) {
                $applicant->status === 'approved' => ['Ready', 'green', 'Approved enrollment'],
                $applicant->status === 'rejected' => ['Blocked', 'red', 'Rejected application'],
                $docsReady => ['Ready', 'green', 'Ready for final approval'],
                default => ['Ready', 'green', 'Approval allowed; follow up documents/payment separately'],
            };
        };
        $typeLabel = fn ($type) => match (\Illuminate\Support\Str::of((string) $type)->lower()->replace(['_', '-'], ' ')->squish()->toString()) {
            'old', 'old student', 'returning', 'returnee', 'existing' => 'OLD STUDENT',
            'transferee', 'transfer', 'transferee student' => 'TRANSFEREE STUDENT',
            'new', 'new student' => 'NEW STUDENT',
            default => 'NOT SET',
        };
        $typeClass = fn ($label) => match ($label) {
            'OLD STUDENT' => 'bg-green-100 text-green-800',
            'TRANSFEREE STUDENT' => 'bg-amber-100 text-amber-800',
            'NEW STUDENT' => 'bg-blue-100 text-blue-800',
            default => 'bg-slate-100 text-slate-600',
        };
        $inboxState = function ($applicant) {
            $status = data_get($applicant, 'onboarding_email_status');
            $sentAt = data_get($applicant, 'onboarding_email_sent_at');
            $sentLabel = $sentAt instanceof \Carbon\CarbonInterface ? $sentAt->format('M d, g:i A') : (string) $sentAt;

            return match ($status) {
                'sent' => ['Sent', 'green', $sentLabel],
                'sent_reset_pending' => ['Sent / Reset Pending', 'yellow', data_get($applicant, 'onboarding_email_error')],
                'failed' => ['Failed', 'red', data_get($applicant, 'onboarding_email_error')],
                'missing_recipient' => ['No Email', 'red', 'No valid parent or applicant email'],
                'missing_payment_proof' => ['No Payment Proof', 'yellow', 'Waiting for payment proof'],
                'disabled' => ['Disabled', 'gray', 'Auto-send disabled in settings'],
                default => $applicant->status === 'approved'
                    ? ['Not Sent', 'yellow', 'Approved but no sent email is recorded']
                    : ['Pending', 'gray', 'Available after approval'],
            };
        };
    @endphp

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-emerald-700">Applications Workspace</p>
                <h1 class="mt-1 text-xl font-bold text-slate-950">Approval Workflow</h1>
                <p class="mt-1 text-sm text-slate-500">Final review queue for application approval and account creation.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.applications.requirements') }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50 px-4 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100">
                    <i data-lucide="list-checks" class="h-4 w-4"></i>
                    Requirements
                </a>
                <a href="{{ route('admin.settings.enrollment') }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 text-sm font-bold text-amber-700 transition hover:bg-amber-100">
                    <i data-lucide="settings" class="h-4 w-4"></i>
                    Settings
                </a>
            </div>
        </div>

        <div class="px-6 py-5">
            <form method="GET" class="mb-5 grid grid-cols-12 gap-3">
                @if(request('school_year'))
                    <input type="hidden" name="school_year" value="{{ request('school_year') }}">
                @endif
                <label class="relative col-span-3">
                    <i data-lucide="search" class="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-slate-400"></i>
                    <input name="search" value="{{ request('search') }}" placeholder="Search approval queue" class="{{ $inputClass }} w-full pl-9">
                </label>
                <select name="status" class="{{ $inputClass }} col-span-2 w-full" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach (\App\Services\Admin\Enrollment\EnrollmentReviewService::FILTER_STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="readiness" class="{{ $inputClass }} col-span-2 w-full" onchange="this.form.submit()">
                    <option value="">All readiness states</option>
                    <option value="ready" @selected(request('readiness') === 'ready')>Ready to Approve</option>
                    <option value="not_ready" @selected(request('readiness') === 'not_ready')>Not Ready</option>
                </select>
                <select name="inbox_status" class="{{ $inputClass }} col-span-3 w-full" onchange="this.form.submit()">
                    <option value="">All inbox states</option>
                    <option value="missing" @selected(request('inbox_status') === 'missing')>Needs resend</option>
                    <option value="failed" @selected(request('inbox_status') === 'failed')>Failed</option>
                    <option value="sent" @selected(request('inbox_status') === 'sent')>Sent</option>
                </select>
                <button class="col-span-2 inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                    <i data-lucide="shield-check" class="h-4 w-4"></i>
                    Filter Queue
                </button>
            </form>

            <div class="grid grid-cols-3 gap-4">
                @foreach ([['submitted', 'Submitted'], ['under_review', 'Under Review'], ['approved', 'Approved']] as [$key, $label])
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs font-extrabold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                        <div class="mt-1 text-2xl font-extrabold text-slate-950">{{ $applicants->getCollection()->where('status', $key)->count() }}</div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 overflow-hidden rounded-md border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-4 font-bold">Applicant</th>
                            <th class="w-44 px-5 py-4 font-bold">Current Status</th>
                            <th class="w-48 px-5 py-4 font-bold">Readiness</th>
                            <th class="w-48 px-5 py-4 font-bold">Inbox Email</th>
                            <th class="px-5 py-4 font-bold">Next Step</th>
                            <th class="w-52 px-5 py-4 text-right font-bold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($applicants as $applicant)
                            @php
                                $name = \Illuminate\Support\Str::upper(trim($applicant->first_name.' '.$applicant->middle_name.' '.$applicant->last_name));
                                [$readyLabel, $readyColor, $nextStep] = $readiness($applicant);
                                [$inboxLabel, $inboxColor, $inboxNote] = $inboxState($applicant);
                                $studentType = $typeLabel($applicant->student_type);
                            @endphp
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-slate-950">{{ $name }}</span>
                                        @php
                                            $regTime = $applicant->created_at ? \Illuminate\Support\Carbon::parse($applicant->created_at) : null;
                                            $isNewRegistration = $regTime && ($regTime->greaterThanOrEqualTo(now()->subHours(24)) || $regTime->isYesterday() || $regTime->isToday());
                                        @endphp
                                        @if ($isNewRegistration)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-600 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-white shadow-3xs" title="Registered {{ $regTime->diffForHumans() }}">
                                                <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"></span>
                                                NEW
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                                        <span>{{ $applicant->grade_abbr }}</span>
                                        <span class="rounded-md px-2 py-0.5 text-[10px] font-extrabold {{ $typeClass($studentType) }}">{{ $studentType }}</span>
                                        <span>Applicant #{{ str_pad($applicant->id, 4, '0', STR_PAD_LEFT) }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4"><x-badge :color="$statusColor[$applicant->status] ?? 'gray'">{{ $statusLabels[$applicant->status] ?? 'Under Review' }}</x-badge></td>
                                <td class="px-5 py-4"><x-badge :color="$readyColor">{{ $readyLabel }}</x-badge></td>
                                <td class="px-5 py-4">
                                    <x-badge :color="$inboxColor">{{ $inboxLabel }}</x-badge>
                                    @if ($inboxNote)
                                        <div class="mt-1 max-w-44 truncate text-[11px] font-medium text-slate-400" title="{{ $inboxNote }}">{{ $inboxNote }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 font-medium text-slate-600">{{ $nextStep }}</td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($canReviewApplications && $applicant->status === 'approved' && data_get($applicant, 'onboarding_email_status') !== 'sent')
                                            <form method="POST" action="{{ route('admin.applicants.send-welcome', $applicant) }}" onsubmit="return confirm('Resend inbox email and reset temporary password for this student?')">
                                                @csrf
                                                <button class="inline-flex h-9 items-center gap-2 rounded-md border border-amber-100 bg-amber-50 px-3 text-xs font-bold text-amber-700 transition hover:bg-amber-100">
                                                    <i data-lucide="send" class="h-4 w-4"></i>
                                                    Resend Inbox
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('admin.applicants.review', $applicant) }}" class="inline-flex h-9 items-center gap-2 rounded-md border border-emerald-100 bg-white px-3 text-xs font-bold text-emerald-700 transition hover:bg-emerald-50">
                                            <i data-lucide="shield-check" class="h-4 w-4"></i>
                                            Open
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No applications in the approval queue.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">{{ $applicants->links() }}</div>
        </div>
    </section>
</x-admin-layout>
