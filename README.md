# Fintrack

Sistema web de finanças pessoais: transações, orçamento do mês por categoria, planejamento, metas, patrimônio e relatórios. As regras de negócio estão em `docs/product-vision.md`.

## Funcionalidades

O visual novo segue o design system: superfícies em camadas, cards com sombra suave e a fonte Plus Jakarta Sans. Não há header. A sidebar flutuante traz o nome Fintrack, os oito links (Dashboard, Análise, Faturas, Planejamento, Metas, Patrimônio, Relatórios e Gerenciar) e o botão Recolher. No topo da área de conteúdo ficam o botão de tema e o ícone de perfil. O tema escuro é o padrão. O botão de tema alterna para o tema claro e guarda a escolha neste navegador.

Nesta etapa dá para entrar, criar conta, cadastrar em Gerenciar, lançar transações, planejar o mês, acompanhar a fatura de cada cartão e fechar o patrimônio do mês. O botão Nova transação, no Dashboard e na Análise, abre o modal. A Análise lista os lançamentos, com filtros na URL, filtros salvos e o resumo do período. Faturas mostra o ciclo do cartão, o que já foi lançado e o que ainda está previsto. Patrimônio guarda o saldo de cada banco no fechamento do mês. O corpo do Dashboard, Metas e Relatórios continua em construção. As páginas internas exigem uma conta. Ainda não há usuário de demonstração.

## Como subir

Na pasta do projeto:

```bash
docker compose up -d
```

Na primeira vez o PHP instala as dependências, gera a chave da aplicação e roda as migrações. O Vite instala os pacotes do front. Isso pode levar alguns minutos. Acompanhe com:

```bash
docker compose logs -f app vite
```

O app fica pronto quando o log do `app` mostrar o servidor em `0.0.0.0:8000` e o `vite` mostrar o endereço local.

## Endereços

| Serviço | Endereço |
| --- | --- |
| Aplicação | http://localhost:8000 |
| Vite (assets do desenvolvimento) | http://localhost:5173 |
| pgAdmin | http://localhost:5050 |

Abra a aplicação em http://localhost:8000. Sem uma conta, a tela de entrada abre em http://localhost:8000/login. O Vite só entrega os arquivos do front; a navegação fica na porta 8000.

pgAdmin, ambiente local:

- E-mail: `admin@example.com`
- Senha: `fintrack`

O servidor **Fintrack** já aparece na lista. Na primeira conexão o pgAdmin pede a senha do banco: `fintrack` (usuário `fintrack`, banco `fintrack`).

Essas senhas são só de desenvolvimento.

## Primeiro usuário

Não há usuário pronto. Crie o primeiro pela tela de cadastro:

1. Abra http://localhost:8000/register.
2. Preencha nome, e-mail e senha (mínimo de 8 caracteres) e confirme a senha.
3. Ao enviar, a conta é criada, já com as categorias e as formas de pagamento iniciais, e você entra no Dashboard.

Para entrar de novo, use http://localhost:8000/login. A mesma tela troca entre **Entrar** e **Criar conta**. A caixa **Manter conectado por 30 dias** mantém a sessão. O ícone de perfil, no topo da área de conteúdo, abre um menu com o nome e a opção **Sair**, que encerra a sessão.

## Gerenciar

A página http://localhost:8000/manage reúne os cadastros: categorias, bancos, cartões e formas de pagamento. O botão de criar fica ao lado do título e muda conforme a aba. Criar e editar acontecem em um modal. A busca por nome ignora acentos. Arquivar tira o item da lista ativa; nada é apagado. O botão **Mostrar arquivados** revela os itens arquivados, com a ação **Desarquivar**. As categorias ficam em dois grupos: **Despesas** e **Reservas e receitas**. Um banco que ainda tem cartão ativo não arquiva: a tela pede para arquivar o cartão antes.

Cada conta nova recebe categorias e formas de pagamento iniciais:

