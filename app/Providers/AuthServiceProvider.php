<?php

namespace App\Providers;

use App\Models\FiscalYear;
use App\Models\IcTransactionLeg;
use App\Models\Message;
use App\Models\Period;
use App\Models\Thread;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Policies\FiscalYearPolicy;
use App\Policies\IcTransactionLegPolicy;
use App\Policies\MessagePolicy;
use App\Policies\PeriodPolicy;
use App\Policies\ThreadPolicy;
use App\Policies\TransactionTemplatePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        TransactionTemplate::class => TransactionTemplatePolicy::class,
        FiscalYear::class => FiscalYearPolicy::class,
        Period::class => PeriodPolicy::class,
        IcTransactionLeg::class => IcTransactionLegPolicy::class,
        Thread::class => ThreadPolicy::class,
        Message::class => MessagePolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
