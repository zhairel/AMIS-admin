<x-admin-layout
    title="Requirements"
    :breadcrumbs="[
        ['label' => 'Applications', 'href' => route('admin.applications.enrollment')],
        ['label' => 'Requirements', 'href' => null],
    ]"
>
    @php
        $inputClass = 'h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100';
        $docColor = ['approved' => 'green', 'rejected' => 'red', 'pending' => 'yellow'];
    @endphp

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-emerald-700">Applications Workspace</p>
                <h1 class="mt-1 text-xl font-bold text-slate-950">Requirements</h1>
                <p class="mt-1 text-sm text-slate-500">Track required documents before final approval.</p>
            </div>
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                {{ number_format($applicants->total()) }} records
            </span>
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
                <select name="grade" class="{{ $inputClass }} col-span-3 w-full" onchange="this.form.submit()">
                    <option value="">All grades</option>
                    @foreach ($gradeLevels as $grade)
                        <option value="{{ $grade }}" @selected(request('grade') === $grade)>{{ $grade }}</option>
                    @endforeach
                </select>
                <select name="payment_status" class="{{ $inputClass }} col-span-3 w-full" onchange="this.form.submit()">
                    <option value="">All payments</option>
                    <option value="pending" @selected(request('payment_status') === 'pending')>Pending Payment</option>
                    <option value="verified" @selected(request('payment_status') === 'verified')>Verified Payment</option>
                    <option value="rejected" @selected(request('payment_status') === 'rejected')>Rejected Payment</option>
                    <option value="no_payment" @selected(request('payment_status') === 'no_payment')>No Payment</option>
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
                            <th class="px-5 py-4 font-bold">Student</th>
                            <th class="w-32 px-5 py-4 font-bold">Grade</th>
                            <th class="px-5 py-4 font-bold">Required Documents</th>
                            <th class="w-40 px-5 py-4 font-bold">Readiness</th>
                            <th class="w-36 px-5 py-4 text-right font-bold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($applicants as $applicant)
                            @php
                                $name = \Illuminate\Support\Str::upper(trim($applicant->first_name.' '.$applicant->middle_name.' '.$applicant->last_name));
                                $requirements = $reviewService->getRequiredDocuments($applicant);
                                $statuses = $applicant->document_statuses ?? [];
                                $approved = collect(array_keys($requirements))->filter(fn ($key) => ($statuses[$key] ?? 'pending') === 'approved')->count();
                                $ready = $approved === count($requirements);
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
                                    <div class="mt-0.5 text-xs font-medium text-slate-500">Applicant #{{ str_pad($applicant->id, 4, '0', STR_PAD_LEFT) }}</div>
                                </td>
                                <td class="px-5 py-4 font-bold text-slate-700">{{ $applicant->grade_abbr }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($requirements as $key => $label)
                                            @php $state = $statuses[$key] ?? 'pending'; @endphp
                                            <x-badge :color="$docColor[$state] ?? 'gray'">{{ $label }}: {{ \Illuminate\Support\Str::headline($state) }}</x-badge>
                                        @endforeach
                                        @if ($applicant->payment)
                                            @php $pStatus = $applicant->payment->status ?? 'pending'; @endphp
                                            <x-badge :color="$pStatus === 'verified' ? 'green' : ($pStatus === 'rejected' ? 'red' : 'yellow')">
                                                Payment: {{ ucfirst($pStatus) }}
                                            </x-badge>
                                        @else
                                            <x-badge color="gray">Payment: No Payment</x-badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <x-badge :color="$ready ? 'green' : 'yellow'">{{ $approved }}/{{ count($requirements) }} Approved</x-badge>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('admin.applicants.review', $applicant) }}" class="inline-flex h-9 items-center gap-2 rounded-md border border-emerald-100 bg-white px-3 text-xs font-bold text-emerald-700 transition hover:bg-emerald-50">
                                        <i data-lucide="list-checks" class="h-4 w-4"></i>
                                        Check
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500">No requirement records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">{{ $applicants->links() }}</div>
        </div>
    </section>
</x-admin-layout>
