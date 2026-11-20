# Fintrack — Arquitetura

Este documento define a estrutura de pastas e o modelo do banco. As regras de negócio estão em `docs/product-vision.md` e têm prioridade sobre este arquivo. Se um módulo precisar mudar algo aqui, o agente avisa na seção "Atenção" e espera a aprovação do autor. O agente não edita este arquivo sozinho.

## Containers (Docker Compose)

| Serviço | Função | Porta no Windows |
| --- | --- | --- |
| app | PHP e Laravel (`php artisan serve`) | 8000 |
| vite | Node, servidor de desenvolvimento do Vite | 5173 |
| db | PostgreSQL, versão principal fixa, dados em volume nomeado | não exposta |
| pgadmin | Interface web do banco | 5050 |

O código fica montado do Linux do WSL dentro dos containers. Todo comando roda por `docker compose exec app ...` ou `docker compose run --rm ...`.

## Estrutura de pastas

```text
fintrack/
├── .cursor/rules/
├── docs/                  product-vision.md, architecture.md, route-map.md
├── docker/                Dockerfiles e arquivos de configuração dos containers
├── scripts/               scripts de apoio (backup, quando existir)
├── docker-compose.yml
├── .env.example
├── app/
│   ├── Http/
│   │   ├── Controllers/   um controller por recurso (CategoryController, ...)
│   │   ├── Requests/      FormRequests, mensagens em português
│   │   ├── Resources/     moldam os dados enviados às páginas
│   │   └── Middleware/
│   ├── Models/
│   └── Services/          regras de negócio (transações, planejamento, faturas, ...)
├── database/
│   ├── migrations/
│   └── seeders/           inclui o usuário demo
├── resources/
│   ├── css/app.css        variáveis globais e temas
│   ├── js/
│   │   ├── app.jsx
│   │   ├── components/    uma pasta por componente: Button/Button.jsx + Button.css
│   │   ├── layouts/       AppLayout, GuestLayout
│   │   ├── pages/         uma pasta por módulo: Dashboard/Index.jsx + Index.css
│   │   └── lib/           format.js (moeda e data pt-BR), theme.js
│   └── views/app.blade.php
└── routes/web.php
```

## Convenções

- Tabelas em inglês, plural, snake_case (`monthly_plans`). Models no singular (`MonthlyPlan`).
- URLs em inglês (`/transactions`); os textos da tela em português.
- Telas são páginas Inertia. Criar e editar acontece em modais com `useForm`, enviando para rotas normais; não há API JSON separada.
- Dinheiro `DECIMAL(15,2)`; meses guardados como a data do primeiro dia do mês (`2026-11-01`).
- Valores de lista fechada (tipo, natureza, estado) ficam em coluna string com restrição no banco, e valores em inglês.
- Toda tabela de dados do usuário tem `user_id` com chave estrangeira e índice. As consultas sempre filtram pelo usuário logado.
- Registros com histórico usam `archived_at` (nulo = ativo) e não são apagados. A transação é a exceção: excluir apaga a linha.
- Todas as tabelas têm `id`, `created_at` e `updated_at`, além dos campos listados.

## Modelo do banco

As tabelas padrão do Laravel (`sessions`, `cache`, `jobs`, `password_reset_tokens`) também existem.

