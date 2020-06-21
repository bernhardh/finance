<?php

namespace App\Jobs;

use App\Contracts\Services\PlaidServiceContract;
use App\Events\RegroupEvent;
use App\Events\TransactionCreated;
use App\Events\TransactionUpdated;
use App\Filters\TransactionsConditionFilter;
use App\Jobs\Traits\PlaidTryCatchErrorForToken;
use App\Tag;
use App\Models\AccessToken;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Kregel\LaravelAbstract\Repositories\GenericRepository;

class SyncTagsWithTransactionsInDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, PlaidTryCatchErrorForToken;

    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $page = 1;

        do {
            $transactions = Transaction::whereIn(
                'account_id',
                Account::whereIn(
                    'access_token_id',
                    AccessToken::where('user_id', $this->user->id)
                        ->pluck('id')
                )->pluck('account_id')
            )
                ->paginate(100, ['*'], 'page', $page++);

            foreach ($transactions as $transaction) {
                event(new RegroupEvent($transaction));
            }
        } while ($transactions->hasMorePages());
    }
}
