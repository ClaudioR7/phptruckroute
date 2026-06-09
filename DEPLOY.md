# 🚀 TruckRoute Pro — Guia Completo de Deploy

## OPÇÃO 1: Hospedagem Compartilhada (mais fácil)
### Ex: Hostgator, KingHost, Locaweb, UOL Host

### Passo 1 — Criar banco de dados
1. Acesse o cPanel do seu host
2. Vá em **MySQL Databases** (ou Bancos de Dados MySQL)
3. Crie um banco: `truckroute`
4. Crie um usuário e anote: usuário, senha, host (geralmente `localhost`)
5. Dê **TODOS OS PRIVILÉGIOS** ao usuário no banco

### Passo 2 — Fazer upload dos arquivos
1. Acesse o **Gerenciador de Arquivos** no cPanel
2. Navegue até `public_html/`
3. Crie uma pasta chamada `truckroute`
4. Faça upload do ZIP e extraia — OU use **FTP** (FileZilla):
   - Host: `ftp.seudominio.com.br`
   - Usuário e senha: os do cPanel
   - Pasta destino: `/public_html/truckroute/`

### Passo 3 — Configurar o sistema
Edite o arquivo `includes/config.php`:
```php
define('DB_HOST', 'localhost');       // quase sempre localhost
define('DB_NAME', 'prefixo_truckroute'); // cPanel adiciona prefixo do usuário
define('DB_USER', 'prefixo_usuario');
define('DB_PASS', 'sua_senha_do_banco');
define('APP_URL', 'https://seudominio.com.br/truckroute');
```

### Passo 4 — Criar as tabelas
Acesse: `https://seudominio.com.br/truckroute/setup.php`
Clique em "Instalar Banco de Dados"
**⚠️ Delete o setup.php depois!**

### Passo 5 — Testar
Acesse: `https://seudominio.com.br/truckroute/`
Login: `admin@ammduarte.com.br` / `password`
**⚠️ Troque a senha imediatamente!**

---

## OPÇÃO 2: VPS / Servidor Próprio (mais controle)
### Ex: DigitalOcean, Vultr, AWS Lightsail, Contabo

### Passo 1 — Instalar dependências (Ubuntu/Debian)
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 mysql-server php8.2 php8.2-mysql \
     php8.2-mbstring php8.2-json php8.2-curl unzip
sudo a2enmod rewrite headers
sudo systemctl restart apache2
```

### Passo 2 — Criar banco de dados
```bash
sudo mysql -u root -p
```
```sql
CREATE DATABASE truckroute CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'truckuser'@'localhost' IDENTIFIED BY 'SenhaForte123!';
GRANT ALL PRIVILEGES ON truckroute.* TO 'truckuser'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Passo 3 — Subir os arquivos
```bash
# No servidor:
sudo mkdir -p /var/www/html/truckroute
cd /var/www/html/truckroute

# Via SCP do seu computador:
scp truckroute_pro.zip usuario@ip_do_servidor:/var/www/html/
ssh usuario@ip_do_servidor
cd /var/www/html
unzip truckroute_pro.zip -d truckroute
sudo chown -R www-data:www-data truckroute/
sudo chmod -R 755 truckroute/
sudo mkdir -p truckroute/logs
sudo chmod 777 truckroute/logs
```

### Passo 4 — Configurar Apache Virtual Host
```bash
sudo nano /etc/apache2/sites-available/truckroute.conf
```
Cole o conteúdo:
```apache
<VirtualHost *:80>
    ServerName seudominio.com.br
    DocumentRoot /var/www/html/truckroute
    <Directory /var/www/html/truckroute>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/truckroute_error.log
    CustomLog ${APACHE_LOG_DIR}/truckroute_access.log combined
</VirtualHost>
```
```bash
sudo a2ensite truckroute.conf
sudo systemctl reload apache2
```

### Passo 5 — SSL gratuito com Let's Encrypt
```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d seudominio.com.br
# Seguir as instruções na tela
```

### Passo 6 — Configurar e instalar
Edite `includes/config.php` com as credenciais do banco.
Acesse `https://seudominio.com.br/setup.php` e instale.

---

## OPÇÃO 3: Deploy gratuito na Render.com
*(PHP + MySQL — requer criar conta gratuita)*

1. Crie conta em https://render.com
2. Crie um "Web Service" → conecte ao GitHub com seu código
3. Crie um "MySQL" database gratuito
4. Configure as variáveis de ambiente no painel:
   - `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
   - `APP_URL` = a URL que a Render gerar
5. Deploy automático a cada `git push`

---

## ✅ Checklist pós-instalação

- [ ] Troque a senha do admin (Usuários → Editar)
- [ ] Troque a senha do motorista padrão
- [ ] Delete o arquivo `setup.php`
- [ ] Configure HTTPS (SSL)
- [ ] Configure SMTP real em `config.php` para envio de e-mails
- [ ] Faça backup semanal do banco de dados
- [ ] No cPanel: ative proteção de diretório em `/logs`
