# Fintrack — Design system

Fonte de verdade do visual. As imagens e o HTML de cada tela estão em `docs/design/`. Se este arquivo e uma imagem divergirem, vale este arquivo; se ele estiver ambíguo, pergunte antes de decidir.

Todos os tamanhos abaixo estão em rem, com `html { font-size: 62.5% }` (1rem = 10px). Os HTML de `docs/design/html/` usam px: divida por 10.

## 1. Sensação

Acolhedor e discreto. Pouco texto grande, poucas cores, espaço bem distribuído. Os blocos têm o tamanho do seu conteúdo: nunca estique um card só para preencher a linha, e nunca invente conteúdo para ocupar espaço. Profundidade vem de camadas (fundo, card, bloco interno) e de sombra suave, nunca de contraste forte, gradiente chamativo ou brilho.

## 2. Tokens de cor

Mantenha os nomes que já existem em `resources/css/app.css` e acrescente os novos. Nenhum componente usa cor escrita à mão (hex, hsl ou rgb): só `var(--...)`.

### Escuro (padrão, `:root`)

| Variável | Valor | Uso |
| --- | --- | --- |
| `--color-bg` | `#0f0e14` | fundo da página |
| `--color-card` | `#17151f` | cards, sidebar, campos de filtro |
| `--color-sidebar` | `#17151f` | igual ao card |
| `--color-card-inset` | `#1d1a27` | blocos dentro de um card, campos de formulário |
| `--color-card-highlight` | `#1c1730` | card de destaque (saldo principal, visão final) |
| `--color-border` | `#26232f` | borda de card e divisórias |
| `--color-border-input` | `#2a2635` | borda de campos e de blocos internos |
| `--color-border-strong` | `#3d3950` | borda no hover e em campos de busca |
| `--color-border-highlight` | `#32295a` | borda do card de destaque |
| `--color-text` | `#efedf6` | texto principal |
| `--color-text-muted` | `#a7a3b8` | texto secundário, rótulos |
| `--color-text-subtle` | `#8a8699` | apoio, datas, eixos de gráfico |
| `--color-primary` | `#7c4fd6` | botão principal |
| `--color-primary-soft` | `#a678f0` | linhas de gráfico, pontos, foco |
| `--color-primary-fill` | `#8c63dc` | barras de progresso |
| `--color-secondary` | `#1f1c29` | botão secundário |
| `--color-accent` | `#241f35` | item ativo (sidebar, abas, segmentos) |
| `--color-accent-text` | `#dccbfb` | texto do item ativo |
| `--color-chip-bg` | `#2a2142` | chips e selos |
| `--color-chip-text` | `#cdb4fa` | texto dos chips |
| `--color-positive` | `#bc9cf7` | receitas e valores positivos |
| `--color-negative-text` | `#f08a8c` | despesas e valores negativos |
| `--color-destructive` | `#d9696c` | barra estourada, ações destrutivas |
| `--color-modal` | `#1a1724` | fundo do modal |
| `--color-modal-border` | `#302b42` | borda do modal |
| `--color-overlay` | `rgb(8 7 12 / 0.72)` | fundo escurecido atrás do modal |

### Claro (`[data-theme='light']`)

Mesma estrutura, fundo lavanda bem claro e cards brancos. As imagens em `docs/design/light/` mostram o resultado.

| Variável | Valor |
| --- | --- |
| `--color-bg` | `#f6f4fb` |
| `--color-card` / `--color-sidebar` | `#ffffff` |
| `--color-card-inset` | `#f3f0fa` |
| `--color-card-highlight` | `#f1eafd` |
| `--color-border` | `#e6e1f0` |
| `--color-border-input` | `#e6e1f0` |
| `--color-border-strong` | `#d3cce3` |
| `--color-border-highlight` | `#d9cbf5` |
| `--color-text` | `#1a1726` |
| `--color-text-muted` | `#5f5a72` |
| `--color-text-subtle` | `#6f6a84` |
| `--color-primary` | `#7c4fd6` |
| `--color-primary-soft` | `#7c4fd6` |
| `--color-primary-fill` | `#7c4fd6` |
| `--color-secondary` | `#efeaf8` |
| `--color-accent` | `#ece4fb` |
| `--color-accent-text` | `#5a2fb0` |
| `--color-chip-bg` | `#ece4fb` |
| `--color-chip-text` | `#5a2fb0` |
| `--color-positive` | `#6d3fc4` |
| `--color-negative-text` | `#c23b43` |
| `--color-destructive` | `#c23b43` |
| `--color-modal` | `#ffffff` |
| `--color-modal-border` | `#e0daee` |
| `--color-overlay` | `rgb(26 23 38 / 0.45)` |

