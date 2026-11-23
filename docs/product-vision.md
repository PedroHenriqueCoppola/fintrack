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

Estes termos têm o mesmo significado em todo o sistema, nos prompts e no código.

| Termo | Significado |
| --- | --- |
| Banco | Instituição financeira (Nubank, Itaú, PicPay). Agrupa cartões e transações e, no fechamento do patrimônio, recebe o saldo total guardado nele. |
| Categoria | Classifica transações (Roupas, Viagens, Outros) e concentra o valor do mês e o uso do planejado. Não tem ligação com banco: o dinheiro de uma categoria é só uma conta do sistema, não um valor guardado num lugar. |
| Natureza da categoria | **Gasto** conta como despesa e só recebe transações do tipo Despesa. **Reserva** (investimentos, guardar para o futuro, reserva de emergência) é dinheiro separado para o futuro: só recebe transações do tipo Reserva e aparece separada das despesas nos totais. |
| Reserva (transação) | Terceiro tipo de transação, ao lado de Receita e Despesa. É dinheiro que o usuário **guarda**: já é dele, então não é receita; e não foi gasto, então não é despesa. Usa uma categoria de natureza Reserva, não aceita cartão de crédito e é a base do acompanhamento das Metas. Tem duas ações: **Guardar** (padrão) e **Resgatar**. Não entra em Receitas nem em Despesas; entra em "Reservado" (guardado menos resgatado) e **não** desconta do Saldo do período: o dinheiro guardado continua sendo do usuário. |
| Valor do mês | Quanto o usuário planeja para a categoria no mês, definido no planejamento. Em categoria de Reserva é quanto ele vai guardar no mês. |
| Uso do planejado | Quanto da categoria de Gasto já foi gasto no mês, em valor e em % do valor do mês. Passou de 100% é estouro. |
| Cartão e fatura | Cartão de crédito de um banco, com dia de fechamento. A fatura reúne as compras no crédito de um ciclo. Ela é calculada a partir das transações (não é guardada); só o pagamento é registrado. Situação: Aberta, Fechada (passou o fechamento) ou Paga. |
| Fechamento do patrimônio | Foto mensal do total guardado em cada banco, digitado pelo usuário. O patrimônio é a soma dos bancos. |
| Meta | Objetivo de valor (e prazo opcional) ligado a **uma categoria de Reserva**. O progresso não é digitado: é o que foi guardado menos o que foi resgatado nessa categoria **desde que a meta começou**. A primeira meta de uma categoria conta todo o histórico dela; uma meta criada depois, em categoria que já teve outra meta, começa do zero a partir do saldo do dia em que foi criada. |
| Resgate | Reserva do tipo "Resgatar": dinheiro que sai da reserva. Reduz o progresso da meta, não entra em Receitas nem em Despesas e não muda o Saldo. Não pode passar do que está guardado na categoria. |

## Regras de negócio

O coração do sistema é um orçamento por categorias com acúmulo: o que sobra de um mês continua disponível no seguinte. Estas regras estão confirmadas pelo autor.

