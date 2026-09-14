<?php

namespace App\Console\Commands;

use App\Models\StaffVerification;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class StaffVerificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staff:verify 
                            {action=list : Action to perform (list, add)}
                            {--name= : Full name of staff}
                            {--position= : Position / Title}
                            {--email= : Official AMIS email}
                            {--title= : Academic title / credentials (e.g. Ph.D, LPT)}
                            {--phone= : Contact phone number}
                            {--slug= : Custom slug (auto-generated if empty)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage AMIS staff verification records (list, add)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        if ($action === 'add') {
            return $this->addStaff();
        }

        return $this->listStaff();
    }

    protected function listStaff(): int
    {
        $staff = StaffVerification::orderBy('id')->get();

        if ($staff->isEmpty()) {
            $this->warn('No staff verification records found.');
            return 0;
        }

        $this->info('=== AMIS Staff Verification Registry ===');
        $rows = [];
        foreach ($staff as $s) {
            $rows[] = [
                $s->id,
                $s->slug,
                $s->display_name,
                $s->position,
                $s->official_email,
                $s->verification_url,
                $s->status,
            ];
        }

        $this->table(
            ['ID', 'Slug', 'Name & Title', 'Position', 'Official Email', 'Verification URL', 'Status'],
            $rows
        );

        return 0;
    }

    protected function addStaff(): int
    {
        $name = $this->option('name') ?: $this->ask('Full Name of Official / Staff');
        if (!$name) {
            $this->error('Full Name is required.');
            return 1;
        }

        $position = $this->option('position') ?: $this->ask('Position (e.g. Director, School Principal, Teacher)');
        if (!$position) {
            $this->error('Position is required.');
            return 1;
        }

        $email = $this->option('email') ?: $this->ask('Official Email Address');
        if (!$email) {
            $this->error('Official Email is required.');
            return 1;
        }

        $title = $this->option('title') ?: $this->ask('Academic Credentials / Title (optional, e.g. Ph.D, LPT, MMEM)');
        $phone = $this->option('phone') ?: $this->ask('Contact Phone Number (optional)');
        
        $defaultSlug = Str::slug($name);
        $slug = $this->option('slug') ?: $this->ask('Verification URL Slug', $defaultSlug);
        $slug = Str::slug($slug);

        if (StaffVerification::where('slug', $slug)->exists()) {
            $this->error("A staff member with slug '{$slug}' already exists.");
            return 1;
        }

        $staff = StaffVerification::create([
            'slug' => $slug,
            'full_name' => $name,
            'academic_title' => $title,
            'position' => $position,
            'official_email' => $email,
            'phone_number' => $phone,
            'office_address' => 'Don Julian Rodriguez Sr. Avenue, Ma-A, Davao City, 8,000, Philippines',
            'badge_title' => 'Verified AMIS Official',
            'official_website' => 'https://amis.edu.ph',
            'status' => 'active',
            'verified_since' => now(),
        ]);

        $this->info("Staff verification created successfully!");
        $this->line("Verification URL: " . $staff->verification_url);

        return 0;
    }
}