Sombras no claro usam `rgb(60 40 110 / 0.08)` em vez de preto.

## 3. Tipografia

Plus Jakarta Sans (já instalada). Números de dinheiro e tabelas usam `font-variant-numeric: tabular-nums`. Nunca use texto maior que 3.4rem.

| Elemento | Tamanho | Peso | Detalhe |
| --- | --- | --- | --- |
| Título de página | 2.6rem | 700 | `letter-spacing: -0.025em` |
| Subtítulo da página | 1.4rem | 400 | `--color-text-muted` |
| Número grande (saldo principal) | 3.2rem | 700 | `letter-spacing: -0.03em` |
| Número de indicador | 2.6rem (resumo), 2.1rem (secundário) | 700 | |
| Título de card | 1.5rem | 600 | |
| Corpo e linhas de lista | 1.4rem | 500 ou 600 | |
| Rótulos, apoio, ajuda | 1.25rem | 500 | `--color-text-muted` ou `--color-text-subtle` |
| Título de seção na página | 1.2rem | 600 | maiúsculas, `letter-spacing: 0.04em` |
| Eixos de gráfico | 1.1rem | 400 | `--color-text-subtle` |

## 4. Espaçamento, cantos e sombras

- **Espaçamento:** escala de 0.4, 0.6, 0.8, 1.0, 1.2, 1.4, 1.6, 2.0, 2.4 e 3.6rem. Espaço entre cards: 1.6rem. Padding de card: 2.2rem por 2.4rem (cards compactos de indicador: 1.6rem por 1.8rem). Padding da área de conteúdo: 2rem por 3.6rem (topo e laterais), 4.8rem embaixo.
- **Cantos** (`--radius-*`): `--radius-sm` 0.8rem (chips), `--radius-md` 1.4rem (botões, campos, abas), `--radius-lg` 1.8rem (blocos dentro de cards), `--radius-xl` 2.2rem (cards, sidebar), `--radius-2xl` 2.4rem (modal). Quadrado de ícone: 1.2rem a 1.4rem. O `--radius: 0.75rem` antigo deixa de ser usado.
- **Sombras:** card `0 0.8rem 2.4rem rgb(0 0 0 / 0.28)`; sidebar `0 1rem 3rem rgb(0 0 0 / 0.35)`; modal `0 3rem 8rem rgb(0 0 0 / 0.55)`; botão principal `0 0.6rem 1.6rem rgb(124 79 214 / 0.3)`.

## 5. Layout

- **Sem header.** O nome do projeto fica no topo da sidebar.
- **Sidebar flutuante:** largura 22.8rem, margem 1.4rem, canto 2.2rem, `position: sticky; top: 1.4rem; height: calc(100vh - 2.8rem)`, sempre com a altura da tela e com todos os 8 itens (Dashboard, Análise, Faturas, Planejamento, Metas, Patrimônio, Relatórios, Gerenciar) mais o botão Recolher no rodapé. Recolhida: 7.2rem, só ícones (com `title`). Item: ícone 1.8rem, texto 1.4rem, padding 1rem por 1.2rem, canto 1.4rem. Item ativo: fundo `--color-accent`, texto `--color-accent-text`, borda interna de 1px roxa a 22%. Logo: quadrado roxo 2.8rem com um F branco.
- **Área de conteúdo:** ocupa toda a largura restante. Largura máxima 168rem, centralizada em telas maiores (`--container-max`).
- **Linha do topo:** altura 4.4rem, alinhada à direita, com o botão de tema (3.8rem, quadrado arredondado) e o ícone de perfil (3.8rem, redondo). O ícone abre um menu pequeno com o nome do usuário e a opção **Sair**.
- **Cabeçalho da página:** título e subtítulo à esquerda, ações à direita (ex.: botão Nova transação, seletor de mês). Margem de 1rem em cima e 2.4rem embaixo.
- **Grade:** 12 colunas com espaço de 1.6rem. Use `align-items: start` quando os blocos tiverem alturas diferentes.
- **Desktop apenas por enquanto.** A responsividade de todo o sistema entra no módulo de Acabamento; até lá, não construa layouts de celular.