1. **Valor sempre positivo.** Toda transação guarda o valor positivo; o tipo (Receita, Despesa ou Reserva) define o sinal. Isso corrige a inconsistência do protótipo.
2. **Uso do planejado da categoria.** Cada categoria de Gasto tem, em cada mês, o valor do mês (planejado), o gasto (despesas Pagas do mês) e o que resta (valor do mês − gasto). Exemplo: Mercado com R$ 800 planejados e R$ 612,40 gastos mostra 77% usado e R$ 187,60 de resto. Não há acúmulo no sistema: a sobra de um mês não vira saldo do seguinte, porque o dinheiro que sobra fica guardado no banco e fora do sistema.
3. **Gasto desconta na data da transação**, seja Pix, débito ou crédito, da categoria da transação.
4. **Estouro de categoria.** Gastar mais que o valor do mês deixa a categoria acima de 100%, com alerta. Não existe remanejamento no sistema: quando o autor cobre um estouro tirando dinheiro de outra caixinha, isso acontece no banco e não é registrado aqui.
5. **Mês de uma despesa.** Só despesas Pagas contam no gasto. Despesa Pendente não conta até virar Paga.
6. **Categorias de Reserva** entram no planejamento com o valor que o autor vai guardar no mês, para a renda esperada fechar com as despesas e as reservas. Não têm gastos nem barra de uso. O que foi guardado é lançado como transação do tipo Reserva e aparece como "Guardado" na área de Reserva do Planejamento; o acompanhamento por objetivo fica em Metas (módulo 9).
7. **Crédito e fatura.** A compra no crédito é uma despesa comum, descontada da categoria na data da compra. Ela entra na fatura do cartão conforme o dia de fechamento: a compra feita no dia do fechamento ou depois dele cai na fatura seguinte. Marcar a fatura como paga só registra a data do pagamento: não cria despesa nova (a despesa já foi contada na compra) e não altera as transações.
8. **Parcelamento.** Lançado uma vez (valor total, número de parcelas, data da primeira). O sistema gera uma transação por mês, Pendente até ser paga; ao ser paga, cada uma desconta da categoria. Na fatura, a parcela Pendente aparece como "Previsto" e, depois de paga, como "Lançado". O parcelamento pode ser criado no formulário da transação ou em Planejamento: é o mesmo cadastro.
9. **Planejamento.** Define o valor do mês de cada categoria mais os gastos fixos e parcelados. Não altera transações já lançadas; ao ser confirmado, gera as transações Pendentes dos fixos e das parcelas do mês. Nasce como cópia do mês anterior. Renda variável não entra aqui: é lançada como receita comum.
10. **Gastos fixos.** Cada fixo tem um valor padrão, mês de início e, opcionalmente, mês de fim; cancelar um fixo é definir o mês de fim. Ao confirmar o planejamento do mês, o sistema gera uma transação Pendente para cada fixo ativo, e o usuário marca como Paga com um clique, podendo ajustar o valor só daquele mês ou daqui em diante (altera o valor padrão). Os fixos contam dentro do valor do mês da categoria, não somados a ele. Um fixo ou parcela cujo valor mensal passa de uma porcentagem configurável do planejado (30% no protótipo) recebe a marca "Alto impacto".
11. **Metas.** O progresso de uma meta é sempre calculado: guardado (Reserva, ação Guardar, Paga) menos resgatado (ação Resgatar, Paga) na categoria de Reserva ligada à meta, **a partir do saldo inicial da meta** (zero na primeira meta da categoria; o saldo do dia da criação nas seguintes), nunca abaixo de zero. Nada é digitado na meta. Há no máximo uma meta **em curso** por categoria (não arquivada e não congelada). Arquivar uma meta **congela** o valor do dia. Criar uma meta nova numa categoria cuja meta em curso está concluída **congela a concluída no valor de hoje**, que continua Concluída (não é arquivada) e deixa de mudar. Resgate nunca pode deixar o saldo da categoria negativo, em nenhuma data.
12. **Patrimônio.** O total é a soma dos bancos, e o valor de cada banco é digitado no fechamento, porque o valor oficial é o que está no banco (o dinheiro rende, com impostos, e o sistema não tenta calcular isso). No fechamento do mês o sistema lista os bancos ativos, cada um com o campo de saldo já preenchido com o valor do fechamento anterior; o usuário ajusta o que mudou. O total é a soma dos campos e o fechamento salva o mês, o saldo de cada banco e as observações. O patrimônio não depende de categorias nem de transações.

### Regras de cálculo

- **Mês da despesa:** conta no mês da data de competência; se estiver vazia, no mês da data da transação. A fatura sempre usa a data da compra.
- **Cálculo derivado:** o gasto e o que resta de cada categoria são sempre calculados a partir das transações, nunca guardados. Alterar uma transação atualiza o mês dela e nada mais.
- **Ritmo diário:** fora do escopo por enquanto. O Dashboard mostra o gasto, o planejado e a % usada de cada categoria, mais o total.
- **Categoria sem planejamento:** só classifica e mostra o gasto do mês; não tem valor do mês nem barra de uso.

### Exemplo dos itens 2 e 4

Em novembro, Transporte tem R$ 400 de valor do mês e gastou R$ 468 (despesas Pagas).

| Categoria | Valor do mês | Gasto | Usado | Resta |
| --- | --- | --- | --- | --- |
| Mercado | 800 | 612,40 | 77% | 187,60 |
| Transporte | 400 | 468 | 117% (estouro) | −68 |

## Páginas e funcionalidades

São oito páginas mais a tela de acesso; cada uma mantém o que o autor valorizou no protótipo e corrige o que ele apontou.

### Estrutura comum

