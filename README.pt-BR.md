[English](README.md)

<img src="public/brand/fintrack-mark.svg" alt="Símbolo do Fintrack: quadrado roxo arredondado com um cartão branco e três barras crescentes" width="64" height="64">

# Fintrack

Um aplicativo web de finanças pessoais para o uso do dia a dia: transações, faturas de cartão, planejamento do mês, metas, patrimônio e relatórios.

<img src="docs/screenshots/dark/01-dashboard.png" alt="Dashboard escuro do mês atual: saldo do período, receitas, despesas, valor guardado, uso do planejado por categoria e a fatura aberta de cada cartão" width="1440">

## Funcionalidades

**Dashboard.** O período num relance: o que entrou, o que saiu, o que sobrou e quanto desse saldo foi guardado. O mês mostra o gasto contra o planejado, a fatura aberta de cada cartão e as últimas transações.

**Análise.** As transações agrupadas por dia, com os totais do período. Os filtros de texto, categoria, banco, forma de pagamento, tags e valor ficam no endereço, e um filtro pode ser salvo para abrir de novo.

**Faturas.** Cada cartão tem o seu dia de fechamento. O que já foi lançado e o que ainda está previsto ficam na fatura com o nome do mês em que ela fecha. Uma fatura fechada pode ser marcada como paga; essa marca não cria uma despesa nova.

**Planejamento.** Informe a renda esperada e um valor para cada categoria. O mês mostra o que foi gasto, o que já está comprometido e o que ainda falta alocar. Gastos fixos e parcelados geram as próprias pendências.

**Metas e reservas.** Uma meta acompanha uma categoria de Reserva. Guardar e resgatar movem esse saldo, e um resgate não pode deixá-lo negativo. Uma meta concluída permanece concluída, e uma meta posterior na mesma categoria começa na data dela.

**Patrimônio.** Uma vez por mês, registre o saldo de cada conta e de cada investimento. O total e a variação desde o fechamento anterior são calculados quando a página abre.

**Relatórios.** Escolha um modelo pronto ou monte o seu na página, leia a prévia e baixe os mesmos números em PDF ou Excel. O arquivo não fica gravado.

**Gerenciar.** Categorias, bancos, cartões, formas de pagamento e tags ficam na mesma página. Um item que já está em uso é arquivado, em vez de apagado.

## Capturas de tela

<table>
<tr>
<td width="50%"><strong>Escuro</strong></td>
<td width="50%"><strong>Claro</strong></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/01-dashboard.png" alt="Dashboard escuro com o saldo do período, o uso do planejado e as faturas abertas" width="640"></td>
<td><img src="docs/screenshots/light/01-dashboard.png" alt="Dashboard claro com o mesmo saldo do período, o uso do planejado e as faturas abertas" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/02-analysis.png" alt="Análise escura do mês atual, com filtros salvos e transações agrupadas por dia" width="640"></td>
<td><img src="docs/screenshots/light/02-analysis.png" alt="Análise clara do mês atual, com filtros salvos e transações agrupadas por dia" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/03-invoices.png" alt="Fatura escura do Itaú Click, com compras lançadas e previstas e faturas pagas abaixo" width="640"></td>
<td><img src="docs/screenshots/light/03-invoices.png" alt="Fatura clara do Itaú Click, com compras lançadas e previstas e faturas pagas abaixo" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/04-planning.png" alt="Planejamento escuro do mês, com renda esperada, valores por categoria e nada a alocar" width="640"></td>
<td><img src="docs/screenshots/light/04-planning.png" alt="Planejamento claro do mês, com renda esperada, valores por categoria e nada a alocar" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/06-goals.png" alt="Metas no tema escuro, com duas metas ativas, uma concluída e o gráfico da reserva" width="640"></td>
<td><img src="docs/screenshots/light/06-goals.png" alt="Metas no tema claro, com duas metas ativas, uma concluída e o gráfico da reserva" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/08-net-worth.png" alt="Patrimônio escuro em 42.000, com o gráfico do ano e o último fechamento por conta" width="640"></td>
<td><img src="docs/screenshots/light/08-net-worth.png" alt="Patrimônio claro em 42.000, com o gráfico do ano e o último fechamento por conta" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/09-reports.png" alt="Relatório escuro de gastos por categoria no ano, com botões de PDF e Excel" width="640"></td>
<td><img src="docs/screenshots/light/09-reports.png" alt="Relatório claro de gastos por categoria no ano, com botões de PDF e Excel" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/11-new-transaction.png" alt="Modal escuro de nova transação preenchido como despesa paga de supermercado, ainda sem salvar" width="640"></td>
<td><img src="docs/screenshots/light/11-new-transaction.png" alt="Modal claro de nova transação preenchido como despesa paga de supermercado, ainda sem salvar" width="640"></td>
</tr>
<tr>
<td colspan="2"><img src="docs/screenshots/dark/14-dashboard-tablet.png" alt="Dashboard escuro na largura de tablet, com a sidebar recolhida em ícones" width="640"><br>Tablet, tema escuro, 1024 × 768.</td>
</tr>
</table>

