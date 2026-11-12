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
│   └── Services/          regras de negócio (saldo, planejamento, faturas, ...)
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
- Registros com histórico usam `archived_at` (nulo = ativo). Nunca apagar de verdade.
- Todas as tabelas têm `id`, `created_at` e `updated_at`, além dos campos listados.

## Modelo do banco

As tabelas padrão do Laravel (`sessions`, `cache`, `jobs`, `password_reset_tokens`) também existem.

| Tabela | Campos | Observações |
| --- | --- | --- |
| users | name, email (único), password, remember_token | login por sessão |
| banks | user_id, name, color?, archived_at? | |
| pockets (caixinhas) | user_id, bank_id, name, type? (balance, piggy_bank, investment), color?, archived_at? | só organiza; o saldo não é calculado |
| categories | user_id, name, applies_to (expense, income), nature? (spending, reserve; só para expense), icon?, color?, pocket_id?, initial_balance (padrão 0), initial_month?, archived_at? | saldo disponível é derivado, nunca guardado |
| cards | user_id, bank_id, name, closing_day, due_day?, color?, archived_at? | faturas são derivadas das transações |
| payment_methods | user_id, name, requires_card (bool), archived_at? | |
| transactions | user_id, type (income, expense), date, amount (positivo), description, category_id, bank_id, payment_method_id, card_id?, pocket_id?, note?, status (paid, pending), competence_month, fixed_expense_id?, installment_plan_id?, installment_number?, installment_total?, archived_at? | índices em (user_id, competence_month) e (user_id, date); `pending` não entra nos totais |
| tags | user_id, name | |
| tag_transaction | tag_id, transaction_id | |
| monthly_plans | user_id, month, expected_income, status (draft, confirmed), note? | único por (user_id, month) |
| monthly_plan_items | monthly_plan_id, category_id, amount | único por (monthly_plan_id, category_id) |
| reallocations | user_id, month, from_category_id, to_category_id, amount, note? | histórico de remanejamentos |
| fixed_expenses | user_id, name, category_id, amount, payment_method_id?, note?, start_month, end_month?, archived_at? | confirmar o planejamento gera transações `pending` |
| installment_plans | user_id, name, category_id, total_amount, installments_count, start_month, payment_method_id?, card_id?, note?, archived_at? | gera uma transação `pending` por mês |
| goals | user_id, name, target_amount, start_date?, deadline, category_id?, pocket_id?, note?, archived_at? | valor atual = soma dos lançamentos |
| goal_entries | goal_id, type (deposit, adjustment), amount (com sinal), date, note? | |
| net_worth_closings | user_id, reference_month, note? | único por (user_id, reference_month) |
| net_worth_closing_items | net_worth_closing_id, pocket_id, balance | saldo digitado no fechamento |
| saved_reports | user_id, name, period (json), included_data (json), filters? (json), grouping? | |

Campos com `?` são opcionais.

## Valores derivados (nunca guardados)

Saldo disponível da categoria no mês, totais do mês, faturas dos cartões, valor atual das metas e total do patrimônio. Todos calculados no backend, em Services, a partir das tabelas acima.