- Não há header. A sidebar é flutuante e recolhível, traz o nome do projeto no topo e os links: Dashboard, Análise, Faturas, Planejamento, Metas, Patrimônio, Relatórios e Gerenciar.
- No topo da área de conteúdo ficam o botão de modo claro/escuro e o ícone de perfil, que abre um menu pequeno com o nome do usuário e a opção Sair. A edição de perfil (nome e senha) fica para depois.
- Cada página tem título e subtítulo explicando o que acontece nela. No Dashboard o título é a saudação "Bom dia", "Boa tarde" ou "Boa noite" seguida do primeiro nome.
- Criar e editar acontecem em modais. O botão "Nova transação" fica no topo do Dashboard e da Análise, à direita do título (a ideia de colocá-lo em todas as páginas foi testada e abandonada).
- Filtro de período padrão em todo o sistema (Dashboard, Análise, Metas e Relatórios; o Patrimônio não usa filtro de período): atalhos prontos (mês atual, mês anterior, trimestre, semestre, ano e todo o período) e "Personalizado", com calendário e campos De e Até. O Planejamento trabalha sempre com um mês fechado e usa o seletor de mês.
- Filtros avançados e salvos: a Análise tem "Mais filtros" (tipo, status, valor mínimo e máximo, cartão e ordenação), chips removíveis dos filtros ativos e filtros salvos com nome, que o usuário aplica com um clique.

### Login e cadastro

Tela única com as opções Entrar e Criar conta, na mesma identidade visual, com visual moderno e animação sutil. Cadastro aberto com nome, e-mail e senha. Não há recuperação de senha por enquanto. Cada usuário enxerga apenas os próprios dados.

### Dashboard

Mantém o mínimo do protótipo (saldo, receitas, despesas, distribuição de gastos, receitas × despesas) e acrescenta o que ajuda a decidir.

- Estados vazios: os blocos que dependem de Planejamento, Patrimônio ou Metas mostram uma mensagem curta com atalho para a página.
- Filtro de período padrão no topo (padrão: Mês atual). Os indicadores comparam com o período anterior de mesmo tamanho (mês anterior, trimestre anterior etc.; em "Todo o período" não há comparação).
- Destaque: **Saldo do período** = receitas − despesas (o que sobrou do que entrou). O card mostra a legenda "R$ X deste saldo foi guardado" quando há Reservado no período. Abaixo, três indicadores: Receitas, Despesas (transações do tipo Despesa) e Reservado (transações do tipo Reserva: guardado menos resgatado), cada um com a comparação.
- Uso do planejado por categoria de Gasto: planejado, gasto e % usado, com barra e alerta, mais o total. Mostra o planejamento do **mês em que o período termina** (limitado ao mês atual) e o título diz qual é ("Uso do planejado · novembro de 2026").
- Fatura aberta de cada cartão ativo, com o dia de fechamento, Lançado e Previsto. Não depende do período.
- Receitas × despesas por mês (barras): os meses do período; quando o período tem menos de 3 meses, os 6 meses até o fim do período.
- Gastos (rosca): por categoria, com alternativa por banco ou cartão. Mostra as 5 maiores e agrupa o resto em "Outras". Substitui "Por tipo de pagamento" e "Método mais usado".
- Últimas transações (6 do período), evolução do patrimônio (linha curta com os últimos 6 fechamentos, não depende do período) e metas em andamento (até 4 metas ativas, com barra, valor e %; não depende do período; estado vazio com atalho para criar).

### Análise

Lista de transações agrupada por dia, com todos os dados visíveis em cada linha.

- Cabeçalho do dia com a data e o total do dia.
- Cada linha mostra categoria (ícone e cor), descrição, banco, cartão ou forma de pagamento, observação e valor colorido.
- Filtros: busca, tipo, período, banco, categoria, cartão e forma de pagamento; ordenação por data ou valor.
- Barra de resumo que muda com o filtro: quantidade, receitas, despesas (tipo Despesa), reservado (tipo Reserva), saldo (receitas − despesas, sem descontar o reservado) e média das despesas.
- Opção de alternar para tabela densa (fica para o módulo de Acabamento).
- Clicar numa linha abre a edição. No rodapé do modal ficam Duplicar (abre uma cópia com a data de hoje) e Excluir (definitivo, com confirmação). A transação Pendente tem um botão para marcar como paga com um clique.
- A lista mostra 50 transações por vez, com o botão "Carregar mais". Os totais do resumo contam todas as transações do filtro, não só as carregadas.
- Os filtros ficam na URL da página, então atualizar ou voltar no navegador os mantém. O usuário pode salvar o conjunto de filtros com um nome (e, se quiser, o período) e aplicá-lo com um clique.

