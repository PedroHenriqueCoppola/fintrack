# Mapa de rotas

As páginas internas exigem login. Quem não está logado é enviado para `/login`. Quem já está logado e abre `/login` ou `/register` é enviado para `/`. Cada página devolve uma tela Inertia, exceto `/up`. Criar, editar, arquivar e desarquivar em Gerenciar voltam para a página Gerenciar; se a validação falha, os erros vêm em português. Um id de outro usuário responde 404.

| Método | Caminho | Nome | Quem acessa | O que devolve |
| --- | --- | --- | --- | --- |
| GET | `/login` | `login` | Visitante | Página Entrar. Se o login desta sessão está bloqueado, inclui os segundos que faltam. Usuário logado vai para `/` |
| POST | `/login` | — | Visitante | Entra na conta e vai para a página que tentou abrir, ou para `/` |
| GET | `/register` | `register` | Visitante | Página Criar conta. Usuário logado vai para `/` |
| POST | `/register` | — | Visitante | Cria a conta, entra logado e vai para `/` |
| POST | `/logout` | `logout` | Logado | Encerra a sessão e vai para `/login` |
| GET | `/` | `dashboard` | Logado | Página Dashboard, com título, subtítulo e estado vazio |
| GET | `/analysis` | `analysis` | Logado | Página Análise, em construção |
| GET | `/planning` | `planning` | Logado | Página Planejamento, em construção |
| GET | `/goals` | `goals` | Logado | Página Metas, em construção |
| GET | `/net-worth` | `net-worth` | Logado | Página Patrimônio, em construção |
| GET | `/reports` | `reports` | Logado | Página Relatórios, em construção |
| GET | `/manage` | `manage` | Logado | Página Gerenciar, com bancos, caixinhas, categorias, cartões e formas de pagamento (ativos e arquivados) e as opções de cor, ícone, tipo e natureza |
| POST | `/manage/banks` | `banks.store` | Logado | Cria um banco e volta para Gerenciar. Erros de validação em português |
| PUT | `/manage/banks/{bank}` | `banks.update` | Logado | Atualiza o banco do usuário. Id de outro usuário responde 404 |
| POST | `/manage/banks/{bank}/archive` | `banks.archive` | Logado | Arquiva o banco. Se ainda houver caixinha ou cartão ativo, devolve o erro "Arquive antes as caixinhas e cartões deste banco." Id de outro usuário responde 404 |
| POST | `/manage/banks/{bank}/restore` | `banks.restore` | Logado | Desarquiva o banco. Nome repetido entre os ativos devolve erro de validação. Id de outro usuário responde 404 |
| POST | `/manage/pockets` | `pockets.store` | Logado | Cria uma caixinha ligada a um banco ativo do usuário. Banco de outro usuário ou arquivado devolve erro de validação |
| PUT | `/manage/pockets/{pocket}` | `pockets.update` | Logado | Atualiza a caixinha do usuário. Id de outro usuário responde 404 |
| POST | `/manage/pockets/{pocket}/archive` | `pockets.archive` | Logado | Arquiva a caixinha. Id de outro usuário responde 404 |
| POST | `/manage/pockets/{pocket}/restore` | `pockets.restore` | Logado | Desarquiva a caixinha. Banco arquivado ou nome repetido no mesmo banco devolve erro de validação. Id de outro usuário responde 404 |
| POST | `/manage/categories` | `categories.store` | Logado | Cria uma categoria. Receita recusa natureza e saldo inicial. Despesa grava o saldo e o primeiro dia do mês atual |
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