- Gasto: Mercado, Moradia, Transporte, Saúde, Lazer, Outros
- Reserva: Reserva de emergência, Investimentos
- Receita: Salário, Outras receitas
- Formas de pagamento: Pix, Débito, Crédito (só Crédito exige cartão) e Transferência. Contas já criadas não recebem Transferência sozinhas: o cadastro fica em Gerenciar.

O escopo `BelongsToUser` filtra toda consulta pelo usuário logado e preenche `user_id` ao criar. Numa requisição web sem usuário autenticado, a consulta não devolve registros. No tinker, nos comandos do Artisan e nos jobs não há usuário autenticado: o escopo não filtra e não gera erro. Por isso o comando abaixo precisa indicar a conta.

Para aplicar os padrões a um usuário que já existia:

```bash
docker compose exec app php artisan tinker --execute='app(App\Services\DefaultDataService::class)->createFor(App\Models\User::query()->where("email", "seu@email")->firstOrFail());'
```

Troque `seu@email` pelo e-mail da conta. Rodar de novo não duplica. Para todas as contas de uma vez:

```bash
docker compose exec app php artisan tinker --execute='App\Models\User::query()->each(function ($user) { app(App\Services\DefaultDataService::class)->createFor($user); echo $user->email, PHP_EOL; });'
```

## Transações e Análise

O modal registra despesa ou receita, paga ou pendente, com valor, data, descrição, categoria, banco e forma de pagamento. A transação Paga não pode ter data depois de hoje. A Pendente pode, até 5 anos à frente, também no lançamento manual. A data de competência pode. Se a forma exige cartão, o cartão é obrigatório e o banco fica travado no banco do cartão. Data de competência e observação ficam em Mais opções. Duplicar abre uma cópia com a data de hoje e o status Paga. Excluir uma transação ou um filtro salvo pede confirmação num modal do sistema e, no caso da transação, apaga a linha.

Na lista, um lançamento gerado por gasto fixo ou parcela aparece com a marca Fixo ou Parcela n/total. Uma pendente pode ter data futura. Ao marcá-la como paga, a data futura vira hoje; a data passada também vira hoje quando não há cartão, e permanece quando há cartão, porque ela decide a fatura. Se a data mudou e a competência estava vazia, ela guarda o dia original. Quando o mês da competência é outro, a linha mostra o selo "Ref. dez/2026". Excluir uma transação gerada, paga ou pendente, impede o sistema de recriá-la naquele mês; o aviso dessa exclusão fica em negrito.

A página http://localhost:8000/analysis agrupa por dia no período escolhido (mês atual é o padrão; o intervalo é o do calendário, no fuso de São Paulo). O último filtro fica na sessão do login e volta ao abrir a página sem parâmetros. **Limpar filtros** esquece esse filtro e volta ao mês atual. O resumo — quantidade, receitas, despesas, saldo e média por despesa — é calculado no servidor sobre o filtro inteiro. Transação pendente entra na quantidade e fica de fora do dinheiro. A lista carrega de 50 em 50. Os filtros ficam na URL, então o endereço pode ser aberto em outra aba. Dá para salvar até 20 filtros por conta e apagar cada um. Aplicar um filtro salvo substitui o filtro guardado na sessão. A busca ignora maiúsculas e acentos. Se o período for um único mês futuro, com fixos ou parcelas previstos e o planejamento ainda não confirmado, a lista avisa quantos lançamentos estão previstos e leva ao Planejamento.

Tag ainda não existe.

## Planejamento

A página http://localhost:8000/planning trabalha com um mês por vez (passado, atual ou futuro). O mês escolhido fica na sessão enquanto o login durar; sem mês guardado, abre o mês atual no fuso de São Paulo. Sair da conta esquece esse mês. A aba **Planejar o mês** mostra a renda esperada, o valor de cada categoria de despesa, o gasto pago do mês, quanto os fixos e as parcelas daquele mês já comprometem, a porcentagem usada e o que resta. Reserva só tem o valor a guardar. A sobra a alocar é a renda menos a soma dos valores; pode ficar negativa. O mês nasce como cópia do plano anterior, mas só é gravado no primeiro salvamento. Confirmar gera as despesas pendentes dos fixos e das parcelas daquele mês. Voltar para rascunho não apaga o que já foi gerado.