### Faturas

Página própria, entre Análise e Planejamento.

- Seletor de cartão (cartões ativos) e, para o cartão escolhido, a fatura do ciclo atual: período do ciclo, data de fechamento, vencimento (se o cartão tiver dia de vencimento), situação e totais.
- **Lançado:** compras no crédito Pagas do ciclo. **Previsto:** parcelas e fixos Pendentes no crédito, que ainda vão entrar. O total estimado é a soma dos dois, mostrados separados.
- Lista das transações da fatura, agrupadas em Lançado e Previsto; clicar numa linha abre a edição da transação.
- Navegação entre faturas (anterior e próxima) e histórico de faturas anteriores com o total e a situação.
- **Marcar como paga:** só depois do fechamento, com a data do pagamento. Só registra a marca; não cria despesa, não mexe nas transações e os Pendentes continuam Pendentes. Dá para desfazer a marca.
- A fatura leva o nome do mês do fechamento (fatura de novembro fecha em novembro).

### Planejamento

Duas seções e um painel de visão final que se atualiza enquanto o usuário preenche.

- **Planejar o mês:** seletor de mês (qualquer mês, passado, atual ou futuro), renda esperada, e uma linha por categoria com o valor do mês, o gasto, a % usada e o que resta. As categorias de Reserva aparecem numa área própria, só com o valor que vai guardar. Botão "Copiar do mês anterior" (o mês sem plano salvo abre vazio; não herda valores sozinho). "Sobra a alocar" = renda esperada − soma dos valores do mês (Gasto e Reserva); pode ficar negativa, com alerta. Só meses **futuros** têm estado Rascunho ou Confirmado (a confirmação gera os Pendentes de fixos e parcelas), continuam editáveis depois de confirmados e há um botão para voltar a Rascunho. No mês atual e nos passados não há confirmação: os Pendentes do mês atual são gerados automaticamente.
- **Fixos e parcelados** (tudo do protótipo é mantido): cadastro de fixo (nome, categoria, valor padrão, mês de início e de fim, pagamento, observação) e de parcelado (nome, categoria, valor total, número de parcelas, início, pagamento, observação); indicadores de total mensal comprometido, fixos, parcelados ativos, parcelas pagas/totais, já quitado no mês e % do orçamento comprometido; filtros por categoria, valor mínimo e máximo, ordenação; alerta % configurável (campo na própria página, padrão 30%) com a marca "Alto impacto"; barra de progresso de parcelas. Ao confirmar o planejamento, cada fixo e cada parcela do mês viram transações Pendentes, com valor editável só naquele mês ou daqui em diante. O sistema pode criar Pendentes com data futura; a tela de lançamento manual continua recusando.
- **Visão final:** painel ao lado com renda, total planejado, sobra a alocar, rosca da distribuição (gastos e reservas) e quanto está comprometido com fixos e parcelados.

### Metas

