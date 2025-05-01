# Numerologia Cósmica

Aplicação web para consultas numerológicas personalizadas, com integração ao OpenAI e Mercado Pago.

## Visão Geral

Esta aplicação permite aos usuários obter uma análise numerológica personalizada com base em seu nome completo e data de nascimento. O sistema:

1. Calcula o número do caminho de vida, número de destino e ano pessoal
2. Integra com a API da OpenAI para gerar interpretações personalizadas
3. Processa pagamentos via Mercado Pago (PIX)
4. Gera e envia PDFs personalizados por e-mail
5. Armazena os dados em um banco de dados SQLite

## Requisitos

- PHP 7.4+
- SQLite3
- Extensão cURL do PHP
- Composer (opcional, para gerenciar dependências)

## Estrutura do Projeto

```
numerologia-cosmica/
├── index.html              # Frontend principal
├── app.js                  # JavaScript do frontend
├── api.php                 # Endpoint principal da API
├── openai.php              # Funções para integração com OpenAI
├── mercadopago.php         # Funções para integração com Mercado Pago
├── pdf-generator.php       # Funções para geração de PDF
├── database.php            # Funções para interação com banco de dados
├── database/               # Diretório do banco de dados
│   └── numerology.db       # Banco de dados SQLite
├── pdfs/                   # Diretório para armazenar PDFs gerados
├── temp/                   # Diretório para arquivos temporários
└── vendor/                 # Dependências (se usar Composer)
    └── fpdf/              # Biblioteca FPDF para geração de PDFs
```

## Instalação

1. Clone este repositório:
```bash
git clone https://github.com/seu-usuario/numerologia-cosmica.git
cd numerologia-cosmica
```

2. Instale as dependências:
```bash
# Se usar Composer:
composer require setasign/fpdf

# Se não usar Composer, baixe manualmente o FPDF:
mkdir -p vendor/fpdf
wget http://www.fpdf.org/en/download/fpdf184.tgz -O fpdf.tgz
tar -xvzf fpdf.tgz -C vendor/fpdf
rm fpdf.tgz
```

3. Crie os diretórios necessários:
```bash
mkdir -p database pdfs temp
chmod 755 database pdfs temp
```

4. Configure as chaves de API:
Edite o arquivo `api.php` e atualize as seguintes linhas:
```php
$openai_api_key = 'sua-chave-da-openai';
$mp_access_token = 'seu-token-do-mercado-pago';
```

5. Configurar servidor web:
Configure seu servidor web (Apache/Nginx) para apontar para o diretório do projeto ou use o servidor embutido do PHP:
```bash
php -S localhost:8000
```

## Uso

1. Acesse a aplicação no navegador (exemplo: `http://localhost:8000`)
2. Preencha o formulário com nome completo e data de nascimento
3. Clique em "Consultar Agora"
4. Aguarde o processamento do pagamento
5. Visualize a análise numerológica
6. Opcionalmente, informe e-mail para receber o PDF ou faça o download direto

## Personalização

### Frontend

O frontend utiliza TailwindCSS via CDN para estilização. Você pode modificar as cores e estilos no arquivo `index.html`.

### Cálculos Numerológicos

Os cálculos numerológicos estão implementados no arquivo `api.php`. Você pode ajustar as fórmulas de cálculo conforme necessário.

### Interpretações

As interpretações numerológicas são obtidas via OpenAI ou de um banco de dados local (fallback). Você pode ajustar os prompts ou adicionar mais interpretações pré-definidas.

## Segurança

Esta aplicação é uma demonstração e deve ser aprimorada para uso em produção:

1. Implemente validação adequada de entrada
2. Use HTTPS para todas as comunicações
3. Armazene chaves de API em variáveis de ambiente
4. Implemente proteção contra CSRF
5. Adicione sistema de autenticação para acesso administrativo

## Licença

Este projeto está licenciado sob a licença MIT. Veja o arquivo LICENSE para mais detalhes.