## 6. Componentes

### Card
Fundo `--color-card`, borda 1px `--color-border`, canto `--radius-xl`, sombra de card. Variantes: **destaque** (fundo `--color-card-highlight`, borda `--color-border-highlight`) para um bloco por tela, e **interno** (fundo `--color-card-inset`, borda `--color-border-input`, canto `--radius-lg`) para itens dentro de um card. Card clicável ou de lista: no hover sobe 0.2rem (`translateY(-0.2rem)`) e a borda vai para `--color-border-strong`, em 0.25s.

### Botão
Altura confortável, padding 1rem por 1.8rem, canto `--radius-md`, texto 1.35rem em 600. **Principal:** fundo `--color-primary`, texto branco, sombra de botão (sem gradiente). **Secundário (ghost):** fundo `--color-secondary`, sem sombra, texto `--color-text`. **Discreto (quiet):** sem fundo, texto `--color-text-muted`. **Destrutivo:** fundo `--color-destructive`. Desabilitado com opacidade 0.5. Foco visível com contorno `--color-primary-soft`.

### Campo de formulário
Rótulo acima, 1.25rem em 500, `--color-text-muted`. Campo com fundo `--color-card-inset`, borda `--color-border-input`, canto 1.3rem, padding 1rem por 1.4rem, texto 1.4rem. Valores digitados, inclusive dinheiro, ficam alinhados à esquerda como em qualquer outro campo (use `tabular-nums` no dinheiro). Erro: borda e texto em `--color-negative-text` abaixo do campo, nunca só a cor. O erro some assim que o usuário altera o campo, sem esperar um novo envio; ele só volta se o servidor recusar de novo.

### Seletor de data (padrão do sistema)
Todo campo de data usa o mesmo calendário do filtro Personalizado: o campo aceita digitação com máscara dd/mm/aaaa e tem um botão de calendário à direita (ícone lucide `Calendar`, `aria-label="Abrir calendário"`) que abre um popover. O popover traz mês e ano no topo com setas, grade de dias com iniciais D S T Q Q S S, dia selecionado em `--color-primary` e dias fora do mês esmaecidos. Navegação por teclado: setas movem o dia, Enter escolhe, Esc fecha só o popover (nunca o modal que o contém). Em campo de data única, escolher o dia preenche o campo e fecha o popover. Em intervalo (De – Até) o mesmo calendário seleciona a faixa, com `--color-accent` no meio. Dias acima do limite (ex.: depois de hoje, na data da transação) ficam desabilitados e esmaecidos. Um único componente `DatePicker` serve todo o sistema; não crie outro calendário.

### Campo de mês e navegação de mês
**Campo de mês** (`MonthField`): campo no estilo do formulário que mostra o mês **por extenso** ("Novembro 2026"), entre as setas ‹ ›. Clicar no nome abre um seletor pequeno (popover) com os 12 meses em grade (Jan a Dez) e setas para trocar o ano; meses fora de `min`/`max` ficam desabilitados. Não se digita. Campo opcional vazio mostra "Escolher mês" e o popover tem o link "Limpar"; com o campo vazio as setas ficam desabilitadas. Usado onde o dado é um mês (início e fim de fixo). **Navegação de mês** (`MonthNav`): o seletor do cabeçalho da página, com seta anterior, "Novembro 2026" e seta próxima (veja `docs/design/dark/planning.png`). Os dois usam os mesmos nomes de mês do calendário. **Padrão de texto para mês em todo o sistema:** por extenso em campos, títulos e frases ("Novembro 2026" em título ou campo; "novembro de 2026" no meio de uma frase, como "Começa em novembro de 2026"); abreviado ("Nov 2026", "nov") só em gráfico ou onde não couber. Nunca "11/2026" na tela. Datas continuam dd/mm/aaaa. As setas precisam **parecer clicáveis**: botão com borda visível, `cursor: pointer`, fundo `--color-accent` e texto `--color-accent-text` no hover, leve escurecimento ao pressionar, anel de foco visível e estado desabilitado claro (opacidade 0.4, sem hover) quando o mês passa do limite permitido. Tamanho mínimo de 3.6rem de alto e largo.

