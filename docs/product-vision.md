# Fintrack — Documento de Visão do Produto

Nov 9, 2026 · @Pedro

## Visão geral

O Fintrack é um sistema web de finanças pessoais que substitui a planilha e o bloco de notas do autor, automatizando o registro de transações, o planejamento do mês, o acompanhamento de metas e o fechamento do patrimônio.

- **Quem usa:** o autor no dia a dia, mas com cadastro aberto: qualquer pessoa cria uma conta e vê somente os próprios dados.
- **Sem planos nem licenças:** todas as funcionalidades estão disponíveis para todos e nenhuma é obrigatória. Quem só quer lançar transações e acompanhar Análise e Dashboard usa só essas telas, sem depender de Planejamento, Metas ou Patrimônio; quem quer o método completo (planejamento por categoria, faturas, metas, fechamento mensal) usa tudo. Os blocos do Dashboard que dependem dessas páginas mostram um estado vazio com atalho, sem quebrar nada.
- **O que substitui:** a linha colorida na planilha para cada transação, as somas manuais de gastos e metas, o fechamento mensal por banco e o planejamento do mês feito no bloco de notas.
- **Base de partida:** o protótipo do Lovable serve de base de pensamento e de identidade visual, não de alvo. As regras e telas deste documento prevalecem sobre ele.

### Princípios

1. O backend é a fonte da verdade: todo cálculo de dinheiro acontece no servidor, e os valores ficam em `DECIMAL`.
2. O uso básico precisa ser simples; os recursos avançados ficam opcionais.
3. Nada que já tem histórico é apagado: o item é arquivado.
4. Só computador. Não haverá trabalho de layout para celular.
5. Roda localmente com Docker, com backup do banco.
6. Projeto de portfólio: modo demo com dados fictícios, README com prints e documentação das regras.

## Glossário

Estes dez termos têm o mesmo significado em todo o sistema, nos prompts e no código.

| Termo | Significado |
| --- | --- |
| Banco | Instituição financeira (Nubank, Itaú, PicPay). Agrupa cartões e transações e, no fechamento do patrimônio, recebe o saldo total guardado nele. |
| Categoria | Classifica transações (Roupas, Viagens, Outros) e concentra as regras de saldo, estouro e remanejamento. Não tem ligação com banco: o dinheiro de uma categoria é só uma conta do sistema, não um valor guardado num lugar. |
| Natureza da categoria | **Gasto** conta como despesa. **Reserva** (investimentos, guardar para o futuro, reserva de emergência) é dinheiro separado para o futuro: não é despesa e aparece separada das despesas nos totais. |
| Valor do mês | Quanto o usuário coloca em cada categoria no começo do mês, definido no planejamento. |
| Saldo anterior | O que sobrou da categoria no fim do mês passado. |
| Saldo disponível | Saldo anterior + valor do mês ± remanejamentos − gastos da categoria no mês. |
| Remanejamento | Mover valor de uma categoria para outra dentro de um mês. |
| Cartão e fatura | Cartão de crédito de um banco, com dia de fechamento. A fatura reúne as compras no crédito de um ciclo e é "aberta" ou "paga". |
| Fechamento do patrimônio | Foto mensal do total guardado em cada banco, digitado pelo usuário. O patrimônio é a soma dos bancos. |
| Meta | Objetivo de valor com prazo, alimentado por um histórico de lançamentos (aportes e ajustes). |

## Regras de negócio

O coração do sistema é um orçamento por categorias com acúmulo: o que sobra de um mês continua disponível no seguinte. Estas regras estão confirmadas pelo autor.

