<?php

namespace App\Console\Commands;

use App\Mail\PaymentReminderMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminders extends Command
{
    protected $signature = 'email:send-payment-reminder 
                            {--send : Actually dispatch emails to all parents and students} 
                            {--preview= : Send a single test email to this address for preview}
                            {--month= : Billing month in YYYY-MM format, defaults to current month (e.g. 2026-10)}
                            {--name= : Recipient name for preview}
                            {--force : Skip confirmation prompt for non-interactive scripts}';

    protected $description = 'Stage, preview, or dispatch the monthly payment reminder email to active students and their parents.';

    public function handle()
    {
        // 1. Query unique parent emails of active students
        $parentEmails = DB::table('students')
            ->join('enrollment_applicants', 'students.enrollment_applicant_id', '=', 'enrollment_applicants.id')
            ->whereNotNull('enrollment_applicants.parent_email')
            ->where('enrollment_applicants.parent_email', '<>', '')
            ->distinct()
            ->pluck('enrollment_applicants.parent_email')
            ->toArray();

        // 2. Query unique student emails of active students
        $studentEmails = DB::table('students')
            ->whereNotNull('school_email')
            ->where('school_email', '<>', '')
            ->distinct()
            ->pluck('school_email')
            ->toArray();

        $allEmails = array_values(array_unique(array_filter(array_merge($parentEmails, $studentEmails))));

        $totalParents = count($parentEmails);
        $totalStudents = count($studentEmails);
        $totalRecipients = count($allEmails);

        $this->info('==================================================');
        $this->info('      AL MUNAWWARA MONTHLY PAYMENT REMINDER      ');
        $this->info('==================================================');
        $this->info('Active Student Records Filter Applied:');
        $this->info("  - Unique Parent Emails: {$totalParents}");
        $this->info("  - Unique Student Emails: {$totalStudents}");
        $this->info("  - Total Unique Recipients: {$totalRecipients}");
        $this->info('==================================================');

        // 3. Handle Preview Mode
        $previewAddress = $this->option('preview');
        if ($previewAddress) {
            $month = $this->option('month') ?: '2026-10';
            $name = $this->option('name') ?: 'Mon Lingasa';
            $this->info("Sending test/preview email to: {$previewAddress} (Month: {$month}, Name: {$name})...");
            try {
                Mail::to($previewAddress)->send(new PaymentReminderMail(
                    recipientName: $name,
                    billingMonth: $month,
                    dispatchRef: 'TEST-' . date('dHis')
                ));
                $this->info("SUCCESS: Test email successfully sent to {$previewAddress}!");
            } catch (\Exception $e) {
                $this->error('ERROR sending preview email: '.$e->getMessage());
            }

            return Command::SUCCESS;
        }

        // 4. Handle Actual Sending
        $actuallySend = $this->option('send');
        if ($actuallySend) {
            $this->error("Bulk payment reminders via SMTP have been disabled to prevent exceeding the server's hourly sending limits (max 500/hour).");
            $this->info('Please use the single-student payment reminder feature on the aging report page instead.');

            return Command::FAILURE;
        }

        // 5. Default: Dry Run Mode
        $this->warn("\n*** CURRENTLY IN DRY-RUN / PREVIEW MODE ***");
        $this->info('No emails were dispatched to parents/students (wag muna send).');
        $this->info('To send a test copy to yourself/finance to see how it looks, run:');
        $this->comment('  php artisan email:send-payment-reminder --preview=your-email@example.com');
        $this->info("\nTo dispatch this reminder to all {$totalRecipients} recipients, run:");
        $this->comment('  php artisan email:send-payment-reminder --send');
        $this->info('==================================================');

        return Command::SUCCESS;
    }
}