### Linha de lista clicável
Linha que abre uma edição (ex.: registros por mês do Patrimônio). Repouso: sem fundo, divisória 1px `--color-border` entre linhas e lápis em `--color-text-subtle` num espaço fixo à direita (o valor nunca se desloca). Hover e foco: fundo `--color-accent` a 70%, **cantos arredondados** (`--radius-md`) e o fundo avança 1rem para os lados dentro do padding do card, com padding interno igual, de modo que o fundo nunca encoste na borda do card nem fique com quina reta; as divisórias da própria linha e da anterior somem enquanto o fundo aparece; o lápis passa a `--color-accent-text`. `cursor: pointer`, `aria-label` "Editar …", anel de foco visível.

### Cards lado a lado
Cards da mesma linha de uma grade têm sempre a **mesma altura** (a da maior), com o conteúdo alinhado ao topo. Lista que pode crescer muito dentro de um card (ex.: registros por mês) rola dentro do card, com altura máxima, em vez de esticar a linha.

### Breakpoints
Media queries sempre em **px** (por exemplo `@media (max-width: 1100px)`). Em `rem` o navegador usa 16px, e não os 10px do `html{font-size:62.5%}`, então o ponto de quebra sai muito maior que o esperado.

### Barra de uso (planejado × gasto)
Mesma barra de progresso, com a % ao lado. Até 100%: preenchimento `--color-primary-fill`. Acima de 100%: `--color-destructive`, % e o "resta" negativo em `--color-negative-text`, e o texto sempre mostra o número (nunca só a cor). Entre 90% e 100% aparece o texto "Perto do limite" em `--color-text-muted`, **numa linha própria abaixo da barra**. Essa linha existe em todas as linhas da tabela, vazia quando não há aviso, para a altura das linhas nunca mudar. O mesmo vale para avisos curtos de outras colunas (ex.: "Passa do valor do mês"): aviso vai embaixo do valor, numa linha reservada, nunca ao lado. Em janelas estreitas a tabela rola na horizontal dentro do próprio card, sem quebrar a página.

### Segmento e abas
Grupo de opções com fundo `--color-card-inset` (ou `--color-card` nas abas da página), borda fina, canto `--radius-md`, padding 0.4rem. Opção ativa com fundo `--color-accent` e texto `--color-accent-text`. As abas do Gerenciar usam esse mesmo estilo.

### Chip e selo
Canto 0.8rem, padding 0.2rem por 0.8rem, texto 1.15rem em 500. Padrão em `--color-chip-bg` e `--color-chip-text`. Variantes: neutra (fundo `--color-card-inset`, texto `--color-text-muted`) e concluída (verde suave, como o quadrado de ícone verde).

### Chips de situação da fatura
Mesmo chip da seção anterior. **Aberta**: neutra. **Fechada**: padrão (roxo suave). **Paga**: concluída (verde suave), com a data ("Paga em 10/11"). **Previsto** e **Lançado** nomeiam os dois totais da fatura e os dois grupos da lista: Lançado em texto normal e Previsto em `--color-text-muted`. Nunca só cor: o rótulo sempre aparece. Veja `docs/design/dark/invoices.png`.

### Selo de referência e pago antecipado
Chip neutro (fundo `--color-card-inset`, texto `--color-text-muted`) com o texto "Ref. dez/2026", ao lado da descrição ou da data, só quando a competência cai em mês diferente do da data. No Planejamento, o fixo ou a parcela quitada antes usa o chip de concluída (verde suave) com "Pago antecipado em 20/11". O texto sempre aparece; nunca só cor.