1. **Valor sempre positivo.** Toda transação guarda o valor positivo; o tipo (Receita ou Despesa) define o sinal. Isso corrige a inconsistência do protótipo.
2. **Saldo disponível da categoria.** Saldo anterior + valor do mês ± remanejamentos − gastos. Exemplo: outubro termina com R$ 120 sobrando em Roupas; em novembro o autor coloca R$ 300; o disponível é R$ 420. Na primeira vez, o usuário informa o saldo inicial de cada categoria.
3. **Gasto desconta na data da transação**, seja Pix, débito ou crédito, da categoria da transação.
4. **Estouro de categoria.** O saldo pode ficar negativo no mês e o sistema alerta. Se o mês terminar negativo, o seguinte começa em zero: o estouro não é descontado do próximo mês.
5. **Remanejamento.** Para cobrir um estouro, o usuário move valor de outra categoria (normalmente Outros). O sistema soma o valor ao destino, subtrai da origem e guarda o histórico. Quando a origem é uma categoria de Reserva, o remanejamento aparece como "Resgate", que é como o autor trata a retirada de um cofrinho antes de gastar. Sem isso, a categoria que cedeu o valor continuaria mostrando um saldo que o autor já usou.
6. **Categorias de Reserva** seguem a mesma regra de acúmulo, mas não contam como despesa e não têm gastos próprios: o saldo cresce com o valor do mês e a retirada é um remanejamento ("Resgate").
7. **Crédito e fatura.** A compra no crédito é uma despesa comum, descontada da categoria na data da compra. Ela entra na fatura do cartão conforme o dia de fechamento: compra feita após o fechamento cai na fatura seguinte. Marcar a fatura como paga não cria despesa nova, porque a despesa já foi contada na compra.
8. **Parcelamento.** Lançado uma vez (valor total, número de parcelas, data da primeira). O sistema gera uma transação por mês, Pendente até ser paga; ao ser paga, cada uma desconta da categoria e entra na fatura do mês correspondente. O parcelamento pode ser criado no formulário da transação ou em Planejamento: é o mesmo cadastro.
9. **Planejamento.** Define o valor do mês de cada categoria mais os gastos fixos e parcelados. Não altera transações já lançadas; ao ser confirmado, gera as transações Pendentes dos fixos e das parcelas do mês. Nasce como cópia do mês anterior. Renda variável não entra aqui: é lançada como receita comum.
10. **Gastos fixos.** Cada fixo tem um valor padrão, mês de início e, opcionalmente, mês de fim; cancelar um fixo é definir o mês de fim. Ao confirmar o planejamento do mês, o sistema gera uma transação Pendente para cada fixo ativo, e o usuário marca como Paga com um clique, podendo ajustar o valor só daquele mês ou daqui em diante (altera o valor padrão). Os fixos contam dentro do valor do mês da categoria, não somados a ele. Um fixo ou parcela cujo valor mensal passa de uma porcentagem configurável do planejado (30% no protótipo) recebe a marca "Alto impacto".
11. **Metas.** O valor atual é sempre a soma de um histórico de lançamentos. O usuário lança um aporte (valor e data) ou digita "agora tenho X", e o sistema registra a diferença como ajuste.
12. **Patrimônio.** O total é a soma dos bancos, e o valor de cada banco é digitado no fechamento, porque o valor oficial é o que está no banco (o dinheiro rende, com impostos, e o sistema não tenta calcular isso). No fechamento do mês o sistema lista os bancos ativos, cada um com o campo de saldo já preenchido com o valor do fechamento anterior; o usuário ajusta o que mudou. O total é a soma dos campos e o fechamento salva o mês, o saldo de cada banco e as observações. O patrimônio não depende de categorias nem de transações.

### Regras de cálculo

- **Mês da despesa:** conta no mês da data de competência; se estiver vazia, no mês da data da transação. A fatura sempre usa a data da compra.
- **Saldo derivado:** o saldo disponível é sempre calculado a partir do histórico, nunca guardado. O saldo anterior de um mês é o maior entre zero e o saldo final do mês anterior, e alterar uma transação antiga recalcula os meses seguintes.
- **Saldo inicial:** vale a partir do mês em que a categoria é criada.
- **Ritmo diário:** o Dashboard mostra, em cada categoria de Gasto com valor no mês, o saldo disponível dividido pelos dias restantes. Não existe um total único somando categorias.
- **Categoria sem planejamento:** só classifica e mostra o gasto; não tem saldo disponível.

