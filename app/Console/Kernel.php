<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    //  Register your custom Artisan commands
    protected $commands = [
        \App\Console\Commands\SendTenderReminders::class,
        \App\Console\Commands\LicenseImportCommand::class,
        \App\Console\Commands\LicenseVerifyCommand::class,
        \App\Console\Commands\LicenseInfoCommand::class,
        \App\Console\Commands\SeedGrnMappingCommand::class,
        \App\Console\Commands\ProcessTripArrivalsCommand::class,
        \App\Console\Commands\RunDueSystemBackups::class,




        \App\Console\Commands\ExpireRecordsCommand::class,
        \App\Console\Commands\DMS\TrashRejectedCheckoutsCommand::class,
        \App\Console\Commands\DMS\SendRejectedCheckoutRemindersCommand::class,
        // HR / HCS commands
        \App\Console\Commands\ExpireRecruitmentOffers::class,
        \App\Console\Commands\HR\ProcessDailyLeaveJobs::class,
        \App\Console\Commands\PostMedicalFundEmployerPremiums::class,
    ];

    // Define your task scheduling here
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('dms:trash-rejected-checkouts')->dailyAt('00:30');
        $schedule->command('dms:remind-rejected-checkouts')->hourly();
        $schedule->command('tender:send-reminders')->dailyAt('15:00');

        $schedule->job(new \App\Jobs\CleanExpiredTokens())->daily();

        $schedule->command('trips:start-pending')->everyMinute();

        $schedule->command('app:process-trip-arrivals-command')->everyFiveMinutes();

        // Assets: policy and maintenance reminders (Email + SMS + in-app)
        $schedule->command('assets:insurance-reminders')->dailyAt('08:00');
        $schedule->command('assets:maintenance-reminders')->dailyAt('08:10');

        // Property: auto-generate monthly rent invoices on the 1st of each month at 07:00.
        // Also runs daily at 07:00 so that leases expiring within the current month
        // have their invoice raised before expiry (the command skips already-created ones).
        $schedule->command('property:generate-invoices')->monthlyOn(1, '07:00');
        $schedule->command('property:generate-invoices')->dailyAt('07:00')
                 ->when(fn () => (int) now()->day !== 1); // avoid double-run on the 1st

        // Property: apply configured rent escalations each day at 06:00.
        // The command only processes leases whose NextEscalationDate <= today.
        $schedule->command('property:apply-rent-escalations')->dailyAt('06:00');

        // Settings: run any system backup whose configured daily time is due.
        $schedule->command('system-backups:run-due')->everyMinute()->withoutOverlapping();

        // Safety net: retry pending-approval creation for workflow submissions
        // stuck with no eligible approvers (e.g. a role was granted permissions
        // after a submission already failed to route). The immediate path is
        // User::syncRolesWithBranch() triggering this right after a role grant;
        // this sweep catches other realignment triggers (e.g. permissions added
        // to an existing role) on a short interval.
        $schedule->command('workflow:reconcile')->everyFifteenMinutes()->withoutOverlapping();

        // HR: daily leave jobs (accruals, expiry, carry-forward)
        $schedule->command('hr:process-daily-leave-jobs')->dailyAt('01:00')->withoutOverlapping();

        // HR: expire recruitment offers that have passed their expiry date
        $schedule->command('hr:expire-recruitment-offers')->dailyAt('02:00')->withoutOverlapping();

        // Medical Scheme: post the employer's monthly premium ledger entry for each beneficiary
        // on employer-funded/hybrid funds. Lands as Pending for Finance to approve.
        $schedule->command('medicalfund:post-employer-premiums')->monthlyOn(1, '05:00');

        $schedule->command('finance:dispatch-cbs-postings --limit=100')
            ->everyMinute()
            ->withoutOverlapping();
        $schedule->command('finance:reconcile-cbs-postings --hours=24 --stale-minutes=15')
            ->dailyAt('03:00')
            ->withoutOverlapping();

    }

    // Register commands from the app/Console/Commands directory
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
