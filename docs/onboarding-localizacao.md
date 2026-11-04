# Bemora — etapa de localização no primeiro acesso

## Fluxo

- Assinatura com **1 acesso**: 1. Seus dados → 2. Localização → Criar acesso.
- Assinatura com **2 ou mais acessos**: 1. Integrantes → 2. Administrador → 3. Localização → Criar acessos.
- A quantidade de integrantes continua definida exclusivamente pelo convite, não pelo navegador.

## Dados solicitados

- **CEP**: obrigatório, formato `00000-000` (armazenado como 8 dígitos).
- **Endereço**: logradouro, obrigatório, até 180 caracteres.
- **Estado**: seleção das 27 unidades da federação.
- **Cidade**: seleção dos municípios da UF pelo serviço de localidades do IBGE. Caso o IBGE não responda, um campo de cidade manual permite concluir o cadastro.

O botão **Buscar CEP** consulta `https://viacep.com.br/ws/{cep}/json/`, preenchendo logradouro, UF e cidade quando disponíveis. Quando não encontra o CEP ou a API está indisponível, o usuário pode preencher os dados manualmente.

## Persistência e integridade

A migration `20261104_047_tenant_addresses.php` cria a tabela `tenant_addresses`, vinculada ao `tenant_id` (um endereço por espaço). O registro de localização ocorre na mesma transação de ativação do espaço e dos integrantes, antes de consumir o convite. Erros de banco anulam toda a operação.

A validação do servidor verifica CEP, logradouro, UF e cidade. A busca externa serve para facilitar o preenchimento e não substitui uma comprovação oficial de endereço. O servidor não depende do ViaCEP ou do IBGE para concluir a ativação.

## Implantação

1. Atualize a `main` com `git pull origin main`.
2. Execute em **Administração → Configurações → Manutenção** a migration `20261104_047_tenant_addresses`.
3. Teste um convite com uma pessoa e outro com três pessoas, incluindo um CEP válido, um CEP desconhecido e a seleção manual de UF/cidade.
4. Confirme que o cadastro cria os usuários e um único registro de endereço para o espaço criado.
5. Verifique a responsividade no celular e que o convite só é marcado como usado após todas as etapas concluídas.

## Melhorias futuras possíveis

Número do imóvel, complemento e bairro como campos opcionais; edição do endereço no perfil; localização para personalizar oportunidades regionais. Evite exigir dados adicionais até haver uma necessidade clara.
