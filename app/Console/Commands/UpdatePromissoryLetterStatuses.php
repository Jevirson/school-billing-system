<?php

namespace App\Console\Commands;

use App\Models\PromissoryLetter;
use App\Models\PromissoryLetterEvent;
use App\Models\User;
use App\Notifications\PromissoryLetterOverdueNotification;
use App\Notifications\PromissoryLetterStatusNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdatePromissoryLetterStatuses extends Command
{
    protected $signature = 'promissory:mark-overdue';

    protected $description = 'Mark unpaid approved promissory letters overdue and completed bills fulfilled';

    public function handle(): int
    {
        PromissoryLetter::with(['bill.payments', 'studentProfile.user'])
            ->whereIn('status', ['Approved', 'Overdue'])
            ->orderBy('id')
            ->chunkById(100, function ($letters): void {
                foreach ($letters as $letter) {
                    $balance = (float) $letter->bill->remaining_balance;

                    if ($balance <= 0 && $letter->status !== 'Fulfilled') {
                        DB::transaction(function () use ($letter): void {
                            $locked = PromissoryLetter::whereKey($letter->id)->lockForUpdate()->firstOrFail();
                            if (! in_array($locked->status, ['Approved', 'Overdue'], true) || (float) $locked->bill->fresh()->remaining_balance > 0) {
                                return;
                            }

                            $locked->update(['status' => 'Fulfilled']);
                            PromissoryLetterEvent::create([
                                'promissory_letter_id' => $locked->id,
                                'status' => 'Fulfilled',
                                'note' => 'The original invoice was fully paid.',
                            ]);
                            $locked->studentProfile->user->notify(new PromissoryLetterStatusNotification($locked));
                        });

                        continue;
                    }

                    if ($balance <= 0 || ! $letter->commitment_date->isBefore(today()) || $letter->status !== 'Approved') {
                        continue;
                    }

                    $updated = DB::transaction(function () use ($letter): ?PromissoryLetter {
                        $locked = PromissoryLetter::whereKey($letter->id)->lockForUpdate()->firstOrFail();
                        if ($locked->status !== 'Approved' || (float) $locked->bill->fresh()->remaining_balance <= 0 || ! $locked->commitment_date->isBefore(today())) {
                            return null;
                        }

                        $locked->update(['status' => 'Overdue']);
                        PromissoryLetterEvent::create([
                            'promissory_letter_id' => $locked->id,
                            'status' => 'Overdue',
                            'note' => 'The commitment date passed with an outstanding balance.',
                        ]);

                        return $locked->fresh();
                    });

                    if ($updated) {
                        User::where('role', User::ROLE_ADMIN)->each(
                            fn (User $admin) => $admin->notify(new PromissoryLetterOverdueNotification($updated))
                        );
                        $this->line('Marked promissory letter '.$updated->id.' overdue.');
                    }
                }
            });

        return self::SUCCESS;
    }
}
