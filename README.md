# Numerologia Cósmica

Aplicação web para consultas numerológicas personalizadas, com integração ao OpenAI e Mercado Pago.

## Visão Geral

Esta aplicação permite aos usuários obter uma análise numerológica personalizada com base em seu nome completo e data de nascimento, incluindo:

1. Cálculo do número do caminho de vida, número de destino e ano pessoal
2. Integração com a API da OpenAI para gerar interpretações personalizadas
3. Processamento de pagamentos via Mercado Pago (PIX e cartões)
4. Geração e envio de PDFs personalizados por e-mail
5. Armazenamento de dados em banco de dados SQLite

## Requisitos

- PHP 7.4+ com extensões:
    - SQLite3
    - cURL
    - mbstring
    - PDO
- Biblioteca TCPDF (pode ser instalada via Composer)
- Servidor web (Apache ou Nginx)
- Acesso à internet para integração com APIs externas

## Instalação em Produção

### 1. Preparar o servidor

```bash
# Criar diretório do projeto
mkdir -p /var/www/html/cosmonumero

# Configurar permissões
chown -R www-data:www-data /var/www/html/cosmonumero
chmod -R 755 /var/www/html/cosmonumero

# Entrar no diretório
cd /var/www/html/cosmonumero
```

### 2. Fazer upload dos arquivos

Upload todos os arquivos do projeto para o servidor usando FTP, SCP ou outro método.

### 3. Instalar dependências (opcional)

```bash
# Se usar Composer para TCPDF
composer require tecnickcom/tcpdf
```

### 4. Configurar permissões de diretórios

```bash
# Criar diretórios necessários
mkdir -p logs temp pdfs database

# Configurar permissões
chmod -R 755 logs temp pdfs database
chown -R www-data:www-data logs temp pdfs database
```

### 5. Configurar credenciais das APIs

Edite o arquivo `api/api.php` e atualize as seguintes credenciais:

```php
// Credenciais da OpenAI
$openai_api_key = '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';
$openai_assistant_id = 'asst_CA8Yo9SaiNhBZVRcCJXQZX0I';

// Credenciais do Mercado Pago
$mp_access_token = 'APP_USR-8427023500547057-050113-5f3c2441f8b60a7ac66fd3c0ee0cfc71-1901198';
```

### 6. Configurar URLs de callback do Mercado Pago

Edite o arquivo `api/checkout/mercadopago.php` e atualize as URLs de callback:

```php
// URLs de callback
$base_url = 'https://ckao.in/cosmonumero';
```

### 7. Configurar regras de segurança

Edite o arquivo `.htaccess` e descomente as regras de segurança para produção:

```apache
# Redirecionar HTTP para HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Headers de segurança
Header set Content-Security-Policy "default-src 'self'; script-src 'self' https://sdk.mercadopago.com https://cdn.tailwindcss.com https://unpkg.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self' https://api.mercadopago.com;"
```

### 8. Configurar Virtual Host no Apache

```apache
<VirtualHost *:80>
    ServerName ckao.in
    ServerAlias www.ckao.in
    DocumentRoot /var/www/html
    
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/ckao.in-error.log
    CustomLog ${APACHE_LOG_DIR}/ckao.in-access.log combined
    
    <Directory /var/www/html/cosmonumero>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 9. Habilitar a configuração do site e reiniciar o Apache

```bash
a2ensite ckao.in.conf
systemctl restart apache2
```

## Estrutura de Diretórios

```
/cosmonumero/
├── index.html              # Frontend principal
├── app.js                  # JavaScript do frontend
├── api/
│   ├── api.php             # Endpoint principal da API
│   ├── checkout/           # Integração com Mercado Pago
│   ├── numerology/         # Cálculos e interpretações numerológicas
│   ├── pdf/                # Geração de PDF
│   └── database/           # Funções de banco de dados
├── logs/                   # Logs da aplicação
├── temp/                   # Arquivos temporários
├── pdfs/                   # PDFs gerados
└── database/               # Banco de dados SQLite
```

## Testes

Para testar a integração com o Mercado Pago:

1. Use a conta de teste do Mercado Pago
2. Efetue um pagamento de teste
3. Verifique os logs em `logs/checkout.log` e `logs/webhook.log`

Para testar a integração com a OpenAI:

1. Faça uma requisição de teste para `api/api.php` com ação "getTestResults"
2. Verifique a resposta e os logs em `logs/openai_error.log`

## Manutenção

### Backup

Faça backup regular do banco de dados:

```bash
cp database/numerology.db database/numerology.db.backup
```

### Logs

Monitore os arquivos de log regularmente:

```bash
tail -f logs/error.log
tail -f logs/checkout.log
tail -f logs/webhook.log
```

### Atualização

Para atualizar a aplicação, substitua os arquivos mantendo os diretórios:

```bash
# Fazer backup
cp -r /var/www/html/cosmonumero /var/www/html/cosmonumero.backup

# Atualizar arquivos
# Manter diretórios de dados
```

## Suporte

Para suporte, entre em contato com o desenvolvedor.