- Criar e editar meta: nome, valor alvo, categoria de Reserva (obrigatória) e prazo (mês, opcional) e observação. Não há data de início.
- O progresso vem das transações de Reserva da categoria (Guardar menos Resgatar, só Pagas). Não existe histórico de lançamentos próprio da meta: guardar e resgatar são feitos pelos botões "Guardar" e "Resgatar" da meta, que abrem o modal de transação já em Reserva e com a categoria preenchida.
- Estados: **Ativa** (progresso abaixo do alvo), **Concluída** (progresso ≥ alvo; volta a Ativa se um resgate baixar o saldo) e **Arquivada** (a pessoa arquivou). Ativa e Concluída são calculadas; uma meta concluída que foi congelada por uma meta nova continua Concluída, com o valor fixo; Arquivada guarda o valor do dia em que foi arquivada. Meta arquivada não pode ser editada nem receber Guardar/Resgatar; meta concluída e congelada só permite editar nome e observação. Restaurar só é possível se for a meta mais recente da categoria e a categoria não tiver outra meta em curso. Não há pausada, cancelada nem exclusão. Meta concluída mostra a barra cheia em verde e o chip "Concluída"; passar do alvo nunca é aviso.
- Cada meta mostra o progresso (valor e %), quanto falta e, se tiver prazo, quanto guardar por mês (o que falta dividido pelos meses restantes, contando o mês atual e o do prazo, arredondado para cima ao centavo). Prazo vencido e não concluída mostra "Prazo vencido". Sem previsão de data de conclusão.
- Filtro de período padrão no topo (padrão: Mês atual), só na URL. O progresso, o percentual, o restante, o estado e o valor mensal **não** dependem do período (são sempre o total da categoria até hoje). O período muda apenas: o indicador "Guardado no período", a linha "Guardado no período" de cada meta, o gráfico e as movimentações do detalhe.
- Painel com total guardado hoje (soma do progresso das metas não arquivadas), metas ativas, metas concluídas e **guardado no período** (guardar menos resgatar nas categorias das metas não arquivadas).
- Gráfico geral: o total guardado nas metas não arquivadas ao fim de cada mês do período, como linha. Mostra **todos os meses do período** (até 60); em "Todo o período" começa no mês da primeira movimentação de Reserva das metas; menos de 3 meses mostra 6 meses até o fim do período. Com muitos meses, o eixo mostra só alguns rótulos.
- Detalhe da meta (página própria): progresso, quanto falta, valor mensal sugerido, gráfico da evolução da meta nos meses do período (com a linha do valor alvo) e as últimas 10 movimentações da categoria dentro do período, com link "Ver na Análise".

### Patrimônio

Mantém o que o autor mais gostou, com o fechamento feito por banco.

- Saldo do mês de cada banco, como "Nubank: R$ 8.200,00".
- Fechamento mensal: lista os bancos ativos, cada um com o saldo pré-preenchido pelo fechamento anterior e a variação em relação a ele; o usuário digita o que mudou, vê o total somado e salva com observações. O botão do topo é sempre "Registrar fechamento" (serve para criar e para editar): abre o modal no mês mais recente ainda sem fechamento (até o atual); se todos já estão fechados, abre o mês atual em edição. Dentro do modal o mês pode ser trocado (até o atual) e o modal diz se o mês escolhido está "sem fechamento" ou "já fechado (editando)". Editar um fechamento antigo é clicar na linha dele em "Registros por mês".
- Indicadores: patrimônio atual (último fechamento), variação desde o fechamento anterior e mês do último fechamento, mais a quantidade de registros do período.
- Por banco: saldo e variação de cada banco no último fechamento ("Sem alteração", "Novo" quando o banco não existia no anterior).
- Evolução do patrimônio (linha, uma ponta por fechamento do período), com o filtro de período padrão; abaixo, os registros por mês (total, variação, observação e o saldo de cada banco), com editar e excluir.

### Gerenciar

Todos os cadastros que servem para separar e organizar o sistema, em cards com ícone e cor, busca e contagem de uso. Criar e editar em modal. Item em uso é arquivado, não apagado.

- Bancos, categorias (com natureza Gasto ou Reserva), cartões (ligados a um banco, com dia de fechamento) e formas de pagamento (Pix, Débito e Crédito de partida).
- O tipo da transação é fixo (Receita, Despesa ou Reserva) e não tem cadastro.

### Relatórios

Duas áreas.

- **Montar relatório:** escolher período, quais dados entram (só gastos, só ganhos, gastos de uma categoria, saldo de cada mês, faturas, patrimônio, metas), filtros (tipo, categoria, banco, cartão, forma de pagamento) e agrupamento (dia, semana, mês, categoria, banco). Prévia ao vivo e opção de salvar como modelo.
- **Relatórios gerados:** lista com nome, data e filtros usados, para reabrir ou baixar em **PDF** (bem formatado, na identidade visual) e **Excel**.

## Cadastros e campos

Cada entidade abaixo tem um dono (o usuário), datas de criação e atualização e o marcador "arquivado". Os campos opcionais existem para deixar os relatórios mais completos sem pesar no uso básico.

