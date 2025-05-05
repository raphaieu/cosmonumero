# CosmoNúmero - Análise Numerológica

Aplicação web para consultas numerológicas personalizadas, com integração ao OpenAI e Mercado Pago.

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4.svg?style=flat&logo=php" alt="PHP 8.3">
  <img src="https://img.shields.io/badge/Vue.js-3.0-4FC08D.svg?style=flat&logo=vue.js" alt="Vue.js 3">
  <img src="https://img.shields.io/badge/TailwindCSS-3-06B6D4.svg?style=flat&logo=tailwindcss" alt="TailwindCSS">
  <img src="https://img.shields.io/badge/SQLite-003B57.svg?style=flat&logo=sqlite" alt="SQLite">
</p>

## 📋 Visão Geral

Esta aplicação permite aos usuários obter uma análise numerológica personalizada com base em seu nome completo e data de nascimento, incluindo:

1. Cálculo do número do caminho de vida, número de destino e ano pessoal
2. Integração com a API da OpenAI para gerar interpretações personalizadas
3. Processamento de pagamentos via Mercado Pago (PIX e cartões)
4. Geração e envio de PDFs personalizados por e-mail
5. Armazenamento de dados em banco de dados SQLite

## ✨ Demo

Acesse [https://ckao.in/cosmonumero/](https://ckao.in/cosmonumero/) para ver a aplicação em produção.

## 🔧 Requisitos

- PHP 8.3+ com extensões:
    - SQLite3
    - cURL
    - mbstring
    - PDO
- Biblioteca TCPDF (instalada via Composer)
- Servidor web (Apache ou Nginx)
- Acesso à internet para integração com APIs externas

## 🚀 Instalação Local

### 1. Clonar o repositório

```bash
git clone https://github.com/seu-usuario/cosmonumero.git
cd cosmonumero
```

### 2. Instalar dependências

```bash
composer install
```

### 3. Configurar variáveis de ambiente

Copie o arquivo `.env.example` para `.env` e configure as variáveis:

```bash
cp .env.example .env
```

Edite o arquivo `.env` com suas credenciais:

```
ENV=development
MP_BASE_URL=http://localhost/cosmonumero
OPENAI_API_KEY=your_openai_api_key
MP_ACCESS_TOKEN=your_mercadopago_access_token
OPENAI_MODEL=gpt-4.1
MP_PUBLIC_KEY=your_mercadopago_public_key
MP_WEBHOOK_KEY=your_webhook_signature_key
```

### 4. Configurar permissões de diretórios

```bash
# Criar diretórios necessários
mkdir -p logs temp pdfs database

# Configurar permissões
chmod -R 755 logs temp pdfs database
```

### 5. Iniciar servidor de desenvolvimento

```bash
php -S localhost:8000
```

Acesse `http://localhost:8000` no seu navegador.

## 🌐 Instalação em Produção

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

### 2. Clonar o repositório

```bash
git clone https://github.com/seu-usuario/cosmonumero.git .
```

### 3. Instalar dependências

```bash
composer install --no-dev --optimize-autoloader
```

### 4. Configurar variáveis de ambiente

```bash
cp .env.example .env
```

Edite o arquivo `.env` com suas credenciais de produção:

```
ENV=production
MP_BASE_URL=https://seu-dominio.com/cosmonumero
OPENAI_API_KEY=your_openai_api_key
MP_ACCESS_TOKEN=your_mercadopago_access_token
OPENAI_MODEL=gpt-4.1
MP_PUBLIC_KEY=your_mercadopago_public_key
MP_WEBHOOK_KEY=your_webhook_signature_key
```

### 5. Configurar permissões de diretórios

```bash
# Criar diretórios necessários
mkdir -p logs temp pdfs database

# Configurar permissões
chmod -R 755 logs temp pdfs database
chown -R www-data:www-data logs temp pdfs database
```

### 6. Configuração do Servidor Web

#### Para Apache

O projeto já inclui um arquivo `.htaccess` com configurações de segurança. Verifique se seu servidor Apache tem o módulo `mod_rewrite` habilitado:

```bash
a2enmod rewrite
a2enmod headers
systemctl restart apache2
```

Conteúdo do `.htaccess`:

```apache
# Disable directory listing
Options -Indexes

# Force HTTPS
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{HTTPS} !=on
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>

# HTTP Security Headers
<IfModule mod_headers.c>
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "DENY"
  Header always set X-XSS-Protection "1; mode=block"
  Header always set Referrer-Policy "no-referrer-when-downgrade"
  Header always set Content-Security-Policy "default-src 'self'; script-src 'self' https://sdk.mercadopago.com https://cdn.tailwindcss.com https://unpkg.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self' https://api.mercadopago.com https://api.openai.com; frame-ancestors 'none';"
</IfModule>
```

Certifique-se de que o Apache esteja configurado para permitir substituições com `.htaccess`. Em `/etc/apache2/sites-available/000-default.conf` ou na configuração do seu virtual host, confirme que você tem:

```apache
<Directory /var/www/html>
    AllowOverride All
</Directory>
```

#### Para Nginx

Se você estiver usando Nginx, adicione estas configurações ao arquivo do seu site (geralmente em `/etc/nginx/sites-available/seu-site.conf`):

```nginx
server {
    # Configurações básicas
    listen 80;
    server_name seu-dominio.com;
    root /www/wwwroot/seu-dominio.com/cosmonumero;
    index index.php index.html;

    # Redirecionar HTTP para HTTPS
    location / {
        return 301 https://$host$request_uri;
    }
}

server {
    # Configurações HTTPS
    listen 443 ssl;
    server_name seu-dominio.com;
    root /www/wwwroot/seu-dominio.com/cosmonumero;
    index index.php index.html;

    # Configurações SSL
    ssl_certificate /etc/letsencrypt/live/seu-dominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/seu-dominio.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;

    # Cabeçalhos de segurança
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' https://sdk.mercadopago.com https://cdn.tailwindcss.com https://unpkg.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self' https://api.mercadopago.com https://api.openai.com; frame-ancestors 'none';" always;

    # Bloquear acesso a diretórios sensíveis
    location ~ ^/(logs|temp|pdfs|database)/ {
        deny all;
        return 403;
    }

    # Bloquear acesso a arquivos de log e banco de dados
    location ~* \.(log|db|sqlite)$ {
        deny all;
        return 403;
    }

    # Configuração PHP
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Desabilitar acesso a arquivos ocultos
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}
```

Após editar, teste e reinicie o Nginx:

```bash
sudo nginx -t
sudo systemctl restart nginx
```

## 🔄 Deploy Automático com GitHub Actions

Este projeto está configurado para deploy automático na Hostinger usando GitHub Actions. Cada push para a branch `main` inicia o pipeline de CI/CD.

### Configuração do GitHub Actions

1. No seu repositório GitHub, vá para **Settings** > **Secrets and variables** > **Actions**
2. Adicione as seguintes secrets:
   - `VPS_HOST`: Endereço IP da sua VPS
   - `VPS_USER`: Nome do usuário SSH (recomendado usar um usuário dedicado como 'deploy')
   - `VPS_SSH_PORT`: Porta SSH (geralmente 22)
   - `VPS_SSH_KEY`: Chave SSH privada para autenticação
   - `VPS_REMOTE_PATH`: Caminho para o diretório no servidor (ex: `/var/www/html/cosmonumero/`)

### Workflow de Deploy

O workflow `.github/workflows/deploy.yml` realiza as seguintes etapas:

1. Checkout do código-fonte
2. Instalação do PHP 8.3
3. Instalação das dependências com Composer
4. Deploy dos arquivos via rsync para a VPS

### Como criar um usuário de deploy na VPS

Para melhor segurança, crie um usuário dedicado para deploy:

```bash
# Conecte ao servidor como root
ssh root@seu_servidor

# Criar o usuário deploy
adduser deploy

# Adicionar o usuário ao grupo www-data
usermod -aG www-data deploy

# Configurar diretório para o projeto
mkdir -p /var/www/html/cosmonumero
chown -R deploy:www-data /var/www/html/cosmonumero
chmod -R 755 /var/www/html/cosmonumero

# Configurar permissões SSH
su - deploy
mkdir -p ~/.ssh
chmod 700 ~/.ssh
touch ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

Adicione a chave pública correspondente à chave privada configurada no GitHub ao arquivo `~/.ssh/authorized_keys` do usuário `deploy`.

## 📁 Estrutura de Diretórios

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

## 🧪 Testes

### Testar a integração com o Mercado Pago

1. Configure uma conta de teste do Mercado Pago
2. Efetue um pagamento de teste usando o ambiente de sandbox
3. Verifique os logs em `logs/checkout.log` e `logs/webhook.log`

### Testar a integração com a OpenAI

1. Faça uma requisição de teste para `api/api.php` com ação "getTestResults"
2. Verifique a resposta e os logs em `logs/openai_error.log`

## 🛠️ Manutenção

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

## 🔐 Segurança

- Todas as requisições POST são protegidas com tokens CSRF
- Configurações de cookies seguros (HttpOnly, Secure, SameSite)
- Sanitização de entrada de dados
- Rate limiting para prevenir ataques de força bruta

## 📄 Licença

Este projeto está licenciado sob a [MIT License](LICENSE).

## 📞 Suporte

Para suporte, abra uma issue no GitHub ou entre em contato através do email de suporte.