### Exemplo dos itens 2 e 5

Roupas gastou R$ 470 no mês e Outros cede R$ 50. Sem o remanejamento, Roupas fecharia em −R$ 50.

| Linha | Roupas (R$) | Outros (R$) |
| --- | --- | --- |
| Saldo anterior | 120 | 80 |
| Valor do mês | 300 | 100 |
| Remanejamento | +50 | −50 |
| Gastos | −470 | 0 |
| **Saldo disponível** | **0** | **130** |

## Páginas e funcionalidades

São sete páginas mais a tela de acesso; cada uma mantém o que o autor valorizou no protótipo e corrige o que ele apontou.

### Estrutura comum

- Não há header. A sidebar é flutuante e recolhível, traz o nome do projeto no topo e os links: Dashboard, Análise, Planejamento, Metas, Patrimônio, Relatórios e Gerenciar.
- No topo da área de conteúdo ficam o botão de modo claro/escuro e o ícone de perfil, que abre um menu pequeno com o nome do usuário e a opção Sair. A edição de perfil (nome e senha) fica para depois.
- Cada página tem título e subtítulo explicando o que acontece nela. No Dashboard o título é a saudação "Bom dia", "Boa tarde" ou "Boa noite" seguida do primeiro nome.
- Criar e editar acontecem em modais. O botão "Nova transação" fica no topo do Dashboard e da Análise, à direita do título (a ideia de colocá-lo em todas as páginas foi testada e abandonada).
- Filtro de período padrão em todo o sistema (Dashboard, Análise, Metas, Patrimônio e Relatórios): atalhos prontos (mês atual, mês anterior, trimestre, semestre, ano e todo o período) e "Personalizado", com calendário e campos De e Até. O Planejamento trabalha sempre com um mês fechado e usa o seletor de mês.
- Filtros avançados e salvos: a Análise tem "Mais filtros" (tipo, status, valor mínimo e máximo, cartão e ordenação), chips removíveis dos filtros ativos e filtros salvos com nome, que o usuário aplica com um clique.

### Login e cadastro

Tela única com as opções Entrar e Criar conta, na mesma identidade visual, com visual moderno e animação sutil. Cadastro aberto com nome, e-mail e senha. Não há recuperação de senha por enquanto. Cada usuário enxerga apenas os próprios dados.

### Dashboard

Mantém o mínimo do protótipo (saldo, receitas, despesas, distribuição de gastos, receitas × despesas) e acrescenta o que ajuda a decidir.

- Estados vazios: os blocos que dependem de Planejamento, Patrimônio ou Metas mostram uma mensagem curta com atalho para a página.
- Indicadores de saldo, receitas, despesas, cada um comparado ao período anterior.
- Saldo disponível por categoria, com barra de progresso, alerta perto do limite e o ritmo diário (quanto ainda pode gastar por dia até o fim do mês, calculado por categoria de Gasto).
- Fatura aberta de cada cartão, com o dia de fechamento.
- Receitas × despesas por mês (barras).
- Gastos por categoria (rosca), com alternativa por banco ou cartão. Substitui "Por tipo de pagamento" e "Método mais usado".
- Evolução do patrimônio (linha curta), últimas transações e metas em andamento.

### Análise

Lista de transações agrupada por dia, com todos os dados visíveis em cada linha.

- Cabeçalho do dia com a data e o total do dia.
- Cada linha mostra categoria (ícone e cor), descrição, banco, cartão ou forma de pagamento, observação e valor colorido.
- Filtros: busca, tipo, período, banco, categoria, cartão e forma de pagamento; ordenação por data ou valor.
- Barra de resumo que muda com o filtro: quantidade, receitas, despesas, saldo e média.
- Opção de alternar para tabela densa (fica para o módulo de Acabamento).
- Clicar numa linha abre a edição. No rodapé do modal ficam Duplicar (abre uma cópia com a data de hoje) e Excluir (definitivo, com confirmação). A transação Pendente tem um botão para marcar como paga com um clique.
- A lista mostra 50 transações por vez, com o botão "Carregar mais". Os totais do resumo contam todas as transações do filtro, não só as carregadas.
- Os filtros ficam na URL da página, então atualizar ou voltar no navegador os mantém. O usuário pode salvar o conjunto de filtros com um nome (e, se quiser, o período) e aplicá-lo com um clique.