| Tabela | Campos | Observações |
| --- | --- | --- |
| users | name, email (único), password, remember_token, high_impact_percent (1 a 100, padrão 30) | login por sessão; a porcentagem não entra no preenchimento em massa, só muda pelo endpoint do planejamento |
| banks | user_id, name, color?, archived_at? | |
| categories | user_id, name, applies_to (expense, income), nature? (spending, reserve; só para expense), icon?, color?, initial_month?, archived_at? | o valor do mês fica em monthly_plan_items; o gasto do mês é calculado |
| cards | user_id, bank_id, name, closing_day, due_day?, color?, archived_at? | faturas são derivadas das transações; o ciclo fica em `CardInvoiceCalculator` |
| payment_methods | user_id, name, requires_card (bool), archived_at? | |
| transactions | user_id, type (income, expense), status (paid, pending), amount (DECIMAL(15,2), sempre positivo), date, competence_date?, description (até 120), category_id, bank_id, payment_method_id, card_id?, notes? (até 500), fixed_expense_id?, installment_id?, installment_number?, generated_for? (dia 1) | chaves estrangeiras com restrict; índices em (user_id, date), (user_id, category_id), (user_id, bank_id), (user_id, status) e (user_id, generated_for); únicos parciais (fixed_expense_id, generated_for) e (installment_id, installment_number); nunca os dois vínculos ao mesmo tempo; sem archived_at; exclusão apaga a linha; `pending` não entra no gasto do planejamento; apagar uma pendente gerada grava `generated_skips` e a geração não a recria |
| saved_filters | user_id, name (até 40), filters (json) | no máximo 20 por usuário; nome único por usuário sem diferenciar maiúsculas nem acentos, validado no serviço; o json só guarda chaves de filtro e ordenação que o servidor aceita, e o período só quando o usuário marca para guardar |
| tags | user_id, name | |
| tag_transaction | tag_id, transaction_id | |
| monthly_plans | user_id, month (dia 1), expected_income (>= 0, padrão 0), status (draft, confirmed), notes? (até 500) | único por (user_id, month) |
| monthly_plan_items | user_id, monthly_plan_id, category_id, amount (>= 0) | único por (monthly_plan_id, category_id); o plano apagado remove os itens; só categorias de despesa |
| fixed_expenses | user_id, name (até 120), category_id, amount (> 0), due_day (1 a 31), start_month (dia 1), end_month? (dia 1), bank_id, payment_method_id, card_id?, notes? (até 500), archived_at? | categoria de gasto ativa; banco e forma obrigatórios; o mês atual gera a pendente ao criar, editar ou arquivar, e também na entrada do mês; meses futuros só quando o plano está confirmado |
| installments | user_id, name (até 120), category_id, total_amount (> 0), installments_count (2 a 120), first_date, bank_id, payment_method_id, card_id?, notes? (até 500), archived_at? | a parcela n cai no mês da primeira data mais n − 1 meses; o resto dos centavos fica na última; a geração segue a mesma regra híbrida dos fixos |
| generated_skips | user_id, fixed_expense_id?, installment_id?, installment_number?, generated_for (dia 1) | exatamente um dos dois vínculos; únicos parciais como em `transactions`; apagar o fixo ou o parcelado apaga os skips em cascata; a geração (automática e na confirmação) não recria o mês pulado |
| card_invoice_payments | user_id, card_id, closing_month (dia 1), paid_on | único por (card_id, closing_month); o valor da fatura não é guardado |
| goals | user_id, name, target_amount, start_date?, deadline, category_id?, note?, archived_at? | valor atual = soma dos lançamentos |
| goal_entries | goal_id, type (deposit, adjustment), amount (com sinal), date, note? | |
| net_worth_closings | user_id, reference_month, note? | único por (user_id, reference_month) |
| net_worth_closing_items | net_worth_closing_id, bank_id, balance | saldo digitado no fechamento |
| saved_reports | user_id, name, period (json), included_data (json), filters? (json), grouping? | |

Campos com `?` são opcionais.

## Valores derivados (nunca guardados)

Gasto do mês por categoria, porcentagem usada, o que resta, totais do planejamento (renda, planejado, sobra a alocar, comprometido e o comprometido de cada categoria), faturas dos cartões, valor atual das metas e total do patrimônio. Todos calculados no backend, em Services, a partir das tabelas acima. O comprometido de cada categoria soma os fixos ativos naquele mês e a parcela que cai nele; não é uma coluna gravada. O planejamento fica em `PlanningService`, `SpendingService`, `FixedExpenseService`, `InstallmentService`, `InstallmentCalculator` e `PlanGenerator`.

A fatura não é uma tabela. `CardInvoiceCalculator` (sem consulta ao banco) define o fechamento, o ciclo, o vencimento e a situação. `CardInvoiceService` soma as despesas do cartão com `Money` (bcmath): Paga entra em Lançado e Pendente em Previsto. Receita com cartão não entra. A página lê as despesas do cartão numa consulta e os pagamentos noutra; a lista da fatura aberta é uma terceira consulta, com categoria, banco e forma de pagamento. O resumo não faz uma consulta por fatura.

## Pendentes do mês atual

`PlanGenerator` gera o mês atual mesmo sem plano confirmado. Meses posteriores ao atual continuam dependendo da confirmação. Meses passados não são gerados por uma mudança de cadastro. Criar, editar ou arquivar um fixo ou parcelado chama `syncFixed` ou `syncInstallment`. O middleware `SyncCurrentPendings`, no grupo autenticado, compara a chave de sessão `pendings_synced_month` com o mês atual (America/Sao_Paulo) e, se for diferente, chama `generateMonth` do mês atual. Uma falha é registrada no log e a página abre mesmo assim; a chave só é gravada quando a geração termina. Essa geração não cria linha em `monthly_plans`.

Uma transação gerada e ainda Pendente, quando o usuário a exclui, ganha uma linha em `generated_skips`. A geração seguinte não a recria. Pago não é alterado. Pendente cujo valor foi ajustado à mão permanece com esse valor.

## Mês de trabalho e filtros da Análise

A sessão do login guarda duas preferências. O logout chama `session()->invalidate()`, então as duas somem no login seguinte.

- `working_month` (`YYYY-MM`), lida e gravada por `App\Support\WorkingMonth`. O Planejamento usa o mês da URL; sem `month`, usa o da sessão; sem sessão, o mês atual (America/Sao_Paulo). Um mês válido na URL substitui o guardado. O Dashboard ainda não filtra período: no módulo 8 ele lê o mesmo helper. Esta rodada não cria filtro no Dashboard.
- `analysis_query`, a última query de filtros da Análise (sem `page`). Abrir `/analysis` sem parâmetros redireciona para essa query. Aplicar ou mudar um filtro grava de novo e substitui o anterior. `POST /analysis/clear` apaga a query e volta ao padrão (mês atual). Uma consulta inválida também apaga a query, para o redirecionamento não entrar em ciclo.