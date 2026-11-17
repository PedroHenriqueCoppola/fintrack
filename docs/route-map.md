# Mapa de rotas

As páginas internas exigem login. Quem não está logado é enviado para `/login`. Quem já está logado e abre `/login` ou `/register` é enviado para `/`. Cada página devolve uma tela Inertia, exceto `/up`. Criar, editar, arquivar e desarquivar em Gerenciar voltam para a página Gerenciar; se a validação falha, os erros vêm em português. Um id de outro usuário responde 404.

| Método | Caminho | Nome | Quem acessa | O que devolve |
| --- | --- | --- | --- | --- |
| GET | `/login` | `login` | Visitante | Página Entrar. Se o login desta sessão está bloqueado, inclui os segundos que faltam. Usuário logado vai para `/` |
| POST | `/login` | — | Visitante | Entra na conta e vai para a página que tentou abrir, ou para `/` |
| GET | `/register` | `register` | Visitante | Página Criar conta. Usuário logado vai para `/` |
| POST | `/register` | — | Visitante | Cria a conta, entra logado e vai para `/` |
| POST | `/logout` | `logout` | Logado | Encerra a sessão e vai para `/login` |
| GET | `/` | `dashboard` | Logado | Página Dashboard. O título é a saudação com o primeiro nome (hora do navegador), o subtítulo é o do dashboard vazio e o corpo ainda é o card "Em construção". O botão Nova transação abre o modal. A página recebe as opções do formulário: categorias ativas (despesa só de gasto, e receita), bancos, cartões e formas de pagamento |
| GET | `/analysis` | `analysis` | Logado | Página Análise, com a lista (50 por página), o resumo do filtro inteiro e as mesmas opções do modal. Parâmetros opcionais na URL: `period` (`current_month` padrão, `previous_month`, `quarter`, `semester`, `year`, `all`, `custom`), `from` e `to` no personalizado, `type`, `status`, `category_id`, `bank_id`, `card_id`, `payment_method_id`, `min_amount`, `max_amount`, `q`, `sort` (`date_desc` padrão, `date_asc`, `amount_desc`, `amount_asc`) e `page`. Ids precisam ser da conta. Consulta inválida volta para `/analysis` com o erro. Pendente entra na quantidade e fica de fora dos totais de dinheiro. A página seguinte é anexada à lista já carregada |
| GET | `/planning` | `planning` | Logado | Página Planejamento. `month` opcional (`YYYY-MM`); sem mês ou com mês inválido usa o mês atual (fuso America/Sao_Paulo). `tab` opcional (`plan` ou `fixed`). Na aba de fixos, filtros opcionais: `category_id`, `min_amount`, `max_amount` e `sort` (`name`, `amount` ou `due`). Devolve o plano (ou a cópia ainda não gravada do mês anterior), o gasto pago por categoria, o resumo, os fixos, os parcelados e as opções dos modais. Nada é gravado neste GET |
| GET | `/goals` | `goals` | Logado | Página Metas, em construção |
| GET | `/net-worth` | `net-worth` | Logado | Página Patrimônio, em construção |
| GET | `/reports` | `reports` | Logado | Página Relatórios, em construção |
| POST | `/transactions` | `transactions.store` | Logado | Cria a transação e volta para a lista, sem o parâmetro `page`. Erros de validação em português. `user_id` da requisição não é aceito |
| PUT | `/transactions/{transaction}` | `transactions.update` | Logado | Atualiza a transação do usuário. A data não pode ser posterior a hoje, exceto numa pendente gerada por fixo ou parcela. Ao marcar como paga, a data volta a respeitar essa regra. Numa transação gerada de fixo, a opção de usar o valor daqui em diante atualiza o valor padrão e as pendentes futuras que ainda estavam no valor antigo. Id de outro usuário responde 404 |
| DELETE | `/transactions/{transaction}` | `transactions.destroy` | Logado | Apaga a transação. Id de outro usuário responde 404 |
| POST | `/transactions/{transaction}/pay` | `transactions.pay` | Logado | Marca a pendente como paga. Se a data for futura, a competência recebe essa data (quando estava vazia) e a data de pagamento vira hoje. Id de outro usuário responde 404 |
| POST | `/saved-filters` | `saved-filters.store` | Logado | Salva o filtro atual. Nome obrigatório, até 40 caracteres, único por usuário sem diferenciar maiúsculas nem acentos, no máximo 20 por usuário. O JSON só guarda chaves de filtro que o servidor valida |
| DELETE | `/saved-filters/{savedFilter}` | `saved-filters.destroy` | Logado | Apaga o filtro salvo. Id de outro usuário responde 404 |
| GET | `/manage` | `manage` | Logado | Página Gerenciar, com bancos, categorias, cartões e formas de pagamento (ativos e arquivados) e as opções de cor, ícone, tipo e natureza |
| POST | `/manage/banks` | `banks.store` | Logado | Cria um banco e volta para Gerenciar. Erros de validação em português |
| PUT | `/manage/banks/{bank}` | `banks.update` | Logado | Atualiza o banco do usuário. Id de outro usuário responde 404 |
| POST | `/manage/banks/{bank}/archive` | `banks.archive` | Logado | Arquiva o banco. Se ainda houver cartão ativo, devolve o erro "Arquive antes os cartões deste banco." Id de outro usuário responde 404 |
| POST | `/manage/banks/{bank}/restore` | `banks.restore` | Logado | Desarquiva o banco. Nome repetido entre os ativos devolve erro de validação. Id de outro usuário responde 404 |
| PUT | `/planning/settings` | `planning.settings` | Logado | Atualiza a porcentagem de alto impacto (inteiro de 1 a 100) e volta para o planejamento |
| PUT | `/planning/{month}` | `planning.update` | Logado | Grava a renda esperada e os valores do mês (`YYYY-MM`, de 2000-01 a 2100-12). Lista parcial mantém as categorias que não vieram. Valor zero permanece gravado. `user_id` é recusado. Categoria de outro usuário ou de receita é recusada. Volta para a mesma aba |
| POST | `/planning/{month}/copy` | `planning.copy` | Logado | Substitui a renda e os valores pelos do plano anterior mais recente. Mês inválido é recusado |
| POST | `/planning/{month}/confirm` | `planning.confirm` | Logado | Marca o mês como confirmado e gera as pendentes dos fixos e das parcelas, na mesma transação. Se o plano ainda não existia, cria com os valores copiados. Repetir não duplica |
| POST | `/planning/{month}/reopen` | `planning.reopen` | Logado | Volta o mês para rascunho. Não apaga transações já geradas |
| POST | `/planning/fixed-expenses` | `planning.fixed-expenses.store` | Logado | Cria um gasto fixo (categoria de gasto, banco e forma de pagamento; cartão se a forma exigir) e sincroniza os meses confirmados |
| PUT | `/planning/fixed-expenses/{fixedExpense}` | `planning.fixed-expenses.update` | Logado | Atualiza o fixo. Id de outro usuário responde 404. Pendentes acompanham; um valor ajustado à mão e um pago ficam como estão |
| POST | `/planning/fixed-expenses/{fixedExpense}/archive` | `planning.fixed-expenses.archive` | Logado | Arquiva o fixo e apaga só as pendentes de meses depois do mês atual |
| POST | `/planning/fixed-expenses/{fixedExpense}/restore` | `planning.fixed-expenses.restore` | Logado | Desarquiva o fixo e volta a gerar nos meses confirmados |
| POST | `/planning/installments/preview` | `planning.installments.preview` | Logado | Devolve a divisão do total em parcelas, com o resto na última. O navegador não calcula |
| POST | `/planning/installments` | `planning.installments.store` | Logado | Cria um parcelado e sincroniza os meses confirmados |
| PUT | `/planning/installments/{installment}` | `planning.installments.update` | Logado | Atualiza o parcelado. Depois da primeira parcela gerada, valor total, número de parcelas e data da primeira não mudam. Id de outro usuário responde 404 |
| POST | `/planning/installments/{installment}/archive` | `planning.installments.archive` | Logado | Arquiva o parcelado e apaga só as pendentes futuras |
| POST | `/planning/installments/{installment}/restore` | `planning.installments.restore` | Logado | Desarquiva o parcelado e volta a gerar nos meses confirmados |
| POST | `/manage/categories` | `categories.store` | Logado | Cria uma categoria. Receita recusa natureza. Despesa grava a natureza e o primeiro dia do mês atual |
| PUT | `/manage/categories/{category}` | `categories.update` | Logado | Atualiza a categoria. Tipo e natureza não podem ser enviados. Id de outro usuário responde 404 |
| POST | `/manage/categories/{category}/archive` | `categories.archive` | Logado | Arquiva a categoria. Id de outro usuário responde 404 |
| POST | `/manage/categories/{category}/restore` | `categories.restore` | Logado | Desarquiva a categoria. Nome repetido entre as ativas devolve erro de validação. Id de outro usuário responde 404 |
| POST | `/manage/cards` | `cards.store` | Logado | Cria um cartão ligado a um banco ativo do usuário, com dia de fechamento de 1 a 31 |
| PUT | `/manage/cards/{card}` | `cards.update` | Logado | Atualiza o cartão do usuário. Id de outro usuário responde 404 |
| POST | `/manage/cards/{card}/archive` | `cards.archive` | Logado | Arquiva o cartão. Id de outro usuário responde 404 |
| POST | `/manage/cards/{card}/restore` | `cards.restore` | Logado | Desarquiva o cartão. Banco arquivado ou nome repetido no mesmo banco devolve erro de validação. Id de outro usuário responde 404 |
| POST | `/manage/payment-methods` | `payment-methods.store` | Logado | Cria uma forma de pagamento |
| PUT | `/manage/payment-methods/{paymentMethod}` | `payment-methods.update` | Logado | Atualiza a forma de pagamento do usuário. Id de outro usuário responde 404 |
| POST | `/manage/payment-methods/{paymentMethod}/archive` | `payment-methods.archive` | Logado | Arquiva a forma de pagamento. Id de outro usuário responde 404 |
| POST | `/manage/payment-methods/{paymentMethod}/restore` | `payment-methods.restore` | Logado | Desarquiva a forma de pagamento. Nome repetido entre as ativas devolve erro de validação. Id de outro usuário responde 404 |
| GET | `/styleguide` | `styleguide` | Logado | Página de verificação dos componentes |
| GET | `/up` | — | Público | Saúde da aplicação (resposta do Laravel, não é uma tela) |