### Planejamento

Três seções e um painel de visão final que se atualiza enquanto o usuário preenche.

- **Planejar o mês:** seletor de mês, renda esperada e valor do mês por categoria, mostrando o saldo anterior e o disponível resultante. Botão "copiar do mês anterior". "Sobra a alocar" = renda esperada − soma dos valores do mês.
- **Fixos e parcelados** (tudo do protótipo é mantido): cadastro de fixo (nome, categoria, valor padrão, mês de início e de fim, pagamento, observação) e de parcelado (nome, categoria, valor total, número de parcelas, início, pagamento, observação); indicadores de total mensal comprometido, fixos, parcelados ativos, parcelas pagas/totais, já quitado no mês e % do orçamento comprometido; filtros por categoria, valor mínimo e máximo, ordenação; alerta % configurável com a marca "Alto impacto"; barra de progresso de parcelas. Ao confirmar o planejamento, cada fixo e cada parcela do mês viram transações Pendentes, com valor editável só naquele mês.
- **Remanejar:** mover valor entre categorias no mês, com histórico.
- **Visão final:** painel ao lado com renda, total planejado, sobra a alocar, rosca da distribuição e quanto está comprometido com fixos e parcelados.

### Metas

- Criar e editar meta: nome, valor alvo, início, prazo e categoria opcional.
- Histórico de lançamentos da meta (aportes e ajustes) com data e observação, editável.
- Estados: ativa, pausada, concluída, cancelada e arquivada.
- Cada meta mostra o progresso, quanto falta, quanto precisa guardar por mês e a previsão no ritmo atual.
- Painel com total guardado, metas ativas e concluídas.
- Gráfico de evolução geral: o total guardado ao longo dos meses, com uma linha por meta e alternância entre Geral e Por meta.
- Cada meta tem uma miniatura da evolução no card e uma tela de detalhe com a linha real contra a linha ideal, o quanto falta, quanto guardar por mês, a previsão e a lista de lançamentos. Esses gráficos substituem os do protótipo.

### Patrimônio

Mantém o que o autor mais gostou, com o fechamento feito por banco.

- Saldo do mês de cada banco, como "Nubank: R$ 8.200,00".
- Fechamento mensal: lista os bancos ativos, cada um com o saldo pré-preenchido pelo fechamento anterior e a variação em relação a ele; o usuário digita o que mudou, vê o total somado e salva com observações.
- Indicadores: total atual, variação desde o último fechamento e último fechamento.
- Evolução do patrimônio (linha), observações mensais e registros por mês organizados por banco.

### Gerenciar

Todos os cadastros que servem para separar e organizar o sistema, em cards com ícone e cor, busca e contagem de uso. Criar e editar em modal. Item em uso é arquivado, não apagado.

- Bancos, categorias (com natureza Gasto ou Reserva), cartões (ligados a um banco, com dia de fechamento) e formas de pagamento (Pix, Débito e Crédito de partida).
- O tipo da transação é fixo (Receita ou Despesa) e não tem cadastro.

### Relatórios

Duas áreas.

- **Montar relatório:** escolher período, quais dados entram (só gastos, só ganhos, gastos de uma categoria, saldo de cada mês, faturas, patrimônio, metas), filtros (tipo, categoria, banco, cartão, forma de pagamento) e agrupamento (dia, semana, mês, categoria, banco). Prévia ao vivo e opção de salvar como modelo.
- **Relatórios gerados:** lista com nome, data e filtros usados, para reabrir ou baixar em **PDF** (bem formatado, na identidade visual) e **Excel**.

## Cadastros e campos

Cada entidade abaixo tem um dono (o usuário), datas de criação e atualização e o marcador "arquivado". Os campos opcionais existem para deixar os relatórios mais completos sem pesar no uso básico.