### Quadrado de ícone da categoria
38px (3.8rem), canto 1.2rem. A cor vem da categoria (HSL gravado no servidor). Fundo: `color-mix(in srgb, var(--item-color) 16%, var(--color-card))`. Ícone: `color-mix(in srgb, var(--item-color) 70%, var(--color-text))`. Sem cor: fundo `--color-card-inset`, ícone `--color-text-muted`. Ícones da biblioteca lucide, traço 1.75.

### Barra de progresso
Altura 0.6rem, canto total, trilho `--color-border-input`, preenchimento `--color-primary-fill`. Passou do valor: preenchimento `--color-destructive` e o texto dizendo que estourou.

### Modal
Fundo `--color-modal`, borda `--color-modal-border`, canto `--radius-2xl`, padding 2.6rem, sombra de modal, largura 56rem (62rem no de transação). Fundo escurecido com `--color-overlay`. Título 1.8rem em 700, descrição curta em `--color-text-muted`, campos com espaço de 1.4rem, rodapé com Cancelar (ghost) e ação principal à direita. Abre perto do topo (padding-top 7rem) e rola quando for alto. Entrada: fade de 0.2s. Mantém o foco preso e fecha com Esc, como o componente atual.

### Confirmação de exclusão (padrão do sistema)
Toda exclusão pede confirmação num modal do próprio sistema (componente `ConfirmDialog`, modal estreito), nunca no `confirm`, `alert` ou `prompt` do navegador. Título em forma de pergunta ("Excluir transação?", "Apagar filtro salvo?"), uma frase com o nome do item e o aviso "Essa ação não pode ser desfeita.", rodapé com Cancelar (ghost) e o botão Destrutivo com o verbo da ação ("Excluir", "Apagar"). O foco inicial fica em Cancelar. Enquanto a exclusão roda, os dois botões ficam desabilitados e o destrutivo mostra "Excluindo…". Veja `docs/design/dark/transaction-delete.png`.

### Linha de lista
Ícone ou quadrado à esquerda, nome e apoio no meio, valor à direita; divisória 1px `--color-border` entre linhas, padding vertical 1.2rem.

### Tabela
Cabeçalho em 1.25rem `--color-text-subtle`, linhas com divisória e padding 1.2rem por 1rem, valores à direita com tabular-nums. Campos editáveis dentro da tabela usam o campo de formulário compacto.

### Estado vazio
Quadrado de ícone 4.4rem em `--color-accent`, uma frase curta em `--color-text-muted` (máx. 36rem) e, quando houver, um link de atalho em `--color-chip-text`. Sem ilustrações.

### Valores em dinheiro
`+R$` e `−R$` (sinal de menos verdadeiro), receita em `--color-positive`, despesa em `--color-negative-text`, zero sem sinal e na cor do texto. Nunca só a cor: o sinal sempre aparece.

### Filtro de período (usado a partir do módulo 4)
Linha acima do conteúdo: segmento com Mês atual, Mês anterior, Trimestre, Semestre, Ano, Todo o período e Personalizado; caixa com as datas (De – Até) e, quando houver, botões Mais filtros e Salvar filtro. Personalizado abre um popover com calendário (faixa selecionada em `--color-accent`, extremos em `--color-primary`) e campos De e Até. Filtros ativos viram chips removíveis, com "Limpar filtros" e, à direita, os filtros salvos como chips tracejados. Veja `docs/design/dark/filters-open.png`.

### Gráficos (usados a partir do módulo 7)
Linha principal em `--color-primary-soft`, 2.4px, pontas arredondadas, ponto final destacado, com área abaixo em gradiente de 22% de opacidade até 0. Linhas de grade em `--color-border`. Eixos em 1.1rem `--color-text-subtle`. Séries extras: azul `#6f9ee0`, verde-água `#7cc4ac`, laranja `#e6b08d` (no claro: `#2f64b0`, `#2a7a62`, `#a65a24`). Legenda sempre em texto. Rosca com espessura 14 e trilho `--color-border-input`; o centro mostra o total do período; segmentos nas cores dos cadastros (categoria, banco ou cartão) e "Outras" em `--color-text-subtle`; legenda em texto com valor e %. **Barras** (receitas × despesas por mês): duas barras por mês, cantos superiores arredondados (4px), receitas em `--color-positive` e despesas em `--color-destructive`, sem grade vertical, mês abreviado no eixo, tooltip com o mês por extenso e os dois valores. Todo gráfico tem `role="img"` com `aria-label` resumindo os dados e uma alternativa em texto (legenda ou lista) com os mesmos números. Vermelho só para despesas.