## Stack

As versões abaixo são as instaladas neste repositório.

| Peça | Versão | Fonte |
| --- | --- | --- |
| PHP | 8.5.12 | `docker/php/Dockerfile` |
| Laravel | 13.42.0 | `composer.lock` (`laravel/framework`) |
| Inertia (Laravel) | 3.7.0 | `composer.lock` (`inertiajs/inertia-laravel`) |
| Inertia (React) | 3.11.0 | `package-lock.json` |
| React | 19.3.1 | `package-lock.json` |
| Vite | 8.3.8 | `package-lock.json` |
| PostgreSQL | 18.6 | `docker-compose.yml` (`postgres:18.6-bookworm`) |
| Node | 26.13.1 | `docker-compose.yml` (`node:26.13.1-bookworm`) |
| Recharts | 3.11.0 | `package-lock.json` |
| Dompdf | 3.1.2 (pacote), 3.1.6 (biblioteca) | `composer.lock` (`barryvdh/laravel-dompdf`, `dompdf/dompdf`) |
| OpenSpout | 5.15.3 | `composer.lock` |
| Playwright | 1.65.1 | `package-lock.json` |
| Composer | 2.11.2 | `docker/php/Dockerfile` |
| pgAdmin | 9.19.0 | `docker-compose.yml` |

A interface é Inertia com React (JSX, sem TypeScript) e CSS puro. Os ícones são do Lucide (`lucide-react` 0.554.0). A fonte é Plus Jakarta Sans.

## Como começar

Docker Desktop no Windows, com o projeto no sistema de arquivos do WSL. Não é preciso ter PHP, Composer, Node ou PostgreSQL na máquina: todo comando passa pelo Docker Compose.

```bash
docker compose up -d
```

Na primeira vez o PHP instala as dependências, gera a chave da aplicação e roda as migrações. O container do Vite instala os pacotes do front. Isso pode levar alguns minutos:

```bash
docker compose logs -f app vite
```

O app fica pronto quando o `app` estiver escutando em `0.0.0.0:8000` e o Vite mostrar o endereço local.

| Serviço | Endereço |
| --- | --- |
| Aplicação | http://localhost:8000 |
| Vite (assets de desenvolvimento) | http://localhost:5173 |
| pgAdmin | http://localhost:5050 |

Abra http://localhost:8000. Sem uma conta, a tela de entrada fica em http://localhost:8000/login. O Vite só entrega os arquivos do front; a navegação fica na porta 8000.

pgAdmin, só no ambiente local:

- E-mail: `admin@example.com`
- Senha: `fintrack`

O servidor **Fintrack** já aparece na lista. Na primeira conexão o pgAdmin pede a senha do banco: `fintrack` (usuário `fintrack`, banco `fintrack`). Essas senhas são só de desenvolvimento.

### Conta demo

O comando recria só a conta demo. Os outros usuários ficam como estão. Em produção ele se recusa a rodar sem `--force`.

```bash
docker compose exec app php artisan fintrack:demo
```

- E-mail: `demo@fintrack.app`
- Senha: `fintrack-demo`
- Nome: Ana Demo

Os dados cobrem pouco mais de um ano, com datas a partir de hoje em `America/Sao_Paulo`. Duas execuções no mesmo dia geram a mesma conta.

Uma conta nova também pode ser criada em http://localhost:8000/register. Ela já nasce com as categorias e as formas de pagamento iniciais.

### Capturas de tela

O script entra pela tela de login. `APP_URL` tem o padrão `http://localhost:8000`.

Na máquina, quando esse endereço for o app:

```bash
npx playwright install chromium
npm run screenshots
```

Neste repositório o Node fica no container do Vite. O Chromium também precisa das bibliotecas do sistema, uma vez por container:

```bash
docker compose exec vite npx playwright install chromium
docker compose exec -u root vite npx playwright install-deps chromium
docker compose exec vite npm run screenshots
```

As imagens vão para `docs/screenshots/dark` e `docs/screenshots/light`. Se a conta demo não existir, o script para com a mensagem `Rode php artisan fintrack:demo antes`.

### Comandos úteis

```bash
docker compose logs -f
docker compose stop
docker compose down
docker compose up -d
docker compose restart app
docker compose exec app php artisan migrate
docker compose exec app bash
```

`docker compose down` para os containers e mantém o volume do banco. Não use `down -v` se quiser conservar os dados.