| Entidade | Campos obrigatórios | Campos opcionais |
| --- | --- | --- |
| Transação | tipo (Receita ou Despesa), data, valor, descrição, categoria, banco, forma de pagamento | cartão (obrigatório se for crédito; define o banco), observação, status (Paga ou Pendente), data de competência, parcela x/y (gerada pelo sistema, a partir do módulo 5) |
| Banco | nome | cor |
| Categoria | nome, aplicável a (Despesa ou Receita), natureza (Gasto ou Reserva, só para Despesa) | ícone, cor, saldo inicial (só Despesa) |
| Cartão | nome, banco, dia de fechamento | dia de vencimento, cor |
| Forma de pagamento | nome | exige cartão |
| Planejamento do mês | mês, renda esperada, valor do mês por categoria, estado (Rascunho ou Confirmado) | observação |
| Gasto fixo | nome, categoria, valor padrão, mês de início | mês de fim, forma de pagamento, observação |
| Gasto parcelado | nome, categoria, valor total, número de parcelas, início | forma de pagamento, observação |
| Remanejamento | mês, categoria de origem, categoria de destino, valor | observação |
| Meta | nome, valor alvo, prazo | início, categoria, observação |
| Lançamento de meta | meta, tipo (Aporte ou Ajuste), valor, data | observação |
| Fechamento do patrimônio | mês de referência, saldo de cada banco | observações |
| Relatório salvo | nome, período, dados incluídos | filtros, agrupamento |

A lista de campos é a proposta inicial e será refinada no modelo do banco da etapa 2 e pode mudar durante o desenvolvimento, sempre por novas migrações (nunca editando uma migração já aplicada).

## Identidade visual

A identidade é "preto sofisticado com roxo", com um tom acolhedor: fundo quase preto levemente arroxeado, cards em camadas com sombra suave, um roxo único como cor de destaque e a fonte Plus Jakarta Sans. Texto pequeno e discreto, espaço bem distribuído (os blocos têm o tamanho do conteúdo, sem esticar para preencher), cantos bem arredondados e animações mínimas. O desenho completo está em `docs/design/` (imagens e HTML de cada tela, nos temas escuro e claro) e as regras de cada elemento estão em `docs/design-system.md`, que é a fonte de verdade dos valores.

### Regras de estilo

- **Fonte:** Plus Jakarta Sans. Texto de corpo em 14px, títulos de página em 26px, números com algarismos de largura fixa.
- **Cantos:** 22px nos cards e na sidebar, 14px em botões e campos, 24px nos modais.
- **Cards:** em camadas (fundo, card, bloco interno), sombra suave e borda fina. Ao passar o mouse, o card sobe 2px.
- **Botão principal:** roxo sólido com sombra suave na cor primária. O gradiente do protótipo foi abandonado.
- **Layout:** sem header. Sidebar flutuante e recolhível, conteúdo ocupando toda a largura no notebook e centralizado com largura máxima em telas grandes.
- **Transições:** 0.25s com curva `cubic-bezier(0.4, 0, 0.2, 1)`. Sem animações de entrada, só o fade do modal.
- **Brilho do cursor:** gradiente radial de 600px, roxo a 8%, que acompanha o mouse. Continua, e pode ser removido depois sem mexer no resto.
- **Valores:** receitas e positivos em roxo, despesas e negativos em vermelho, sempre acompanhados de + ou − para não depender só da cor. Números em R$ no padrão brasileiro.
- **Categorias:** cada categoria aparece com um ícone sobre um quadrado arredondado na sua cor, em tom suave.
- **Gráficos:** roxo para a série principal e tons de azul, verde-água e laranja para as demais, sempre com legenda em texto. Vermelho só para despesas.
- **Modo escuro é o padrão**, com botão para alternar para o claro, e a escolha fica salva. O tema claro usa a mesma estrutura, com fundo lavanda bem claro e cards brancos.

### Como isso entra no código