A aba **Fixos e parcelados** cadastra o gasto que se repete e o parcelado, e lista o que está ativo no mês escolhido. Se o Pendente daquele mês foi pago num mês anterior, a linha mostra "Pago antecipado em dd/mm". Se foi pago num mês posterior, mostra "Pago em dd/mm". O valor entra em "Já quitado no mês" do mês de referência. O que começa depois ou já terminou fica no grupo **Fora deste mês**. O valor de cada parcela é calculado no servidor: o total dividido pelo número de parcelas, arredondado para baixo, e o resto fica na última. Um fixo ou parcela que passa da porcentagem configurada (30% no início) recebe a marca **Alto impacto**.

O mês atual recebe as pendentes dos fixos e das parcelas sozinho: ao criar, editar ou arquivar o cadastro, e também na primeira visita autenticada daquele mês. Meses futuros só geram quando o planejamento daquele mês é confirmado. Meses passados não ganham pendente por essa geração. Ela não grava o plano do mês. Uma transação gerada que o usuário excluiu, paga ou pendente, não volta. Se a geração automática falhar, a página abre e a próxima tentativa espera 5 minutos.

## Faturas

A página http://localhost:8000/invoices lista os cartões ativos. A fatura leva o nome do mês em que fecha. A compra feita no dia do fechamento entra na fatura seguinte. **Lançado** são as despesas pagas do ciclo; **Previsto**, as pendentes, inclusive a pendente manual com data futura. Quando a competência cai em outro mês, a linha mostra o selo "Ref. dez/2026". O total estimado é a soma dos dois, calculada no servidor. Dá para ver as faturas anteriores e, depois do fechamento, marcar a fatura como paga (e desmarcar). A marca só guarda a data: não cria despesa e não muda as transações. No modal de transação, com cartão e data escolhidos, uma dica diz em qual fatura a compra entra.

## Patrimônio

A página http://localhost:8000/net-worth mostra o que está guardado em cada banco. O fechamento é mensal: o botão **Fechar novembro** (o mês atual) abre o modal com o saldo do fechamento anterior já preenchido. Se o mês atual já foi fechado, o botão vira **Editar fechamento de novembro**. Dá para escolher qualquer mês até o atual, um por mês. O total e a variação são calculados no servidor. A variação compara com o fechamento anterior, mesmo que um mês tenha sido pulado. O filtro de período (padrão: ano) muda o gráfico e a lista; o patrimônio atual e o bloco por banco continuam sendo os do último fechamento. Excluir um fechamento tira só aquele registro.

## Proteção do login

- 5 falhas seguidas do mesmo e-mail e do mesmo IP bloqueiam o login por 5 minutos, contados a partir da 5ª falha. Durante o bloqueio, nem a senha correta entra.
- 20 falhas do mesmo IP em 15 minutos bloqueiam o login por mais 15 minutos, mesmo que o e-mail mude a cada tentativa.
- O cadastro aceita no máximo 10 tentativas por hora por IP.

## Comandos úteis

```bash
docker compose logs -f
docker compose stop
docker compose down
docker compose up -d
docker compose restart app
docker compose exec app php artisan migrate
docker compose exec app bash
```

`docker compose down` para os containers e **mantém** o volume do banco. Não use `down -v` se quiser conservar os dados.

## Quando mudar dependências

Pacote novo ou atualizado no `composer.json` (PHP):

```bash
docker compose exec app composer install
```

Pacote novo ou atualizado no `package.json` (JavaScript):

```bash
docker compose exec vite npm install
```

## Backup manual do banco

A porta do PostgreSQL não fica exposta. O backup sai de dentro do container:

```bash
docker compose exec -T db pg_dump -U fintrack -d fintrack > backup-fintrack.sql
```

O arquivo `backup-fintrack.sql` é criado na pasta atual.
