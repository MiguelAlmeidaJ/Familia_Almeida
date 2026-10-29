# Rebranding Bemora — plano em 3 etapas

Identidade baseada no manual da marca (outubro de 2026):
- Verde profundo #06463F; petróleo #168F82; menta #34C9A1;
  verde suave #AFDE8E; neve #F7FAF8; grafite #20332F.
- Títulos e navegação Nunito Sans (600–800), conteúdos Inter (400–600).
- Logo completa sobre superfícies claras; símbolo isolado em ícones.
- Assinatura “by Anoar” apenas secundária.

## Etapa 1 — identidade e casca visual (concluída no código)
- Adicionar artes da marca e tokens com CSS de compatibilidade.
- Atualizar logo, tipografia e cores na navegação familiar e administrativa.
- Aplicar Bootstrap 5 à página de login e painel financeiro, mantendo CSS legado.
- Usar Bootstrap Icons no menu administrativo.
- Não alterar regras financeiras nem executar migrations.

## Etapa 2 — componentes financeiros (implementada no código)
- Adicionado arquivo de estilo financeiro isolado `assets/bemora-finance.css`.
- Bootstrap 5 e Bootstrap Icons carregados em Painel, Ganhos e Gastos, Contas Previstas, Categorias e Minha Conta.
- Aplicadas classes Bootstrap nos controles principais, sem alterar ações financeiras.
- Navegação de usuários passou a usar Bootstrap Icons.
- Ainda requer inspeção visual no servidor em desktop/mobile e testes manuais de regressão.

## Etapa 3 — acabamento, PWA e revisão
- Concluir telas de conta, admin/tenants, configurações e estados vazios.
- Revisar responsividade e contraste em todas as páginas, inclusive arquivos offline.
- Atualizar manifest e assets do PWA sem perder dados locais.
- Testes visuais comparativos e regressão de fluxos financeiros.

## Antes de publicar
- Executar verificações PHP e conferir interface em desktop/mobile.
- Atualizar servidor por git pull, limpar cache da aplicação e avaliar novas capturas.
- Novos tenants continuam desabilitados até isolamento de dados concluído.

## Dependência de CDN
Bootstrap e fontes Google são carregados via CDN. A área offline do PWA permanece
inalterada na etapa 1; a estratégia offline será tratada na etapa 3.
