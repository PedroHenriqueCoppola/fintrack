<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Card;
use App\Models\Category;
use App\Models\FixedExpense;
use App\Models\Installment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankService;
use App\Services\CardInvoiceCalculator;
use App\Services\CardInvoiceService;
use App\Services\CardService;
use App\Services\CategoryService;
use App\Services\DefaultDataService;
use App\Services\FixedExpenseService;
use App\Services\GoalService;
use App\Services\InstallmentService;
use App\Services\NetWorthService;
use App\Services\PlanGenerator;
use App\Services\PlanningService;
use App\Services\ReserveBalanceService;
use App\Services\SavedFilterService;
use App\Services\TagService;
use App\Services\TransactionService;
use App\Support\Money;
use App\Support\Month;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recreates the portfolio demo account. Random choices use a fixed seed, and
 * every date is relative to today in America/Sao_Paulo.
 */
class DemoUserSeeder extends Seeder
{
    public const EMAIL = 'demo@fintrack.app';

    public const PASSWORD = 'fintrack-demo';

    public const NAME = 'Ana Demo';

    private const SEED = 20261125;

    /**
     * Net worth of the 12 closed months, in cents, oldest first.
     * One month dips, then the total reaches R$ 42.000,00.
     *
     * @var array<int, int>
     */
    private const WEALTH = [
        1800000, 1985000, 2210000, 2465000, 2730000, 2548000,
        2890000, 3155000, 3480000, 3765000, 4010000, 4200000,
    ];

    private Generator $faker;

    private string $today;

    private string $currentKey;

    /** @var array<string, Category> */
    private array $categories = [];

    /** @var array<string, PaymentMethod> */
    private array $methods = [];

    /** @var array<int, Bank> */
    private array $accountBanks = [];

    public function __construct(
        private DefaultDataService $defaults,
        private BankService $banks,
        private CardService $cards,
        private CategoryService $categoriesService,
        private FixedExpenseService $fixedExpenses,
        private InstallmentService $installments,
        private PlanGenerator $generator,
        private TransactionService $transactions,
        private PlanningService $planning,
        private GoalService $goals,
        private NetWorthService $netWorth,
        private CardInvoiceService $invoices,
        private SavedFilterService $savedFilters,
        private TagService $tags,
    ) {}

    public function run(): void
    {
        $this->seed();
    }

    /**
     * @return array<string, int|string>
     */
    public function seed(): array
    {
        $this->bootRandom();

        return DB::transaction(function () {
            $this->wipe();
            $user = $this->createUser();
            Auth::setUser($user);

            $catalog = $this->catalog($user);
            $fixed = $this->fixedExpenses($catalog);
            $this->installmentPlans($catalog);
            $this->history($fixed['energy']);
            $this->settleGenerated($catalog['nubankCard'], $catalog['itauCard']);
            $income = $this->incomeAndSpending($catalog);
            $this->reserves($catalog);
            $this->futurePendings($catalog);
            $this->attachTags();
            $this->goals();
            $this->plans($income);
            $this->varyEnergy($fixed['energy'], [Month::shift($this->currentKey, 1)]);
            $paidInvoices = $this->payInvoices($catalog);
            $this->closings();
            $this->filters();
            $this->assertReserves();

            return $this->summary($user, $paidInvoices);
        });
    }

    private function bootRandom(): void
    {
        mt_srand(self::SEED);
        $this->faker = Factory::create('pt_BR');
        $this->faker->seed(self::SEED);
        $this->today = now('America/Sao_Paulo')->toDateString();
        $this->currentKey = Month::currentKey();
    }