| Entidade | Campos obrigatórios | Campos opcionais |
| --- | --- | --- |
| Transação | tipo (Receita, Despesa ou Reserva), data, valor, descrição, categoria, banco, forma de pagamento | cartão (obrigatório se for crédito; define o banco), observação, status (Paga ou Pendente), data de competência, parcela x/y (gerada pelo sistema, a partir do módulo 5) |
| Banco | nome | cor |
| Categoria | nome, aplicável a (Despesa ou Receita), natureza (Gasto ou Reserva, só para Despesa) | ícone, cor |
| Cartão | nome, banco, dia de fechamento | dia de vencimento, cor |
| Pagamento de fatura | cartão, mês do fechamento, data do pagamento | |
| Forma de pagamento | nome | exige cartão |
| Planejamento do mês | mês, renda esperada, valor do mês por categoria de Despesa (Gasto e Reserva), estado (Rascunho ou Confirmado) | observação |
| Gasto fixo | nome, categoria, valor padrão, mês de início | mês de fim, forma de pagamento, observação |
| Gasto parcelado | nome, categoria, valor total, número de parcelas, início | forma de pagamento, observação |
| Meta | nome, valor alvo, categoria de Reserva | prazo (mês), observação |
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
5. **Planejamento:** valor do mês por categoria (Gasto e Reserva), uso do planejado (gasto e %), renda e sobra a alocar, visão final, estado Rascunho/Confirmado, e gastos fixos e parcelados com as transações Pendentes geradas. Inclui a remoção do saldo inicial da categoria. Pronto quando o exemplo Mercado e Transporte dá 77% e 117% e a confirmação gera os Pendentes. O código é revisado pelo próprio agente contra este documento antes da entrega.
6. **Cartões e faturas:** página Faturas, com a fatura calculada por ciclo (Lançado e Previsto), histórico e marcação de pago. Inclui a geração automática dos Pendentes do mês atual. Pronto quando uma compra feita no dia do fechamento ou depois dele cai na fatura seguinte. O código é revisado pelo próprio agente contra este documento antes da entrega.
7. **Patrimônio:** saldo por banco, fechamento mensal e evolução. Pronto quando o fechamento pré-preenche com o mês anterior.
8. **Dashboard:** lê tudo dos módulos 4 a 7, por isso vem depois deles.
9. **Metas:** metas ligadas a categorias de Reserva, Resgate, valor mensal sugerido e gráficos. Pronto quando guardar e resgatar na categoria move o progresso da meta e o resgate acima do guardado é recusado.
10. **Relatórios:** construtor, relatórios gerados, PDF e Excel.
11. **Acabamento:** (perguntar ao autor sobre a exclusão de metas e de outros cadastros, hoje só arquivados); responsividade de todo o sistema (reduzir a janela do navegador sem quebrar elementos; isso substitui a regra "só desktop"), favicon e logo de verdade (hoje é um F branco sobre roxo), modo demo (usuário demo com 12 meses de dados fictícios coerentes com as regras), backup, README profissional escrito pelo agente (apresentação, funcionalidades, stack, como subir com Docker, regras de negócio, diagrama do banco e usuário demo; os prints são tirados pelo autor), mapa de rotas e revisão final.

## Decisões da revisão

Todos os pontos da revisão foram respondidos pelo autor; não há pendências abertas para a etapa 2.

- [x] **Saldo inicial da categoria:** confirmado na época, **removido no módulo 5** (ver "Módulo 5" abaixo).
- [x] **Planejamento Rascunho ou Confirmado:** confirmado. Mantido no módulo 5: confirmar libera a geração dos Pendentes de fixos e parcelas.
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
- **Data da transação:** a transação **Paga** não pode ter data posterior a hoje. A **Pendente** pode ter data futura, também no lançamento manual (boleto, conta a vencer); essa regra foi ajustada no módulo 6. Ao marcar uma Pendente como paga: se a data é futura, ela vira hoje; se a data é passada e a forma de pagamento **não** é cartão (Pix, débito, boleto), ela também vira hoje, porque a data é o dia em que o dinheiro saiu; no cartão, a data passada é mantida, porque ela decide a fatura. Em todos os casos a competência guarda a data original.
- **Receita e forma de pagamento:** a forma de pagamento continua obrigatória. Salário e outras receitas usam uma forma comum do catálogo, como "Transferência" (TED ou DOC), cadastrada em Gerenciar.
- **Padrões de interface (a partir do módulo 4):** campo de data com o calendário do filtro Personalizado; confirmação de exclusão sempre em modal próprio; erro do campo some ao preencher; dinheiro alinhado à esquerda no campo.

### Módulo 5 (revisão do saldo)

