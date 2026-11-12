# Fintrack

Sistema web de finanças pessoais: transações, orçamento por categoria com acúmulo, planejamento do mês, metas, patrimônio e relatórios. As regras de negócio estão em `docs/product-vision.md`.

Nesta etapa o projeto sobe com um comando, com tema claro/escuro, layout e componentes comuns. Ainda não há login nem cadastros.

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

Abra a aplicação em http://localhost:8000. O Vite só entrega os arquivos do front; a navegação fica na porta 8000.

pgAdmin, ambiente local:

- E-mail: `admin@example.com`
- Senha: `fintrack`

O servidor **Fintrack** já aparece na lista. Na primeira conexão o pgAdmin pede a senha do banco: `fintrack` (usuário `fintrack`, banco `fintrack`).

Essas senhas são só de desenvolvimento.

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
