# Mapa de rotas

As páginas internas exigem login. Quem não está logado é enviado para `/login`. Quem já está logado e abre `/login` ou `/register` é enviado para `/`. Cada página devolve uma tela Inertia, exceto `/up`.

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
| GET | `/manage` | `manage` | Logado | Página Gerenciar, em construção |
| GET | `/styleguide` | `styleguide` | Logado | Página de verificação dos componentes |
| GET | `/up` | — | Público | Saúde da aplicação (resposta do Laravel, não é uma tela) |
