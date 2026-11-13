# Fintrack

Sistema web de finanças pessoais: transações, orçamento por categoria com acúmulo, planejamento do mês, metas, patrimônio e relatórios. As regras de negócio estão em `docs/product-vision.md`.

Nesta etapa o projeto sobe com um comando, com tema claro/escuro, layout, componentes comuns, login, cadastro e a página Gerenciar (bancos, caixinhas, categorias, cartões e formas de pagamento). As páginas internas exigem uma conta. Ainda não há transações nem usuário de demonstração.

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

Para entrar de novo, use http://localhost:8000/login. O botão **Sair**, no topo, encerra a sessão.

## Gerenciar

A página http://localhost:8000/manage reúne os cadastros que organizam o resto do sistema: bancos, caixinhas, categorias, cartões e formas de pagamento. Criar e editar acontecem em um modal. Arquivar tira o item da lista ativa; nada é apagado. O botão **Mostrar arquivados** revela os itens arquivados, com a ação **Desarquivar**. Um banco que ainda tem caixinha ou cartão ativo não arquiva: a tela pede para arquivar esses itens antes.

Cada conta nova recebe categorias e formas de pagamento iniciais:

- Gasto: Mercado, Moradia, Transporte, Saúde, Lazer, Outros
- Reserva: Reserva de emergência, Investimentos
- Receita: Salário, Outras receitas
- Formas de pagamento: Pix, Débito e Crédito (só Crédito exige cartão)

As categorias de Despesa nascem com saldo inicial zero, valendo a partir do mês em que a conta foi criada. Receita não tem saldo inicial.

O escopo `BelongsToUser` filtra toda consulta pelo usuário logado e preenche `user_id` ao criar. No tinker e nos comandos do console não há usuário autenticado: o escopo não filtra e não gera erro. Por isso o comando abaixo precisa indicar a conta.

Para aplicar os padrões a um usuário que já existia:

```bash
docker compose exec app php artisan tinker --execute='app(App\Services\DefaultDataService::class)->createFor(App\Models\User::query()->where("email", "seu@email")->firstOrFail());'
```

Troque `seu@email` pelo e-mail da conta. Rodar de novo não duplica. Para todas as contas de uma vez:

```bash
docker compose exec app php artisan tinker --execute='App\Models\User::query()->each(function ($user) { app(App\Services\DefaultDataService::class)->createFor($user); echo $user->email, PHP_EOL; });'
```

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