### Relatórios em PDF e Excel (usados a partir do módulo 10; revisado na correção 1)

**Regras comuns (prévia, PDF e Excel):**
- **Uma única formatação**, feita no servidor: o servidor entrega, para cada célula, o valor cru (para o Excel) e o texto pronto com o tom (para a prévia e o PDF). O navegador não formata dinheiro, percentual nem data dos relatórios.
- **Despesas são valor positivo e neutro** em colunas que já dizem o que são (Despesas, Valor dos Gastos por categoria, Lançado, Previsto, Total). Vermelho e "−R$" só para o que é negativo de verdade: saldo negativo, variação negativa, resgate e despesa no Extrato (coluna Valor com sinal). Positivos não levam "+", exceto em colunas de variação. O Excel grava o mesmo sinal que a prévia mostra.
- Zeros ("R$ 0,00") em cor secundária suave. Valores em peso normal; **só a linha de total em negrito**. Números alinhados à direita com algarismos tabulares; texto, data e situação à esquerda.
- **Cabeçalho do documento:** título grande, o período com as datas exatas ("Ano de 2026 · 01/01/2026 a 31/12/2026"; "Todo o período · desde 05/03/2025"; mês: "Novembro de 2026 · 01/11/2026 a 30/11/2026") e os filtros como etiquetas, **só quando há filtro** (nunca "Sem filtros").
- **Caixas de indicadores (KPIs):** os totais do Extrato e o topo do Resumo do período são quatro caixas lado a lado (Receitas, Despesas, Reservado, Saldo), com rótulo pequeno em cima e valor grande embaixo; o Saldo segue a cor do sinal.

**PDF:** sempre tema claro, fundo branco, tokens do tema claro escritos uma vez numa folha de estilo do PDF: texto `#1a1726`, secundário `#5f5a72`, suave `#6f6a84`, destaque `#7c4fd6`, texto de destaque `#5a2fb0`, fundo de destaque `#ece4fb`, cabeçalho de tabela `#f4f0fb`, listra `#faf8fd`, positivo/ok `#2a7a62`, negativo `#c23b43`, borda `#e0daee`. Fonte DejaVu Sans (tem "−" e acentos), sem internet.
- Faixa de cabeçalho: à esquerda o quadrado "F" em `#7c4fd6` com o nome Fintrack; à direita "Gerado em dd/mm/aaaa HH:mm" em texto suave; um filete `#e0daee` embaixo.
- Título 20pt em negrito, período 10pt, etiquetas de filtro com fundo `#ece4fb` e texto `#5a2fb0`.
- Tabela: cabeçalho com fundo `#f4f0fb`, rótulos 8pt em maiúsculas com leve espaçamento, repetido a cada página; listras `#faf8fd`; total com filete superior de 1,5pt e negrito.
- Rodapé centralizado: "Fintrack · Página X de Y", numerado **sem** execução de PHP no HTML.

**Excel:** uma aba por relatório (até 31 caracteres). Linhas iniciais: título em negrito tamanho 14, período (com as datas exatas), filtros (só se houver) e "Gerado em". Cabeçalho em negrito com a linha congelada. Dinheiro como número com o formato `"R$ "#,##0.00;-"R$ "#,##0.00` (o "R$" entre aspas), percentual `0.0%`, datas `dd/mm/aaaa`, inteiros como inteiro, total em negrito, larguras ajustadas. As caixas de indicadores viram duas colunas (Indicador, Valor). Sem gráficos nem cores.

### Página de Relatórios (layout)

