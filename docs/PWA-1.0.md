# Bemora PWA 1.0

O PWA é uma camada instalável sobre o site PHP existente. Não substitui rotas, autenticação, checkout, regras financeiras ou funcionalidades de compras.

## Instalação
- Android (Chrome): abra o Bemora, entre na conta e use **Instalar Bemora** quando aparecer, ou a opção de instalação no menu do navegador.
- iPhone (Safari): abra o Bemora, toque em **Compartilhar** e em **Adicionar à Tela de Início**.
- Requer HTTPS em produção. O app inicia em `/painel`; quando não autenticado, o servidor continua responsável pelo redirecionamento para login.

## Cache e privacidade
O Service Worker mantém o fluxo legado de compras previamente implementado. Para as demais rotas, não armazene respostas HTML autenticadas, endpoints financeiros, dados de sessão ou checkout. Na falha de rede, a navegação mostra apenas `/pwa-offline.html`, uma tela pública sem dados do cliente.

## Checklist de homologação
1. Validar manifesto e instalação Android e iOS.
2. Entrar, sair e alternar entre contas e famílias; garantir que nenhuma página financeira apareça offline.
3. Confirmar funcionamento de painel, lançamentos, compras, assinatura e checkout Asaas.
4. Testar rede desconectada e retorno da conexão.
5. Testar atualização do Service Worker e navegação após novo deploy.
6. Verificar política de sessão, CSP e comportamento de armazenamento local em aparelhos compartilhados.

Observação: recursos de sincronização de compras offline já existentes devem passar por revisão de isolamento multi-tenant antes de serem promovidos como parte oficial do PWA.
