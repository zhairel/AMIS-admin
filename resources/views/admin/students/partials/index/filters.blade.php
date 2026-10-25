<!-- Filter Bar Form -->
<form method="GET" class="mb-5 grid grid-cols-12 gap-3 print:hidden" id="filterForm">
    <input type="hidden" name="sort" value="{{ $sort }}">
    <input type="hidden" name="direction" value="{{ $direction }}">
    @if(request('school_year'))
        <input type="hidden" name="school_year" value="{{ request('school_year') }}">
    @endif
    <label class="relative col-span-12 lg:col-span-2">
        <i data-lucide="search" class="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-slate-400"></i>
        <input name="search" value="{{ request('search') }}" placeholder="Search name, ID, or email" class="{{ $inputClass }} w-full pl-9">
    </label>
    <select name="grade" class="{{ $inputClass }} col-span-6 lg:col-span-2 w-full" onchange="this.form.submit()">
        @unless ($isTeacherAdminViewer)
            <option value="">All grades</option>
        @endunless
        @foreach($gradeOrder as $g)
            <option value="{{ $g }}" @selected(request('grade') === $g)>{{ $g }}</option>
        @endforeach
    </select>
    <select name="type" class="{{ $inputClass }} col-span-6 lg:col-span-1 w-full" onchange="this.form.submit()">
        <option value="">All types</option>
        <option value="new" @selected(request('type') === 'new')>New Student</option>
        <option value="old" @selected(request('type') === 'old')>Old Student</option>
        <option value="transferee" @selected(request('type') === 'transferee')>Transferee</option>
    </select>
    <select name="gender" class="{{ $inputClass }} col-span-6 lg:col-span-1 w-full" onchange="this.form.submit()">
        <option value="">All genders</option>
        <option value="male" @selected(request('gender') === 'male')>Male</option>
        <option value="female" @selected(request('gender') === 'female')>Female</option>
        <option value="not_set" @selected(request('gender') === 'not_set')>Not Set</option>
    </select>
    <select name="mode" class="{{ $inputClass }} col-span-6 lg:col-span-2 w-full" onchange="this.form.submit()">
        <option value="">All learning modes</option>
        <option value="Face-to-Face" @selected(request('mode') === 'Face-to-Face')>Face-to-Face</option>
        <option value="1st Shift" @selected(request('mode') === '1st Shift')>Flexible Online 1st Shift</option>
        <option value="2nd Shift" @selected(request('mode') === '2nd Shift')>Flexible Online 2nd Shift</option>
    </select>
    <select name="ms_status" class="{{ $inputClass }} col-span-6 lg:col-span-1 w-full" onchange="this.form.submit()">
        <option value="">All syncs</option>
        <option value="no_license" @selected(request('ms_status') === 'no_license')>No License</option>
        <option value="enrolled" @selected(request('ms_status') === 'enrolled')>Synced</option>
        <option value="failed" @selected(request('ms_status') === 'failed')>Failed</option>
        <option value="pending" @selected(request('ms_status') === 'pending')>Pending</option>
        <option value="no_account" @selected(request('ms_status') === 'no_account')>No Acc</option>
    </select>
    <select name="password_status" class="{{ $inputClass }} col-span-6 lg:col-span-2 w-full" onchange="this.form.submit()">
        <option value="">All password states</option>
        <option value="changed" @selected(request('password_status') === 'changed')>Changed Password</option>
        <option value="temp" @selected(request('password_status') === 'temp')>Temporary Password</option>
        <option value="no_account" @selected(request('password_status') === 'no_account')>No Microsoft Account</option>
    </select>
    <button class="col-span-12 lg:col-span-1 inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition-colors duration-100 hover:bg-emerald-800">
        <i data-lucide="filter" class="h-4 w-4"></i>
        <span class="lg:hidden">Filter</span>
    </button>
</form>
