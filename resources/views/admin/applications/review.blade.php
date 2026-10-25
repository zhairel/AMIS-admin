<x-admin-layout
    title="Applicant Review"
    :breadcrumbs="[
        ['label' => 'Applications', 'href' => route('admin.applications.enrollment')],
        ['label' => 'Applicant Review', 'href' => null],
    ]"
>
    @php
        $inputClass = 'h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100';
        $statusColor = ['approved' => 'green', 'rejected' => 'red', 'under_review' => 'blue', 'ready_for_submission' => 'yellow', 'pending' => 'yellow', 'submitted' => 'purple'];
        $paymentLabel = fn ($applicant) => match ($applicant->payment->status ?? null) {
            'verified' => 'Paid',
            'pending' => 'Pending',
            'rejected' => 'Rejected',
            default => 'No Payment',
        };
        $paymentColor = fn ($label) => ['Paid' => 'green', 'Pending' => 'yellow', 'Rejected' => 'red', 'No Payment' => 'gray'][$label] ?? 'gray';
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
    @endphp

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-emerald-700">Applications Workspace</p>
                <h1 class="mt-1 text-xl font-bold text-slate-950">Applicant Review</h1>
                <p class="mt-1 text-sm text-slate-500">Review submitted student applications and open the full child profile.</p>
            </div>
            <a href="{{ route('admin.applications.enrollment') }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50 px-4 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100">
                <i data-lucide="table-2" class="h-4 w-4"></i>
                Enrollment Registry
            </a>
        </div>

        <div class="px-6 py-5">
            <form method="GET" class="mb-5 grid grid-cols-12 gap-3">
                @if(request('school_year'))
                    <input type="hidden" name="school_year" value="{{ request('school_year') }}">
                @endif
                <label class="relative col-span-4">
                    <i data-lucide="search" class="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-slate-400"></i>
                    <input name="search" value="{{ request('search') }}" placeholder="Search student or email" class="{{ $inputClass }} w-full pl-9">
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
                <select name="grade" class="{{ $inputClass }} col-span-2 w-full" onchange="this.form.submit()">
                    <option value="">All grades</option>
                    @foreach ($gradeLevels as $grade)
                        <option value="{{ $grade }}" @selected(request('grade') === $grade)>{{ $grade }}</option>
                    @endforeach
                </select>
                <button class="col-span-2 inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                    <i data-lucide="filter" class="h-4 w-4"></i>
                    Filter
                </button>
            </form>

            <div class="overflow-hidden rounded-md border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-4 font-bold">Applicant</th>
                            <th class="w-36 px-5 py-4 font-bold">Student Type</th>
                            <th class="w-32 px-5 py-4 font-bold">Grade</th>
                            <th class="w-44 px-5 py-4 font-bold">Status</th>
                            <th class="w-40 px-5 py-4 font-bold">Payment</th>
                            <th class="w-36 px-5 py-4 text-right font-bold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($applicants as $applicant)
                            @php
                                $name = \Illuminate\Support\Str::upper(trim($applicant->first_name.' '.$applicant->middle_name.' '.$applicant->last_name));
                                $initials = collect(explode(' ', $name))->filter()->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->join('');
                                $pay = $paymentLabel($applicant);
                                $studentType = $typeLabel($applicant->student_type);
                            @endphp
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-emerald-50 text-xs font-extrabold text-emerald-700 ring-1 ring-emerald-100">{{ $initials ?: 'ST' }}</span>
                                        <div>
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
                                            <div class="mt-0.5 text-xs font-medium text-slate-500">{{ $applicant->user->email ?? $applicant->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="rounded-md px-2.5 py-1 text-xs font-extrabold {{ $typeClass($studentType) }}">{{ $studentType }}</span>
                                </td>
                                <td class="px-5 py-4 font-bold text-slate-700">{{ $applicant->grade_abbr }}</td>
                                <td class="px-5 py-4"><x-badge :color="$statusColor[$applicant->status] ?? 'gray'">{{ $statusLabels[$applicant->status] ?? 'Under Review' }}</x-badge></td>
                                <td class="px-5 py-4"><x-badge :color="$paymentColor($pay)">{{ $pay }}</x-badge></td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('admin.applicants.review', $applicant) }}" class="inline-flex h-9 items-center gap-2 rounded-md border border-emerald-100 bg-white px-3 text-xs font-bold text-emerald-700 transition hover:bg-emerald-50">
                                        <i data-lucide="file-search" class="h-4 w-4"></i>
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No applicants found for review.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">{{ $applicants->links() }}</div>
        </div>
    </section>
</x-admin-layout>