- **Caminho escolhido:** orçamento simples do mês. Saem o saldo anterior, o saldo disponível com acúmulo, o saldo inicial da categoria, o remanejamento (e o Resgate) e o ritmo diário. A sobra de um mês fica guardada no banco, fora do sistema; mover dinheiro entre caixinhas também.
- **Reservas:** entram no planejamento com o valor a guardar; o acompanhamento real fica em Metas.
- **Rascunho e Confirmado:** mantidos. O mês confirmado continua editável e pode voltar a Rascunho.
- **Meses:** qualquer mês pode ser planejado.
- **Tamanho:** módulo único, com revisão pelo próprio agente.
- **Fixos e parcelas:** o sistema pode criar Pendentes com data futura (o lançamento manual não pode).
- **Alto impacto:** porcentagem configurável num campo da própria página, padrão 30%.
- **Fixos e parcelas por categoria:** a tabela do planejamento tem a coluna "Comprometido" (antes "Fixos e parcelas"), logo depois de "Gasto no mês": quanto, entre os fixos e parcelas ativos naquele mês **mais os Pendentes lançados à mão** (sem fixo nem parcela de origem), pertence à categoria. Conta mesmo sem confirmar o mês. É informativa (o "Resta" continua sendo valor do mês − gasto pago) e avisa "Passa do valor do mês" quando o comprometido é maior que o planejado.
- **Aba Fixos e parcelados:** mostra por padrão o que está ativo no mês selecionado; o que ainda não começou ou já terminou fica num grupo recolhido "Fora deste mês", com o motivo. O Pendente de cada mês só nasce quando aquele mês é confirmado.
- **Mês de trabalho compartilhado:** o mês escolhido no Planejamento vale também para as Faturas e é lembrado enquanto o usuário estiver logado. O Dashboard não usa o mês de trabalho: tem filtro de período próprio (decisão do módulo 8). Há o atalho "Voltar ao mês atual". A Análise lembra os próprios filtros à parte, com "Limpar filtros".
- Esta decisão substitui as regras de saldo com acúmulo e de remanejamento das seções anteriores deste documento (inclusive "Saldo inicial da categoria" e "Caixinha" em Decisões da revisão e "Ritmo diário" na Segunda revisão).

### Módulo 6

- **Onde ficam as faturas:** página nova "Faturas" na sidebar, entre Análise e Planejamento.
- **Dia do fechamento:** a compra feita exatamente no dia do fechamento entra na fatura **seguinte**. Dia de fechamento 29, 30 ou 31 em mês mais curto vale o último dia do mês.
- **Pendentes na fatura:** parcelas e fixos Pendentes no crédito aparecem como "Previsto", separados do "Lançado".
- **Pagar a fatura:** só marca com a data. Não cria despesa nem altera transações.
- **Geração dos Pendentes (regra híbrida):** o mês atual gera os Pendentes de fixos e parcelas sozinho (ao criar, editar ou arquivar um fixo ou parcelado, e ao entrar no sistema, uma vez por sessão). Meses futuros só geram quando o planejamento é confirmado. Quando o usuário vê na Análise um mês com fixos ou parcelas previstos e planejamento não confirmado, aparece um aviso com link para o Planejamento.

- **Pago antes do mês (ajuste do módulo 6):** quando uma transação tem competência em mês diferente do da data (por exemplo, um fixo de dezembro pago em novembro), ela mostra o selo "Ref. dez/2026" na Análise, no modal de edição e na lista da Fatura. No Planejamento, o fixo ou a parcela daquele mês aparece como "Pago antecipado em 20/11" em vez de sumir da lista.

### Módulo 7

- **Meses:** o fechamento aceita qualquer mês até o atual (dá para montar o histórico), um por mês, editável e excluível depois.
- **Variação:** sempre contra o fechamento imediatamente anterior, mesmo que meses tenham sido pulados. É calculada, não guardada; corrigir um mês antigo atualiza a variação do seguinte.
- **Bancos no fechamento:** bancos ativos sempre aparecem (banco novo entra com saldo 0). Banco arquivado só aparece se o fechamento de referência tinha saldo nele, para o usuário poder zerá-lo.
- **Saldo:** valor digitado, zero ou maior; não há saldo negativo. O total do patrimônio é a soma dos bancos e não é guardado.
- **Gráfico:** a linha de evolução entra já no módulo 7, com a biblioteca Recharts, que o Dashboard (módulo 8) reaproveita.
- **Período padrão:** Ano. O Patrimônio não usa o mês de trabalho do Planejamento.
- **Princípio daqui em diante:** priorizar o que simplifica o dia a dia e dá boas visualizações; antes de cada módulo, cortar o que só complica.

