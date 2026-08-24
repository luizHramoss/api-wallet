<?php

namespace App\Console\Commands;

use App\Services\RecurringBillService;
use Illuminate\Console\Command;

class GenerateRecurringBillOccurrences extends Command
{
    protected $signature = 'bills:generate-occurrences';

    protected $description = 'Materializa as próximas ocorrências das contas fixas ativas como transações previstas (planned).';

    public function __construct(private readonly RecurringBillService $recurringBillService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->recurringBillService->generateDueOccurrences();

        $this->info("Ocorrências de contas fixas geradas: {$count}");

        return self::SUCCESS;
    }
}
