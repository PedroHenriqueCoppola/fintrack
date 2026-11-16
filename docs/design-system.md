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
- **Sidebar flutuante:** largura 22.8rem, margem 1.4rem, canto 2.2rem, `position: sticky; top: 1.4rem; height: calc(100vh - 2.8rem)`, sempre com a altura da tela e com todos os 7 itens mais o botão Recolher no rodapé. Recolhida: 7.2rem, só ícones (com `title`). Item: ícone 1.8rem, texto 1.4rem, padding 1rem por 1.2rem, canto 1.4rem. Item ativo: fundo `--color-accent`, texto `--color-accent-text`, borda interna de 1px roxa a 22%. Logo: quadrado roxo 2.8rem com um F branco.
- **Área de conteúdo:** ocupa toda a largura restante. Largura máxima 168rem, centralizada em telas maiores (`--container-max`).
- **Linha do topo:** altura 4.4rem, alinhada à direita, com o botão de tema (3.8rem, quadrado arredondado) e o ícone de perfil (3.8rem, redondo). O ícone abre um menu pequeno com o nome do usuário e a opção **Sair**.
- **Cabeçalho da página:** título e subtítulo à esquerda, ações à direita (ex.: botão Nova transação, seletor de mês). Margem de 1rem em cima e 2.4rem embaixo.
- **Grade:** 12 colunas com espaço de 1.6rem. Use `align-items: start` quando os blocos tiverem alturas diferentes.
- **Desktop apenas.**

## 6. Componentes

### Card
Fundo `--color-card`, borda 1px `--color-border`, canto `--radius-xl`, sombra de card. Variantes: **destaque** (fundo `--color-card-highlight`, borda `--color-border-highlight`) para um bloco por tela, e **interno** (fundo `--color-card-inset`, borda `--color-border-input`, canto `--radius-lg`) para itens dentro de um card. Card clicável ou de lista: no hover sobe 0.2rem (`translateY(-0.2rem)`) e a borda vai para `--color-border-strong`, em 0.25s.

### Botão
Altura confortável, padding 1rem por 1.8rem, canto `--radius-md`, texto 1.35rem em 600. **Principal:** fundo `--color-primary`, texto branco, sombra de botão (sem gradiente). **Secundário (ghost):** fundo `--color-secondary`, sem sombra, texto `--color-text`. **Discreto (quiet):** sem fundo, texto `--color-text-muted`. **Destrutivo:** fundo `--color-destructive`. Desabilitado com opacidade 0.5. Foco visível com contorno `--color-primary-soft`.

### Campo de formulário
Rótulo acima, 1.25rem em 500, `--color-text-muted`. Campo com fundo `--color-card-inset`, borda `--color-border-input`, canto 1.3rem, padding 1rem por 1.4rem, texto 1.4rem. Valores em dinheiro alinhados à direita. Erro: texto em `--color-negative-text` abaixo do campo, nunca só a cor.

### Segmento e abas
Grupo de opções com fundo `--color-card-inset` (ou `--color-card` nas abas da página), borda fina, canto `--radius-md`, padding 0.4rem. Opção ativa com fundo `--color-accent` e texto `--color-accent-text`. As abas do Gerenciar usam esse mesmo estilo.

### Chip e selo
Canto 0.8rem, padding 0.2rem por 0.8rem, texto 1.15rem em 500. Padrão em `--color-chip-bg` e `--color-chip-text`. Variantes: neutra (fundo `--color-card-inset`, texto `--color-text-muted`) e concluída (verde suave, como o quadrado de ícone verde).

### Quadrado de ícone da categoria
38px (3.8rem), canto 1.2rem. A cor vem da categoria (HSL gravado no servidor). Fundo: `color-mix(in srgb, var(--item-color) 16%, var(--color-card))`. Ícone: `color-mix(in srgb, var(--item-color) 70%, var(--color-text))`. Sem cor: fundo `--color-card-inset`, ícone `--color-text-muted`. Ícones da biblioteca lucide, traço 1.75.

### Barra de progresso
Altura 0.6rem, canto total, trilho `--color-border-input`, preenchimento `--color-primary-fill`. Passou do valor: preenchimento `--color-destructive` e o texto dizendo que estourou.

### Modal
Fundo `--color-modal`, borda `--color-modal-border`, canto `--radius-2xl`, padding 2.6rem, sombra de modal, largura 56rem (62rem no de transação). Fundo escurecido com `--color-overlay`. Título 1.8rem em 700, descrição curta em `--color-text-muted`, campos com espaço de 1.4rem, rodapé com Cancelar (ghost) e ação principal à direita. Abre perto do topo (padding-top 7rem) e rola quando for alto. Entrada: fade de 0.2s. Mantém o foco preso e fecha com Esc, como o componente atual.

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

### Gráficos (usados a partir do módulo 8)
Linha principal em `--color-primary-soft`, 2.4px, pontas arredondadas, ponto final destacado, com área abaixo em gradiente de 22% de opacidade até 0. Linhas de grade em `--color-border`. Eixos em 1.1rem `--color-text-subtle`. Séries extras: azul `#6f9ee0`, verde-água `#7cc4ac`, laranja `#e6b08d` (no claro: `#2f64b0`, `#2a7a62`, `#a65a24`). Legenda sempre em texto. Rosca com espessura 14 e trilho `--color-border-input`. Vermelho só para despesas.

## 7. Movimento

- Transição padrão: `var(--transition)` (0.25s, `cubic-bezier(0.4, 0, 0.2, 1)`) em cor, borda, fundo e `transform`.
- Hover de card clicável sobe 0.2rem. Botões mudam só de tom.
- Sem animação de entrada de página. Modal com fade de 0.2s.
- O brilho do cursor (`CursorGlow`) continua como está.
- Respeitar `prefers-reduced-motion` (já tratado em `app.css`).

## 8. Acessibilidade

Rótulo em todo campo, foco visível, o estado nunca depende só de cor, texto de apoio com contraste mínimo de 4.5:1 nos dois temas, ícone sem texto sempre com `aria-label`.

## 9. Mapa das telas

Imagens em `docs/design/dark/` (todas) e `docs/design/light/` (dashboard, analysis, goals, manage-modal e transaction-edit). HTML com os valores reais em `docs/design/html/`.

| Arquivo | Tela | Módulo que implementa o conteúdo |
| --- | --- | --- |
| `login` | Entrar e criar conta | 3.5 |
| `manage`, `manage-modal` | Gerenciar e modal de categoria | 3.5 |
| `dashboard`, `dashboard-empty` | Dashboard cheio e vazio | 8 (estrutura visual no 3.5) |
| `analysis`, `transaction-modal`, `transaction-edit`, `transaction-delete`, `filters-open`, `filter-save` | Análise, nova transação, editar (com Duplicar e Excluir), confirmar exclusão, filtros abertos e salvar filtro | 4 |
| `planning` | Planejamento | 5 |
| `net-worth`, `net-worth-closing` | Patrimônio e fechamento por banco | 7 |
| `goals`, `goal-detail` | Metas e meta em detalhe | 9 |
| `reports` | Relatórios | 10 |

As telas de módulos futuros são referência de visual. Só construa o que o módulo atual pede.