Depois de mudar o `composer.json`:

```bash
docker compose exec app composer install
```

Depois de mudar o `package.json`:

```bash
docker compose exec vite npm install
```

A porta do PostgreSQL não fica exposta. O backup manual sai de dentro do container:

```bash
docker compose exec -T db pg_dump -U fintrack -d fintrack > backup-fintrack.sql
```

## Regras de negócio que vale conhecer

O dinheiro é `DECIMAL(15, 2)` e a conta usa bcmath. Nada derivado fica gravado: totais de fatura, saldo de Reserva, progresso da meta e totais de patrimônio são calculados a partir das linhas que foram salvas.

A fatura segue o dia de fechamento do cartão. A compra feita no dia do fechamento entra na fatura seguinte. Lançado é a despesa paga daquele ciclo. Previsto é a pendente.

Gastos fixos e parcelados geram transações pendentes. O mês atual é gerado pelo sistema. Um mês futuro só é gerado quando o plano daquele mês é confirmado.

Reserva é um terceiro tipo de transação, com guardar e resgatar. O saldo de uma categoria de Reserva nunca pode ficar negativo.

Uma meta pertence a uma categoria de Reserva. A primeira meta conta todo o histórico. Uma meta seguinte começa na data dela, e concluir uma meta a congela para a próxima poder começar.

O patrimônio é um fechamento mensal: um saldo por conta e por investimento. Os relatórios mostram a prévia, o PDF e o Excel a partir da mesma estrutura, e nenhum dos três fica salvo.

## Arquitetura

Os controllers ficam finos. Form Requests validam a entrada, Services aplicam as regras e Resources montam o que cada página Inertia recebe. Não há uma API JSON separada. Um escopo global prende cada consulta do usuário ao usuário logado e preenche `user_id` na criação. No console, onde ninguém está logado, esse escopo não filtra.

Mais detalhes: [arquitetura](docs/architecture.md), [rotas](docs/route-map.md), [regras de produto](docs/product-vision.md) e o [design system](docs/design-system.md).

## Como foi construído com agentes de IA

O autor foi o dono do produto: as decisões, os roteiros de teste manuais e o merge de cada módulo. O Claude orquestrou: as perguntas de decisão, a especificação de cada módulo em um prompt, a revisão de código de cada commit e os roteiros de teste. O agente do Cursor fez a implementação.

Cada módulo seguiu o mesmo ciclo: perguntas de decisão, depois um prompt com a especificação e os documentos atualizados, depois a implementação com autorrevisão obrigatória, a revisão de código, as rodadas de correção, os testes manuais e o merge.

Os arquivos que guiaram os agentes são `docs/product-vision.md`, `docs/design-system.md` e `.cursor/rules/`.

`git rev-list --count HEAD` informa 59 commits. `git log --grep='Merge pull request' --oneline` lista 12 pull requests mergeados. Esses pull requests trouxeram as doze branches de `feat/module-02-auth` a `feat/module-11b-polish`, todas contidas na `main`.

A especificação escrita manteve os módulos consistentes. O ciclo da fatura e o recomeço das metas pediram mais rodadas de correção do que as telas mais simples.

## Publicação

Antes de apontar um endereço público para o app:

- Defina `APP_ENV=production`, `APP_DEBUG=false` e `APP_URL` com o endereço `https`.
- Defina `SESSION_SECURE_COOKIE=true`.
- Defina `REGISTRATION_ENABLED` como `true` só enquanto o cadastro aberto deve existir. Com `false`, `/register` responde 404 e a tela de entrada esconde "Criar conta".
- Troque todas as senhas padrão: a do banco e a do pgAdmin. Não publique o pgAdmin.
- Rode `php artisan migrate --force`, depois `php artisan config:cache`, `php artisan route:cache` e `php artisan view:cache`.
- Rode `npm run build`.

`php artisan fintrack:demo --force` funciona em produção e recria a conta demo. Essa conta fica pública com a senha impressa neste README. Não rode o comando num servidor público, a menos que isso seja de propósito.

## Limitações e próximos passos

Não há suíte de testes automatizados. Os testes foram roteiros manuais. Não há um layout de celular dedicado. Não há backup automático. As contas de investimento são saldos registrados em cada fechamento, sem rentabilidade. Não há recuperação de senha por e-mail.


## 🔗 Links

[![linkedin](https://img.shields.io/badge/linkedin-0A66C2?style=for-the-badge&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/pedro-henrique-coppola-071baa225/) [![instagram](https://img.shields.io/badge/Instagram-E4405F?style=for-the-badge&logo=instagram&logoColor=white)](https://www.instagram.com/pedrocoppola_/)

## Licença

Todos os direitos reservados.