    private function wipe(): void
    {
        $user = User::query()->where('email', self::EMAIL)->first();

        if (! $user instanceof User) {
            return;
        }

        $id = $user->id;

        DB::table('tag_transaction')->whereIn('transaction_id', function ($query) use ($id) {
            $query->select('id')->from('transactions')->where('user_id', $id);
        })->delete();

        foreach ([
            'transactions',
            'generated_skips',
            'card_invoice_payments',
            'monthly_plan_items',
            'monthly_plans',
            'fixed_expenses',
            'installments',
            'net_worth_closing_items',
            'net_worth_closings',
            'goals',
            'saved_filters',
            'tags',
            'cards',
            'banks',
            'categories',
            'payment_methods',
        ] as $table) {
            DB::table($table)->where('user_id', $id)->delete();
        }

        DB::table('sessions')->where('user_id', $id)->delete();
        DB::table('password_reset_tokens')->where('email', self::EMAIL)->delete();
        $user->delete();
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => self::NAME,
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ]);
    }

    /**
     * @return array{nubank: Bank, itau: Bank, inter: Bank, nubankCard: Card, itauCard: Card}
     */
    private function catalog(User $user): array
    {
        $this->defaults->createFor($user);

        $nubank = $this->banks->create(['name' => 'Nubank', 'color' => $this->color('purple'), 'kind' => 'account']);
        $itau = $this->banks->create(['name' => 'Itaú', 'color' => $this->color('orange'), 'kind' => 'account']);
        $inter = $this->banks->create(['name' => 'Inter', 'color' => $this->color('magenta'), 'kind' => 'account']);
        $this->banks->create(['name' => 'Tesouro Direto', 'color' => $this->color('green'), 'kind' => 'investment']);
        $this->banks->create(['name' => 'CDB Inter', 'color' => $this->color('blue'), 'kind' => 'investment']);

        $nubankCard = $this->cards->create([
            'bank_id' => $nubank->id,
            'name' => 'Nubank Roxinho',
            'closing_day' => 3,
            'due_day' => 10,
            'color' => $this->color('purple'),
        ]);
        $itauCard = $this->cards->create([
            'bank_id' => $itau->id,
            'name' => 'Itaú Click',
            'closing_day' => 25,
            'due_day' => 5,
            'color' => $this->color('orange'),
        ]);

        $this->categoriesService->create([
            'name' => 'Viagem',
            'applies_to' => 'expense',
            'nature' => 'reserve',
            'icon' => 'Plane',
            'color' => $this->color('cyan'),
        ]);
        $this->categoriesService->create([
            'name' => 'Presentes',
            'applies_to' => 'expense',
            'nature' => 'reserve',
            'icon' => 'Gift',
            'color' => $this->color('rose'),
        ]);

        foreach (Category::query()->get() as $category) {
            $this->categories[$category->name] = $category;
        }

        foreach (PaymentMethod::query()->get() as $method) {
            $this->methods[$method->name] = $method;
        }

        $this->accountBanks = [$nubank, $itau, $inter];

        return [
            'nubank' => $nubank,
            'itau' => $itau,
            'inter' => $inter,
            'nubankCard' => $nubankCard,
            'itauCard' => $itauCard,
        ];
    }

    /**
     * @param  array{nubank: Bank, itau: Bank, inter: Bank, nubankCard: Card, itauCard: Card}  $catalog
     * @return array{energy: FixedExpense}
     */
    private function fixedExpenses(array $catalog): array
    {
        $start = $this->monthKey(-12);
        $pix = $this->methods['Pix']->id;
        $debit = $this->methods['Débito']->id;
        $credit = $this->methods['Crédito']->id;

        $rows = [
            ['Aluguel', '1850.00', 10, 'Moradia', $catalog['itau']->id, $pix, null],
            ['Condomínio', '480.00', 10, 'Moradia', $catalog['itau']->id, $pix, null],
            ['Internet', '119.90', 15, 'Moradia', $catalog['nubank']->id, $debit, null],
            ['Energia', '186.40', 20, 'Moradia', $catalog['inter']->id, $pix, null],
            ['Streaming', '55.90', 8, 'Lazer', $catalog['nubank']->id, $credit, $catalog['nubankCard']->id],
            ['Academia', '109.90', 12, 'Saúde', $catalog['itau']->id, $credit, $catalog['itauCard']->id],
        ];

        $energy = null;

        foreach ($rows as [$name, $amount, $day, $category, $bankId, $methodId, $cardId]) {
            $expense = $this->fixedExpenses->create([
                'name' => $name,
                'amount' => $amount,
                'due_day' => $day,
                'start_month' => $start,
                'category_id' => $this->categories[$category]->id,
                'bank_id' => $bankId,
                'payment_method_id' => $methodId,
                'card_id' => $cardId,
            ]);

            if ($name === 'Energia') {
                $energy = $expense;
            }
        }

        if (! $energy instanceof FixedExpense) {
            throw new RuntimeException('Energia não foi cadastrada.');
        }

        return ['energy' => $energy];
    }

    /**
     * @param  array{nubank: Bank, itau: Bank, inter: Bank, nubankCard: Card, itauCard: Card}  $catalog
     * @return array<int, Installment>
     */
    private function installmentPlans(array $catalog): array
    {
        $credit = $this->methods['Crédito']->id;

        return [
            $this->installments->create([
                'name' => 'Notebook',
                'total_amount' => '4899.00',
                'installments_count' => 10,
                'first_date' => $this->date($this->monthKey(-4), 12),
                'category_id' => $this->categories['Outros']->id,
                'bank_id' => $catalog['nubank']->id,
                'payment_method_id' => $credit,
                'card_id' => $catalog['nubankCard']->id,
            ]),
            $this->installments->create([
                'name' => 'Geladeira',
                'total_amount' => '2475.00',
                'installments_count' => 6,
                'first_date' => $this->date($this->monthKey(-2), 18),
                'category_id' => $this->categories['Moradia']->id,
                'bank_id' => $catalog['itau']->id,
                'payment_method_id' => $credit,
                'card_id' => $catalog['itauCard']->id,
            ]),
        ];
    }

    private function history(FixedExpense $energy): void
    {
        for ($ago = 12; $ago >= 1; $ago--) {
            $this->generator->generateMonth(Month::start($this->monthKey(-$ago)));
        }

        $this->varyEnergy($energy, $this->keysFrom(-12, 0));

        Transaction::query()
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNotNull('fixed_expense_id')->orWhereNotNull('installment_id');
            })
            ->whereDate('generated_for', '<', Month::currentStart())
            ->orderBy('id')
            ->each(function (Transaction $transaction) {
                $this->markPaid($transaction);
            });
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function varyEnergy(FixedExpense $energy, array $keys): void
    {
        foreach ($keys as $key) {
            if ($key === null) {
                continue;
            }

            $transaction = Transaction::query()
                ->where('fixed_expense_id', $energy->id)
                ->whereDate('generated_for', Month::start($key))
                ->first();

            if (! $transaction instanceof Transaction) {
                continue;
            }

            $amount = $this->cents($this->faker->numberBetween(14000, 23000));

            if (Money::compare($transaction->amount, $amount) === 0) {
                continue;
            }

            $this->transactions->update($transaction, $this->payload($transaction, $amount));
        }
    }

    private function settleGenerated(Card $nubankCard, Card $itauCard): void
    {
        $cards = [
            $nubankCard->id => $nubankCard,
            $itauCard->id => $itauCard,
        ];
        $dueToday = ['Condomínio', 'Internet', 'Energia'];

        Transaction::query()
            ->where('status', 'pending')
            ->whereDate('generated_for', Month::currentStart())
            ->where(function ($query) {
                $query->whereNotNull('fixed_expense_id')->orWhereNotNull('installment_id');
            })
            ->orderBy('id')
            ->get()
            ->each(function (Transaction $transaction) use ($cards, $dueToday) {
                if ($transaction->card_id === null) {
                    $due = $transaction->date->toDateString();
                    $shouldPay = $transaction->description === 'Aluguel'
                        || ($due <= $this->today && in_array($transaction->description, $dueToday, true));

                    if (! $shouldPay) {
                        return;
                    }

                    if ($due < $this->today) {
                        $this->markPaid($transaction);
                    } else {
                        $this->transactions->pay($transaction);
                    }

                    return;
                }

                $card = $cards[$transaction->card_id] ?? null;

                if (! $card instanceof Card) {
                    return;
                }

                $calculator = $this->calculator($card);
                $key = $calculator->invoiceKeyFor($transaction->date->toDateString());

                if ($key !== $calculator->openKey()) {
                    $this->transactions->pay($transaction);
                }
            });
    }

    /**
     * Past non-card bills keep the due date. TransactionService::pay() would
     * move a past debit or Pix to today, which would pile a year of rent on
     * the current day.
     */
    private function markPaid(Transaction $transaction): void
    {
        if ($transaction->status === 'paid' || $transaction->type !== 'expense') {
            return;
        }

        $transaction->status = 'paid';
        $transaction->save();
    }

    /**
     * @param  array{nubank: Bank, itau: Bank, inter: Bank, nubankCard: Card, itauCard: Card}  $catalog
     * @return array<string, string>
     */
    private function incomeAndSpending(array $catalog): array
    {
        $raise = $this->monthKey(-6);
        $freelanceMonths = $this->freelanceMonths();
        $income = [];

        foreach ($this->keysFrom(-12, 0) as $key) {
            $salary = $key >= $raise ? '7300.00' : '6800.00';
            $salaryDate = $this->date($key, 5);
            $this->income($catalog['nubank'], 'Salário', $this->categories['Salário'], $salary, $salaryDate);
            $monthIncome = $salary;

            if (isset($freelanceMonths[$key])) {
                $this->income($catalog['nubank'], $freelanceMonths[$key]['description'], $this->categories['Outras receitas'], $freelanceMonths[$key]['amount'], $this->date($key, 16));
                $monthIncome = Money::add($monthIncome, $freelanceMonths[$key]['amount']);
            }

            if ($key === $this->previousDecember()) {
                $bonus = $salary;
                $this->income($catalog['nubank'], 'Décimo terceiro', $this->categories['Salário'], $bonus, $this->date($key, 20));
                $monthIncome = Money::add($monthIncome, $bonus);
            }

            $income[$key] = $monthIncome;
            $this->variables($key, $catalog);
            $this->giftsOn($key, $catalog);
        }

        $next = $this->monthKey(1);
        $income[$next] = '7300.00';

        return $income;
    }

    /**
     * @return array<string, array{description: string, amount: string}>
     */
    private function freelanceMonths(): array
    {
        $agos = range(1, 12);

        for ($index = count($agos) - 1; $index > 0; $index--) {
            $swap = $this->faker->numberBetween(0, $index);
            [$agos[$index], $agos[$swap]] = [$agos[$swap], $agos[$index]];
        }

        $names = ['Freela de site', 'Freela de identidade', 'Freela de planilha', 'Freela de consultoria'];
        $chosen = [];

        foreach (array_slice($agos, 0, 4) as $offset => $ago) {
            $chosen[$this->monthKey(-$ago)] = [
                'description' => $names[$offset],
                'amount' => $this->cents($this->faker->numberBetween(80000, 220000)),
            ];
        }

        return $chosen;
    }

    /**
     * @param  array{nubank: Bank, itau: Bank, inter: Bank, nubankCard: Card, itauCard: Card}  $catalog
     */
    private function variables(string $key, array $catalog): void
    {
        $limit = $this->dayLimit($key);
        $made = 0;
        $target = $this->faker->numberBetween(60, 90);

        if ($limit >= 2) {
            $this->cardPurchase($catalog['nubankCard'], $this->date($key, 2), $this->cents($this->faker->numberBetween(2500, 8000)));
            $made++;
        }

        if ($limit >= 10) {
            $this->cardPurchase($catalog['itauCard'], $this->date($key, 10), $this->cents($this->faker->numberBetween(2500, 8000)));
            $made++;
        }

        foreach ([4, 11, 18, 25] as $day) {
            if ($day > $limit) {
                continue;
            }

            $this->spending(
                'Mercado',
                $this->pick(['Supermercado', 'Feira', 'Padaria', 'Hortifruti']),
                $this->date($key, $day),
                $this->cents($this->faker->numberBetween(9000, 16000)),
                $this->pick(['Pix', 'Débito']),
                null,
            );
            $made++;
        }

        while ($made < $target) {
            $template = $this->pickTemplate();
            $card = null;
            $method = $template['methods'][$this->faker->numberBetween(0, count($template['methods']) - 1)];

            if ($method === 'Crédito') {
                $card = $this->pick([$catalog['nubankCard'], $catalog['itauCard']]);
            }

            $this->spending(
                $template['category'],
                $template['descriptions'][$this->faker->numberBetween(0, count($template['descriptions']) - 1)],
                $this->date($key, $this->faker->numberBetween(1, $limit)),
                $this->cents($this->faker->numberBetween($template['min'], $template['max'])),
                $method,
                $card,
            );
            $made++;
        }
    }

    /**
     * @param  array{nubank: Bank, itau: Bank, inter: Bank, nubankCard: Card, itauCard: Card}  $catalog
     */
    private function giftsOn(string $key, array $catalog): void
    {
        foreach ($this->holidays($key) as $gift) {
            $method = $this->pick(['Pix', 'Crédito']);
            $card = $method === 'Crédito' ? $this->pick([$catalog['nubankCard'], $catalog['itauCard']]) : null;
            $this->spending('Outros', $gift['description'], $gift['date'], $this->cents($this->faker->numberBetween(3000, 9000)), $method, $card);
        }
    }

    /**
     * @return array<int, array{date: string, description: string}>
     */
    private function holidays(string $key): array
    {
        $year = (int) substr($key, 0, 4);
        $month = (int) substr($key, 5, 2);
        $dates = [
            5 => [$this->nthSunday($year, 5, 2) => 'Presente dia das maes'],
            6 => [$this->date($key, 12) => 'Presente dia dos namorados'],
            8 => [$this->nthSunday($year, 8, 2) => 'Presente dia dos pais'],
            10 => [$this->date($key, 12) => 'Presente dia das criancas'],
            12 => [$this->date($key, 20) => 'Presente de Natal'],
        ];
        $gifts = [];

        foreach ($dates[$month] ?? [] as $date => $description) {
            if ($date <= $this->today && str_starts_with($date, $key)) {
                $gifts[] = ['date' => $date, 'description' => $description];
            }
        }

        return $gifts;
    }

    /**
     * @param  array{nubank: Bank, itau: Bank, inter: Bank, nubankCard: Card, itauCard: Card}  $catalog
     */
    private function reserves(array $catalog): void
    {
        $bank = $catalog['inter'];
        $method = $this->methods['Transferência'];
        $emergency = $this->categories['Reserva de emergência'];
        $travel = $this->categories['Viagem'];
        $gifts = $this->categories['Presentes'];
        $giftMonths = [
            $this->monthKey(-10) => '500.00',
            $this->monthKey(-7) => '500.00',
            $this->monthKey(-3) => '500.00',
        ];
        $withdrawalKey = $this->monthKey(-5);

        foreach ($this->keysFrom(-12, 0) as $key) {
            $day = min(6, $this->dayLimit($key));
            $this->reserve($bank, $method, $emergency, 'deposit', $this->cents($this->faker->numberBetween(60000, 90000)), $this->date($key, $day), 'Guardar na reserva');
            $this->reserve($bank, $method, $travel, 'deposit', '300.00', $this->date($key, $day), 'Guardar para a viagem');

            if (isset($giftMonths[$key])) {
                $this->reserve($bank, $method, $gifts, 'deposit', $giftMonths[$key], $this->date($key, min(9, $this->dayLimit($key))), 'Guardar para presentes');
            }

            if ($key === $withdrawalKey) {
                $this->reserve($bank, $method, $emergency, 'withdraw', '1200.00', $this->date($key, 22), 'Resgate da reserva');
            }
        }
    }

    /**
     * @param  array{nubank: Bank, itau: Bank, inter: Bank, nubankCard: Card, itauCard: Card}  $catalog
     */
    private function futurePendings(array $catalog): void
    {
        foreach ([4, 9, 14, 19, 26, 33, 40, 48] as $index => $offset) {
            $date = Carbon::parse($this->today, 'America/Sao_Paulo')->addDays($offset)->toDateString();
            $template = $this->pickTemplate();
            $card = $index % 2 === 0 ? $this->pick([$catalog['nubankCard'], $catalog['itauCard']]) : null;
            $method = $card instanceof Card ? 'Crédito' : $template['methods'][0];

            if ($method === 'Crédito' && ! $card instanceof Card) {
                $method = 'Pix';
            }

            $this->transactions->create([
                'type' => 'expense',
                'status' => 'pending',
                'amount' => $this->cents($this->faker->numberBetween($template['min'], $template['max'])),
                'date' => $date,
                'description' => $template['descriptions'][0],
                'category_id' => $this->categories[$template['category']]->id,
                'bank_id' => $card instanceof Card ? $card->bank_id : $catalog['nubank']->id,
                'payment_method_id' => $this->methods[$method]->id,
                'card_id' => $card?->id,
            ]);
        }
    }

    private function attachTags(): void
    {
        $quotas = [
            'Supermercado' => ['casa', 8],
            'Padaria' => ['casa', 4],
            'Freela' => ['trabalho', 4],
            'Uber' => ['trabalho', 6],
            'Passeio' => ['viagem', 5],
            'Presente' => ['presente', 8],
        ];
        $used = array_fill_keys(array_keys($quotas), 0);
        $total = 0;

        Transaction::query()->orderBy('id')->each(function (Transaction $transaction) use (&$quotas, &$used, &$total) {
            if ($total >= 30) {
                return false;
            }

            foreach ($quotas as $prefix => [$tag, $limit]) {
                if ($used[$prefix] >= $limit || ! str_starts_with($transaction->description, $prefix)) {
                    continue;
                }

                $this->tags->sync($transaction, [$tag]);
                $used[$prefix]++;
                $total++;

                return;
            }
        });
    }

    private function goals(): void
    {
        $this->goals->create([
            'name' => 'Reserva de 6 meses',
            'target_amount' => '25000.00',
            'category_id' => $this->categories['Reserva de emergência']->id,
            'deadline' => $this->monthKey(8),
            'notes' => 'Seis meses de gastos essenciais.',
        ]);
        $this->goals->create([
            'name' => 'Viagem para o Chile',
            'target_amount' => '9000.00',
            'category_id' => $this->categories['Viagem']->id,
            'deadline' => $this->monthKey(5),
            'notes' => 'Passagens e hospedagem.',
        ]);

        $month = (int) substr($this->currentKey, 5, 2);
        $deadline = $month === 12 ? $this->currentKey : substr($this->currentKey, 0, 4).'-12';

        $this->goals->create([
            'name' => 'Presentes de fim de ano',
            'target_amount' => '1500.00',
            'category_id' => $this->categories['Presentes']->id,
            'deadline' => $deadline,
            'notes' => 'Compras de fim de ano.',
        ]);
    }

    /**
     * @param  array<string, string>  $income
     */
    private function plans(array $income): void
    {
        $items = [];

        foreach ([
            'Mercado' => '1000.00',
            'Moradia' => '3150.00',
            'Transporte' => '280.00',
            'Saúde' => '350.00',
            'Lazer' => '1120.00',
            'Outros' => '900.00',
            'Reserva de emergência' => '300.00',
            'Viagem' => '150.00',
            'Presentes' => '50.00',
        ] as $name => $amount) {
            $items[] = [
                'category_id' => $this->categories[$name]->id,
                'amount' => $amount,
            ];
        }

        foreach ([-3, -2, -1, 0, 1] as $offset) {
            $key = $this->monthKey($offset);
            $this->planning->save($key, [
                'expected_income' => $income[$key] ?? '7300.00',
                'items' => $items,
            ]);
        }

        $this->planning->confirm($this->monthKey(1));
    }

    /**
     * @param  array{nubank: Bank, itau: Bank, inter: Bank, nubankCard: Card, itauCard: Card}  $catalog
     */
    private function payInvoices(array $catalog): int
    {
        $paid = 0;

        foreach ([$catalog['nubankCard'], $catalog['itauCard']] as $card) {
            $calculator = $this->calculator($card);
            $open = $calculator->openKey();
            $keys = [];

            Transaction::query()
                ->where('card_id', $card->id)
                ->where('type', 'expense')
                ->orderBy('date')
                ->pluck('date')
                ->each(function ($date) use ($calculator, &$keys) {
                    $value = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);
                    $keys[$calculator->invoiceKeyFor($value)] = true;
                });

            foreach (array_keys($keys) as $key) {
                if ($key === $open) {
                    continue;
                }

                $closing = $calculator->periodFor($key)['closing'];

                if ($closing > $this->today) {
                    continue;
                }

                $due = $calculator->dueDateFor($key);
                $paidOn = $due !== null && $due >= $closing && $due <= $this->today ? $due : $closing;
                $this->invoices->pay($card, Month::start($key), $paidOn);
                $paid++;
            }
        }

        return $paid;
    }

    private function closings(): void
    {
        foreach (self::WEALTH as $index => $cents) {
            $key = $this->monthKey(-12 + $index);
            $split = $this->wealth($cents);
            $form = $this->netWorth->form($key);
            $items = [];

            foreach ($form['banks'] as $bank) {
                if (! isset($split[$bank['name']])) {
                    throw new RuntimeException('Banco sem saldo no fechamento: '.$bank['name']);
                }

                $items[] = [
                    'bank_id' => $bank['bank_id'],
                    'balance' => $split[$bank['name']],
                ];
            }

            $this->netWorth->create([
                'month' => $key,
                'items' => $items,
            ]);
        }
    }

    private function filters(): void
    {
        $this->savedFilters->create('Mercado do mês', [
            'category_id' => $this->categories['Mercado']->id,
            'period' => 'current_month',
        ]);
        $this->savedFilters->create('Só cartão', [
            'payment_method_id' => $this->methods['Crédito']->id,
        ]);
    }

    private function assertReserves(): void
    {
        $rows = DB::select(
            'select category_id, '.ReserveBalanceService::netSql().' as balance
             from transactions
             where user_id = ? and type = ? and status = ?
             group by category_id',
            [Auth::id(), 'reserve', 'paid'],
        );

        foreach ($rows as $row) {
            if (Money::compare($row->balance, '0') < 0) {
                throw new RuntimeException('Saldo de Reserva negativo na categoria '.$row->category_id.'.');
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function summary(User $user, int $paidInvoices): array
    {
        $id = $user->id;

        return [
            'transactions' => Transaction::query()->count(),
            'income' => Transaction::query()->where('type', 'income')->count(),
            'expenses' => Transaction::query()->where('type', 'expense')->count(),
            'reserves' => Transaction::query()->where('type', 'reserve')->count(),
            'pending' => Transaction::query()->where('status', 'pending')->count(),
            'fixed' => DB::table('fixed_expenses')->where('user_id', $id)->count(),
            'installments' => DB::table('installments')->where('user_id', $id)->count(),
            'goals' => DB::table('goals')->where('user_id', $id)->count(),
            'closings' => DB::table('net_worth_closings')->where('user_id', $id)->count(),
            'plans' => DB::table('monthly_plans')->where('user_id', $id)->count(),
            'invoices' => $paidInvoices,
            'tags' => DB::table('tags')->where('user_id', $id)->count(),
            'tagged' => Transaction::query()->whereHas('tags')->count(),
            'filters' => DB::table('saved_filters')->where('user_id', $id)->count(),
        ];
    }

    private function income(Bank $bank, string $description, Category $category, string $amount, string $date): void
    {
        $status = $date <= $this->today ? 'paid' : 'pending';

        $this->transactions->create([
            'type' => 'income',
            'status' => $status,
            'amount' => $amount,
            'date' => $date,
            'description' => $description,
            'category_id' => $category->id,
            'bank_id' => $bank->id,
            'payment_method_id' => $this->methods['Pix']->id,
        ]);
    }

    private function spending(string $category, string $description, string $date, string $amount, string $method, ?Card $card): void
    {
        if ($date > $this->today) {
            throw new RuntimeException('Despesa paga no futuro: '.$description);
        }

        $this->transactions->create([
            'type' => 'expense',
            'status' => 'paid',
            'amount' => $amount,
            'date' => $date,
            'description' => $description,
            'category_id' => $this->categories[$category]->id,
            'bank_id' => $card instanceof Card ? $card->bank_id : $this->pick($this->accountBanks)->id,
            'payment_method_id' => $this->methods[$method]->id,
            'card_id' => $card?->id,
        ]);
    }

    private function cardPurchase(Card $card, string $date, string $amount): void
    {
        $this->spending('Outros', 'Compra no cartao', $date, $amount, 'Crédito', $card);
    }

    private function reserve(Bank $bank, PaymentMethod $method, Category $category, string $action, string $amount, string $date, string $description): void
    {
        $this->transactions->create([
            'type' => 'reserve',
            'reserve_action' => $action,
            'status' => 'paid',
            'amount' => $amount,
            'date' => $date,
            'description' => $description,
            'category_id' => $category->id,
            'bank_id' => $bank->id,
            'payment_method_id' => $method->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Transaction $transaction, string $amount): array
    {
        return [
            'type' => $transaction->type,
            'reserve_action' => $transaction->reserve_action,
            'status' => $transaction->status,
            'amount' => $amount,
            'date' => $transaction->date->toDateString(),
            'competence_date' => $transaction->competence_date?->toDateString(),
            'description' => $transaction->description,
            'notes' => $transaction->notes,
            'category_id' => $transaction->category_id,
            'bank_id' => $transaction->bank_id,
            'payment_method_id' => $transaction->payment_method_id,
            'card_id' => $transaction->card_id,
        ];
    }

    /**
     * @return array<int, array{category: string, descriptions: array<int, string>, methods: array<int, string>, min: int, max: int, weight: int}>
     */
    private function templates(): array
    {
        return [
            ['category' => 'Mercado', 'descriptions' => ['Supermercado', 'Feira', 'Padaria', 'Hortifruti'], 'methods' => ['Pix', 'Débito'], 'min' => 1500, 'max' => 4200, 'weight' => 4],
            ['category' => 'Lazer', 'descriptions' => ['Restaurante', 'Delivery'], 'methods' => ['Pix', 'Crédito'], 'min' => 1500, 'max' => 4200, 'weight' => 8],
            ['category' => 'Transporte', 'descriptions' => ['Uber'], 'methods' => ['Pix'], 'min' => 900, 'max' => 2400, 'weight' => 6],
            ['category' => 'Transporte', 'descriptions' => ['Combustivel'], 'methods' => ['Débito'], 'min' => 7000, 'max' => 12000, 'weight' => 1],
            ['category' => 'Transporte', 'descriptions' => ['Onibus'], 'methods' => ['Pix'], 'min' => 450, 'max' => 900, 'weight' => 3],
            ['category' => 'Saúde', 'descriptions' => ['Farmacia'], 'methods' => ['Pix', 'Débito'], 'min' => 900, 'max' => 3200, 'weight' => 4],
            ['category' => 'Lazer', 'descriptions' => ['Cinema', 'Bar'], 'methods' => ['Pix', 'Crédito'], 'min' => 1500, 'max' => 4200, 'weight' => 4],
            ['category' => 'Outros', 'descriptions' => ['Compras'], 'methods' => ['Crédito'], 'min' => 1800, 'max' => 6500, 'weight' => 3],
            ['category' => 'Lazer', 'descriptions' => ['Passeio'], 'methods' => ['Pix', 'Crédito'], 'min' => 1500, 'max' => 5000, 'weight' => 3],
            ['category' => 'Outros', 'descriptions' => ['Presente'], 'methods' => ['Pix', 'Crédito'], 'min' => 1800, 'max' => 5500, 'weight' => 2],
        ];
    }

    /**
     * @return array{category: string, descriptions: array<int, string>, methods: array<int, string>, min: int, max: int, weight: int}
     */
    private function pickTemplate(): array
    {
        $templates = $this->templates();
        $total = array_sum(array_column($templates, 'weight'));
        $cursor = $this->faker->numberBetween(1, $total);

        foreach ($templates as $template) {
            $cursor -= $template['weight'];

            if ($cursor <= 0) {
                return $template;
            }
        }

        return $templates[0];
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function pick(array $items): mixed
    {
        return $items[$this->faker->numberBetween(0, count($items) - 1)];
    }

    /**
     * @return array<string, string>
     */
    private function wealth(int $cents): array
    {
        $accounts = intdiv($cents * 58, 100);
        $investments = $cents - $accounts;
        $nubank = intdiv($accounts * 36, 100);
        $itau = intdiv($accounts * 44, 100);
        $inter = $accounts - $nubank - $itau;
        $tesouro = intdiv($investments * 68, 100);
        $cdb = $investments - $tesouro;

        return [
            'Nubank' => $this->cents($nubank),
            'Itaú' => $this->cents($itau),
            'Inter' => $this->cents($inter),
            'Tesouro Direto' => $this->cents($tesouro),
            'CDB Inter' => $this->cents($cdb),
        ];
    }

    private function calculator(Card $card): CardInvoiceCalculator
    {
        return new CardInvoiceCalculator((int) $card->closing_day, $card->due_day === null ? null : (int) $card->due_day);
    }

    /**
     * @return array<int, string>
     */
    private function keysFrom(int $from, int $to): array
    {
        $keys = [];

        for ($offset = $from; $offset <= $to; $offset++) {
            $keys[] = $this->monthKey($offset);
        }

        return $keys;
    }

    private function monthKey(int $offset): string
    {
        $key = Month::shift($this->currentKey, $offset);

        if ($key === null) {
            throw new RuntimeException('Mês fora da faixa do sistema.');
        }

        return $key;
    }

    private function previousDecember(): string
    {
        $year = (int) substr($this->currentKey, 0, 4) - 1;

        return sprintf('%04d-12', $year);
    }

    private function date(string $key, int $day): string
    {
        return Month::clampDay($key, $day);
    }

    private function dayLimit(string $key): int
    {
        $last = (int) substr($this->date($key, 31), 8, 2);

        if ($key !== $this->currentKey) {
            return $last;
        }

        return min($last, (int) substr($this->today, 8, 2));
    }

    private function nthSunday(int $year, int $month, int $n): string
    {
        $date = Carbon::create($year, $month, 1, 0, 0, 0, 'America/Sao_Paulo');
        $date->addDays((Carbon::SUNDAY - $date->dayOfWeek + 7) % 7);
        $date->addWeeks($n - 1);

        return $date->toDateString();
    }

    private function cents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private function color(string $key): string
    {
        foreach (config('fintrack.colors') as $color) {
            if ($color['key'] === $key) {
                return $color['value'];
            }
        }

        throw new RuntimeException('Missing fintrack color: '.$key);
    }
}