Variáveis CSS em `:root` (escuro) e `[data-theme='light']`, definidas em `docs/design-system.md`. `html` com `font-size: 62.5%` (1rem = 10px), um arquivo CSS por componente ao lado do `.jsx` e os estilos globais num único arquivo. Nenhum componente usa cor escrita à mão: só as variáveis.

## Plataforma e decisões técnicas

A stack confirmada é Laravel (PHP) com Inertia, React em JSX, CSS puro e PostgreSQL, tudo rodando em Docker Desktop no computador do autor. Pasta de código, regras para o agente e modelo do banco ficam para a etapa 2.

| Camada | Escolha | Observação |
| --- | --- | --- |
| Backend | Laravel (PHP) | Framework do MaiaLanches: rotas, login, validação e migrações prontos |
| Páginas | Inertia + React (JSX) | O Laravel entrega as páginas e o React desenha; um serviço só |
| Estilo | CSS puro | Variáveis globais e um CSS por componente |
| Banco | PostgreSQL | Sobe pelo Docker, sem configurar no XAMPP |
| Ambiente | Docker Desktop | Um comando para subir tudo |

### Padrões do MaiaLanches que serão repetidos

- Backend em camadas: Controllers de API, FormRequests de validação, Resources de resposta, Services para as regras e Models com migrações e seeders.
- O backend é a fonte da verdade: o front não envia valor calculado.
- Componentes React em pastas próprias, cada um com seu `.jsx` e seu `.css`, e um layout comum para as páginas internas.
- Sessão por cookie do Laravel, com limite de tentativas de login.
- Documentação viva: README com como subir o projeto e um `mapa-de-rotas.md` atualizado a cada módulo.

### Decisões adicionais

- **Isolamento de dados:** toda consulta é filtrada pelo usuário logado, sem exceção.
- **Dinheiro:** `DECIMAL(15,2)` no banco e cálculo no servidor. Datas e fuso em `America/Sao_Paulo`.
- **Backup:** um script diário com `pg_dump` mais uma segunda cópia em outro local (pendrive ou nuvem), já que a cópia no mesmo disco não protege contra falha do disco. A restauração precisa ser testada.
- **Sem testes automatizados** por decisão do autor. Em troca, cada módulo terá um checklist de conferência manual com números de exemplo (como o exemplo Roupas e Outros nas regras).
- **Modo demo:** seeder com dados fictícios para o portfólio.
- **A definir na etapa 2:** biblioteca de gráficos, biblioteca de PDF e Excel, e o fluxo de trabalho com o agente no Cursor.

## Fora do escopo agora

Os itens abaixo foram conscientemente deixados de fora.

- **Celular e PWA:** descartados. Só computador.
- **Importação de extrato (CSV ou OFX):** talvez no futuro. Hoje todo dado é digitado; o foco é a exportação dos relatórios.
- **Recuperação de senha:** fora por enquanto.
- **Testes automatizados:** dispensados por decisão do autor.
- **Caixinhas (subdivisão do banco):** removidas do sistema. O Patrimônio é fechado por banco.
- **Saldo do banco calculado pelas transações:** descartado. O dinheiro rende com impostos, então o valor oficial é o que está no banco e é digitado no fechamento do mês.

## Ordem de construção

São onze módulos em sequência, mais o 3.5 de visual, cada um dependendo do anterior; cada módulo vira um prompt na etapa 3, com o checklist de "pronto quando" abaixo.

1. **Base:** Docker, Laravel, Inertia, PostgreSQL, tema claro/escuro, sidebar e componentes comuns (botão, modal, campos, cards). Pronto quando sobe com um comando e alterna o tema.
2. **Login e cadastro:** conta, entrar, sair, isolamento por usuário. Pronto quando dois usuários não veem os dados um do outro.
3. **Gerenciar:** bancos, categorias, cartões e formas de pagamento, com categorias iniciais. Pronto quando cria, edita e arquiva por modal.
   - **Módulo 3.5, novo visual:** aplicar o design de `docs/design/` e `docs/design-system.md` ao que já existe (tokens, layout sem header, componentes, login e Gerenciar), em três etapas. Pronto quando as telas existentes batem com as imagens de referência nos temas escuro e claro.