### Módulo 8

- **Período:** o Dashboard usa o filtro de período padrão (padrão Mês atual), não o mês de trabalho.
- **Destaque:** Saldo do período (receitas − despesas), com a legenda de quanto dele foi guardado. O "Disponível para gastar" e o gasto por dia do protótipo ficam de fora.
- **Reserva:** é um terceiro tipo de transação (Receita, Despesa e Reserva), decisão do autor depois dos testes. Despesas = tipo Despesa; Reservado = tipo Reserva; Saldo = receitas − despesas, sem descontar o Reservado (ajuste do autor: guardar não tira o dinheiro do usuário). No Planejamento, "Sobra a alocar" continua descontando a Reserva planejada, porque lá a conta é dividir a renda entre gastar e guardar. Guardar dinheiro não é receita nem despesa. O Planejamento mostra "Guardado no mês" na área de Reserva (texto, sem barra).
- **Tamanho:** Dashboard completo na primeira versão, com barras e rosca.
- **Planejado por categoria:** segue o mês em que o período termina (limitado ao mês atual) e o título informa o mês.
- **Planejamento (revisão do módulo 8):** mês sem plano salvo abre vazio, com o botão "Copiar do mês anterior" (o Dashboard só usa planos salvos); a confirmação existe só para meses futuros; a coluna "Comprometido" inclui os Pendentes lançados à mão; a área de Reserva mostra "Guardado: R$ X" (texto, sem barra); categoria de Reserva arquivada continua aparecendo no mês em que tem valor guardado.
- **Priorização:** simplicidade do dia a dia e boas visualizações.

### Segunda revisão

- **Caixinha e categoria:** a caixinha foi removida. (As regras de saldo e remanejamento da categoria foram removidas no módulo 5.) O Patrimônio é por banco, com valor digitado.
- **Fixos e parcelas:** geram transações Pendentes no mês, editáveis e marcadas como Pagas com um clique.
- **Resgate de Reserva:** removido no módulo 5, junto com o remanejamento.
- **Sem modo simples:** todas as funcionalidades para todos, nenhuma obrigatória.
- **Ritmo diário:** removido no módulo 5 (fora do escopo por enquanto).

### Módulo 9

- **Progresso pela categoria de Reserva:** cada meta liga a uma categoria de Reserva; o progresso é guardado menos resgatado a partir do saldo inicial da meta (a primeira meta da categoria conta todo o histórico, inclusive o guardado antes de ela existir). Não há aporte nem ajuste digitado na meta.
- **Prazo opcional e valor mensal sugerido:** com prazo, a meta mostra quanto guardar por mês. Sem previsão de data de conclusão (decisão de simplicidade).
- **Resgate dentro de Reserva:** o tipo Reserva ganha a escolha Guardar ou Resgatar (continuam três tipos de transação). O resgate não muda o Saldo. O sistema bloqueia qualquer criação, edição, exclusão, troca de data, categoria ou status que deixe o saldo da categoria negativo em alguma data.
- **Estados:** Ativa, Concluída (calculada) e Arquivada (congela o valor do dia). Sem pausada, cancelada nem exclusão. Filtro "Todas | Ativas | Concluídas | Arquivadas" na lista, com etiqueta de estado em cada card quando "Todas" está selecionado.
- **Uma meta em curso por categoria, e recomeço do zero (ajuste pós-teste):** meta concluída não impede a próxima. Ao criar uma meta numa categoria que já teve metas, a nova conta só o que for guardado a partir do saldo do dia, e a meta concluída que estava na categoria é **congelada** no valor de hoje, sem ser arquivada (continua em Concluídas). Meta ainda em andamento continua impedindo uma nova na mesma categoria (o usuário conclui ou arquiva antes).
- **Visualizações:** cards com barra de progresso, gráfico geral do total guardado e, no detalhe, o gráfico da meta. Metas usa o filtro de período padrão (decisão do autor), que não altera o progresso, só o que foi guardado no período e os gráficos.
- **Exclusão de meta:** adiada. O autor quer decidir sobre exclusão (de metas e dos demais cadastros) no fim, na etapa de correções e estética: perguntar a ele nessa etapa.