- No topo, o filtro de período padrão (vale para tudo).
- Acima de 1280px: duas colunas. **Esquerda (largura fixa por volta de 36rem): card "Configurar"** com abas "Modelos prontos | Montar o meu" no topo do card. Em Modelos prontos, os quatro modelos empilhados, cada um com ícone, nome e uma linha de descrição, e o selecionado com borda e fundo de destaque. Em Montar o meu, "O que incluir" em quatro blocos com ícone (2 × 2) e, abaixo, os campos do conjunto escolhido.
- **Direita (o resto da largura): a prévia como documento.** Cabeçalho com título (2.4rem, 700), período com datas, etiquetas de filtro e, no canto direito, as ações: **"Baixar PDF"** (botão primário com ícone) e **"Baixar Excel"** (secundário com ícone). Abaixo, as caixas de indicadores (quando houver) e as tabelas. "Mostrando 50 de N" em texto pequeno abaixo da tabela.
- Até 1280px: tudo empilhado (Configurar em cima, documento embaixo). Tabelas rolam na horizontal dentro do documento só quando não cabem.

### Campo de seleção (módulo 11a; substitui o select nativo)

- Botão com a aparência do campo de formulário (mesma altura, borda, fundo, raio, foco), mostrando o item escolhido com o ícone/cor quando houver (categoria: quadrado de ícone pequeno; banco: bolinha da cor) e uma seta `ChevronDown` que gira ao abrir.
- Lista em popover com o mesmo visual do seletor de data: fundo `--color-modal`, borda `--color-modal-border`, `--shadow-modal`, raio `--radius-md`, altura máxima ~32rem com rolagem. Item com 3.6rem de altura, hover e item ativo com `--color-accent`/`--color-accent-text`, selecionado com `Check` à direita. Item arquivado com "(arquivado)" em texto suave. Grupos com rótulo pequeno em maiúsculas quando a lista tiver grupos.
- Busca no topo da lista quando houver mais de 8 opções (ignora acento e maiúscula), com "Nenhum resultado".
- Opção vazia ("Todos", "Todas", "Nenhum") quando o campo aceita vazio, sempre no topo.
- Teclado: Enter/Espaço/↓ abre; ↑ ↓ Home End navegam; letras saltam para o item; Enter escolhe; Esc fecha e devolve o foco. Padrão ARIA de combobox + listbox. Dentro de modal, a lista não é cortada pela borda do modal.

### Campo de valor (módulo 11a)

- Mostra "1.234.567,89" enquanto digita: pontos de milhar automáticos, vírgula para centavos (no máximo 2 casas). Só aceita dígitos e uma vírgula; colar "R$ 1.234,56", "1234.56" ou "1.234,56" é normalizado. Prefixo "R$" fixo à esquerda dentro do campo, em texto suave. Texto alinhado à esquerda, como os outros campos. `inputMode="decimal"`.
- Campos de número inteiro (dia, quantidade de parcelas) só aceitam dígitos, com mínimo e máximo.

### Validação de texto (módulo 11a)

- Texto aceita letras (com acento), números, espaço e . , - / & ( ) ' # + %. Caractere fora disso não entra no campo enquanto digita; colado, mostra o erro "Use letras, números e pontuação simples." no campo. O servidor valida igual.

### Tags (módulo 11a)

- No modal: campo de tags com chips (`--color-chip-bg`/`--color-chip-text`, raio cheio, "×" para remover), sugestões das tags existentes numa lista como a do campo de seleção. Na Análise, chips pequenos ao lado da descrição, no máximo 3 visíveis e "+N".

### Banco de investimento (módulo 11a)

- No Patrimônio, os bancos aparecem em dois grupos com título pequeno: "Contas" e "Investimentos", cada um com subtotal; o total geral continua em destaque.

### Marca: símbolo e favicon (módulo 11a, correção)

- Símbolo **Caixinhas**: quadrado arredondado em degradê roxo (`#8b5cf0` → `#6a3fc8`) com três cartões empilhados e três barras subindo no cartão da frente. Arquivos em `public/brand/` (vêm prontos no pacote): `fintrack-mark.svg` (símbolo completo), `fintrack-favicon.svg` (versão simplificada para tamanhos pequenos), `favicon.ico` (16, 32 e 48), `favicon-32.png`, `favicon-16.png`, `apple-touch-icon.png` (180) e `fintrack-mark-128.png` (para o PDF).
- Uso: sidebar e telas de login/cadastro com o símbolo completo ao lado do nome "Fintrack" (o mesmo tamanho do quadrado "F" de hoje: 3.2rem; na sidebar recolhida, só o símbolo). Aba do navegador com o favicon. Cabeçalho do PDF dos relatórios com o PNG de 128 px exibido a 22 px. Não recolorir, não esticar, não pôr borda nem sombra.