4. **Transações e Análise:** nova transação, lista por dia, filtros (período personalizado, avançados e salvos), resumo, edição, duplicar, excluir e marcar como paga. O parcelamento fica para o módulo 5 e as tags para depois. Pronto quando os totais da Análise batem com o conjunto de exemplo do roteiro de testes.
5. **Planejamento:** valor do mês, saldo disponível com acúmulo, remanejamento, fixos e parcelados, visão final e estado Rascunho/Confirmado. Pronto quando o exemplo Roupas e Outros dá 0 e 130.
6. **Cartões e faturas:** agrupar compras no crédito por fechamento e marcar fatura como paga. Pronto quando uma compra após o fechamento cai na fatura seguinte.
7. **Patrimônio:** saldo por banco, fechamento mensal e evolução. Pronto quando o fechamento pré-preenche com o mês anterior.
8. **Dashboard:** lê tudo dos módulos 4 a 7, por isso vem depois deles.
9. **Metas:** lançamentos, estados, ritmo e gráficos.
10. **Relatórios:** construtor, relatórios gerados, PDF e Excel.
11. **Acabamento:** modo demo (usuário demo com 12 meses de dados fictícios coerentes com as regras), backup, README profissional escrito pelo agente (apresentação, funcionalidades, stack, como subir com Docker, regras de negócio, diagrama do banco e usuário demo; os prints são tirados pelo autor), mapa de rotas e revisão final.

## Decisões da revisão

Todos os pontos da revisão foram respondidos pelo autor; não há pendências abertas para a etapa 2.

- [x] **Saldo inicial da categoria:** confirmado. Informado ao criar a categoria.
- [x] **Planejamento Rascunho ou Confirmado:** confirmado. O valor do mês só entra no saldo disponível depois de confirmado.
- [x] **Caixinha:** removida. O sistema calcula o saldo das categorias; o valor de cada banco é digitado no fechamento do mês.
- [x] **Taxa de poupança:** fora da primeira versão. O Dashboard mostra saldo, receitas e despesas.
- [x] **Categorias de receita:** confirmado. Cada categoria é aplicável a Despesa ou a Receita.
- [x] **Transação Pendente:** confirmado. Não conta nos totais nem desconta da categoria até virar Paga.
- [x] **Categorias iniciais:** conjunto genérico e editável. Gasto: Mercado, Moradia, Transporte, Saúde, Lazer e Outros. Reserva: Reserva de emergência e Investimentos. Receita: Salário e Outras receitas.
- [x] **Botão Nova transação:** no topo do Dashboard e da Análise. A versão em todas as páginas foi abandonada junto com o header.
- [x] **Nome do projeto:** Fintrack.

### Módulo 4

- **Parcelamento:** não entra no módulo 4. Nasce junto com os gastos fixos, no Planejamento (módulo 5).
- **Tags:** ficam para depois do módulo 4. A data de competência entra agora.
- **Exclusão de transação:** definitiva, com confirmação. A regra de nunca apagar de verdade vale para bancos, categorias, cartões e formas de pagamento.
- **Banco e cartão:** ao escolher um cartão, o banco é o do cartão e fica travado.
- **Pendente:** não entra nos totais do resumo (receitas, despesas, saldo e média). A quantidade conta todas as transações do filtro.
- **Filtros:** ficam na URL, os salvos entram no módulo 4 e a lista carrega de 50 em 50.
- **Tabela densa:** fica para o Acabamento.

### Segunda revisão

- **Caixinha e categoria:** a caixinha foi removida. As regras de saldo, estouro e remanejamento são da categoria; o Patrimônio é por banco, com valor digitado.
- **Fixos e parcelas:** geram transações Pendentes no mês, editáveis e marcadas como Pagas com um clique.
- **Resgate de Reserva:** é um remanejamento da Reserva para outra categoria.
- **Sem modo simples:** todas as funcionalidades para todos, nenhuma obrigatória.
- **Ritmo diário:** calculado por categoria, nunca somado.