### Análise: painel "Mais filtros" (módulo 11a, correção)

- Todos os campos numa linha só acima de 1280px: Buscar (mais largo, ~1.6×) e depois Categoria, Banco, Cartão (quando existir no painel), Pagamento, Tags e Valor, com a mesma largura. Abaixo de 1280px a grade quebra em linhas de três; abaixo de 900px, duas. Nada de campo sozinho numa segunda linha em tela larga.

### Meu perfil (módulo 11a, correção)

- Coluna única centralizada, largura máxima ~72rem, cards empilhados com espaço de 2.4rem.
- **Cabeçalho de identidade** (card): avatar grande com as iniciais (7.2rem, fundo `--color-accent`, texto `--color-accent-text`, 2.6rem/700), ao lado o nome (2.2rem/700), o e-mail em texto secundário e "Conta criada em novembro de 2026" em texto suave.
- **Dados** (card): Nome e E-mail lado a lado (uma coluna abaixo de 700px); botão "Salvar alterações" à direita, tamanho normal (não ocupa a largura toda), desabilitado enquanto nada mudou.
- **Segurança** (card): Senha atual numa linha; Nova senha e Confirmar lado a lado; botão de mostrar/ocultar (ícone `Eye`/`EyeOff`) dentro de cada campo de senha; dica "Mínimo de 8 caracteres." abaixo da nova senha; botão "Trocar senha" à direita, tamanho normal. Texto pequeno: "Ao trocar a senha, as outras sessões abertas são encerradas."
- **Aparência** (card): segmento "Escuro | Claro" ligado ao mesmo controle de tema do topo.
- Cada card com título (1.6rem/600) e uma linha de descrição em texto secundário. Sucesso em aviso discreto no próprio card (não um banner no topo).

## 7. Movimento

- Transição padrão: `var(--transition)` (0.25s, `cubic-bezier(0.4, 0, 0.2, 1)`) em cor, borda, fundo e `transform`.
- Hover de card clicável sobe 0.2rem. Botões mudam só de tom.
- Sem animação de entrada de página. Modal com fade de 0.2s.
- O brilho do cursor (`CursorGlow`) continua como está.
- Respeitar `prefers-reduced-motion` (já tratado em `app.css`).

## 8. Acessibilidade

Rótulo em todo campo, foco visível, o estado nunca depende só de cor, texto de apoio com contraste mínimo de 4.5:1 nos dois temas, ícone sem texto sempre com `aria-label`.

## 9. Mapa das telas

Imagens em `docs/design/dark/` (todas) e `docs/design/light/` (dashboard, analysis, planning, invoices, goals, manage-modal e transaction-edit). HTML com os valores reais em `docs/design/html/`.

| Arquivo | Tela | Módulo que implementa o conteúdo |
| --- | --- | --- |
| `login` | Entrar e criar conta | 3.5 |
| `manage`, `manage-modal` | Gerenciar e modal de categoria | 3.5 |
| `dashboard`, `dashboard-empty` | Dashboard cheio e vazio | 8 (estrutura visual no 3.5) |
| `analysis`, `transaction-modal`, `transaction-edit`, `transaction-delete`, `filters-open`, `filter-save` | Análise, nova transação, editar (com Duplicar e Excluir), confirmar exclusão, filtros abertos e salvar filtro | 4 |
| `planning` | Planejamento | 5 |
| `invoices` | Faturas (cartão, totais Lançado e Previsto, lista e histórico) | 6 |
| `net-worth`, `net-worth-closing` | Patrimônio e fechamento por banco | 7 |
| `goals`, `goal-detail` | Metas e meta em detalhe | 9 |
| `reports` | Relatórios | 10 |

As telas de módulos futuros são referência de visual. Só construa o que o módulo atual pede.
