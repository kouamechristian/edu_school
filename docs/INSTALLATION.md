# 🚀 Guide d'Installation - EDU-SCHOOL

## 📋 Table des matières

1. [Prérequis](#prérequis)
2. [Installation locale](#installation-locale)
3. [Configuration](#configuration)
4. [Base de données](#base-de-données)
5. [Installation des assets](#installation-des-assets)
6. [Configuration avancée](#configuration-avancée)
7. [Déploiement en production](#déploiement-en-production)
8. [Dépannage](#dépannage)

## 🔧 Prérequis

### Logiciels requis

#### Serveur
- **PHP** >= 8.1
- **Composer** >= 2.0
- **MySQL** >= 8.0 ou **MariaDB** >= 10.5
- **Apache** >= 2.4 ou **Nginx** >= 1.18

#### Extensions PHP requises
```bash
php -m | grep -E 'pdo_mysql|mbstring|xml|ctype|iconv|intl|gd|zip|curl'
```

Liste complète:
- `pdo_mysql` - Connexion base de données
- `mbstring` - Manipulation de chaînes
- `xml` - Traitement XML
- `ctype` - Validation de caractères
- `iconv` - Conversion d'encodage
- `intl` - Internationalisation
- `gd` - Traitement d'images
- `zip` - Compression de fichiers
- `curl` - Requêtes HTTP

#### Outils optionnels (recommandés)
- **Node.js** >= 18 (pour asset compilation)
- **Git** (pour versioning)
- **Redis** (pour cache/sessions)
- **Supervisor** (pour workers)

### Configuration système minimale

#### Développement
- RAM: 4 GB
- Disk: 10 GB
- CPU: 2 cores

#### Production
- RAM: 8 GB minimum, 16 GB recommandé
- Disk: 50 GB SSD
- CPU: 4 cores minimum

## 💻 Installation locale

### 1. Cloner le projet

```bash
# HTTPS
git clone https://github.com/votre-org/edu-school.git

# SSH
git clone git@github.com:votre-org/edu-school.git

# Entrer dans le dossier
cd edu-school
```

### 2. Installer les dépendances

```bash
# Dépendances PHP
composer install

# Si problème avec les extensions manquantes
composer install --ignore-platform-reqs  # ⚠️ Uniquement pour test

# Vérifier l'installation
composer validate
```

### 3. Configuration de l'environnement

```bash
# Copier le fichier d'environnement
cp .env .env.local

# Générer une clé secrète
php bin/console secret:generate-keys

# Ou manuellement
APP_SECRET=$(php -r "echo bin2hex(random_bytes(16));")
echo "APP_SECRET=$APP_SECRET" >> .env.local
```

### 4. Éditer `.env.local`

```bash
# Éditeur de votre choix
nano .env.local
# ou
code .env.local
```

#### Configuration minimale

```env
# Environment
APP_ENV=dev
APP_DEBUG=true
APP_SECRET=votre_secret_genere_ici

# Database
DATABASE_URL="mysql://root:password@127.0.0.1:3306/edu_school?serverVersion=8.0&charset=utf8mb4"

# Mailer (exemple avec Gmail)
MAILER_DSN=gmail://username:password@default
# ou SMTP
MAILER_DSN=smtp://user:pass@smtp.example.com:587

# Messenger (optionnel)
MESSENGER_TRANSPORT_DSN=doctrine://default
```

## 🗄️ Base de données

### 1. Créer la base de données

```bash
# Créer la base
php bin/console doctrine:database:create

# Vérifier la connexion
php bin/console doctrine:schema:validate
```

### 2. Exécuter les migrations

```bash
# Voir les migrations en attente
php bin/console doctrine:migrations:status

# Exécuter toutes les migrations
php bin/console doctrine:migrations:migrate

# Ou migration par migration
php bin/console doctrine:migrations:migrate --step=1
```

### 3. Charger les données de test (optionnel)

```bash
# Charger les fixtures
php bin/console doctrine:fixtures:load

# Confirmer avec 'yes' ou utiliser --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
```

#### Créer un utilisateur admin manuellement

```bash
# Créer un admin
php bin/console app:create-admin

# Ou utiliser la commande make:user
php bin/console make:user
```

**Exemple de script SQL pour créer un admin**:
```sql
INSERT INTO user (username, email, roles, password, is_active, created_at)
VALUES (
    'admin',
    'admin@edu-school.com',
    '["ROLE_SUPER_ADMIN"]',
    '$2y$13$hash_du_mot_de_passe',  -- Utiliser password_hash() en PHP
    1,
    NOW()
);
```

**Générer le hash du mot de passe**:
```bash
php bin/console security:hash-password
```

## 🎨 Installation des assets

### 1. Assets Symfony

```bash
# Installer les assets dans public/
php bin/console assets:install public

# Avec lien symbolique (Linux/Mac)
php bin/console assets:install public --symlink
```

### 2. ImportMap (Symfony 6.4+)

```bash
# Installer les dépendances JavaScript
php bin/console importmap:install

# Mettre à jour
php bin/console importmap:update
```

### 3. CKEditor

```bash
# Installer CKEditor
php bin/console ckeditor:install

# Avec options
php bin/console ckeditor:install --clear=drop
```

### 4. elFinder

```bash
# Installer elFinder
php bin/console elfinder:install

# Créer les dossiers nécessaires
mkdir -p public/uploads/elfinder
chmod -R 755 public/uploads
```

## 🚀 Lancer l'application

### Avec Symfony CLI (recommandé)

```bash
# Installer Symfony CLI
wget https://get.symfony.com/cli/installer -O - | bash

# Lancer le serveur
symfony server:start

# En arrière-plan
symfony server:start -d

# Voir les logs
symfony server:log

# Arrêter le serveur
symfony server:stop
```

### Avec PHP built-in server

```bash
# Lancer sur le port 8000
php -S localhost:8000 -t public/

# Sur un port personnalisé
php -S localhost:8080 -t public/
```

### Avec Docker

```bash
# Build
docker-compose build

# Démarrer
docker-compose up -d

# Voir les logs
docker-compose logs -f

# Arrêter
docker-compose down
```

## ⚙️ Configuration avancée

### Cache

#### Configuration Redis (recommandé pour production)

```env
# .env.local
REDIS_URL=redis://localhost:6379
```

```yaml
# config/packages/cache.yaml
framework:
    cache:
        app: cache.adapter.redis
        default_redis_provider: '%env(REDIS_URL)%'
```

#### Vider le cache

```bash
# Vider tous les caches
php bin/console cache:clear

# Cache de production
php bin/console cache:clear --env=prod

# Pools spécifiques
php bin/console cache:pool:clear cache.app
```

### Sessions

#### Stockage en base de données

```bash
# Créer la table de sessions
php bin/console doctrine:schema:update --force --em=default
```

```yaml
# config/packages/framework.yaml
framework:
    session:
        handler_id: Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler
```

### Messenger (Queues asynchrones)

```bash
# Consommer les messages
php bin/console messenger:consume async

# Avec Supervisor (production)
sudo supervisorctl start messenger-consume:*
```

**Configuration Supervisor**:
```ini
; /etc/supervisor/conf.d/messenger.conf
[program:messenger-consume]
command=php /var/www/edu-school/bin/console messenger:consume async --time-limit=3600
user=www-data
numprocs=2
startsecs=0
autostart=true
autorestart=true
process_name=%(program_name)s_%(process_num)02d
```

### Cron Jobs

```bash
# Ouvrir crontab
crontab -e
```

**Tâches recommandées**:
```cron
# Nettoyage du cache tous les jours à 3h
0 3 * * * cd /var/www/edu-school && php bin/console cache:clear --env=prod

# Envoi des notifications quotidiennes à 8h
0 8 * * * cd /var/www/edu-school && php bin/console app:send-daily-notifications

# Backup quotidien à 2h
0 2 * * * /usr/local/bin/backup-edu-school.sh

# Nettoyage des fichiers temporaires
0 4 * * * find /var/www/edu-school/var/cache/prod -mtime +7 -delete
```

## 🌐 Déploiement en production

### 1. Préparer l'environnement

```bash
# Basculer en mode production
APP_ENV=prod
APP_DEBUG=false
```

### 2. Optimiser l'application

```bash
# Installer les dépendances de production uniquement
composer install --no-dev --optimize-autoloader

# Vider et préchauffer le cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Optimiser l'autoloader
composer dump-autoload --no-dev --classmap-authoritative
```

### 3. Configuration Apache

#### Virtual Host

```apache
<VirtualHost *:80>
    ServerName edu-school.com
    ServerAlias www.edu-school.com
    
    DocumentRoot /var/www/edu-school/public
    
    <Directory /var/www/edu-school/public>
        AllowOverride All
        Require all granted
        
        # Réécriture d'URL
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteRule ^(.*)$ index.php [QSA,L]
        </IfModule>
    </Directory>
    
    # Logs
    ErrorLog ${APACHE_LOG_DIR}/edu-school-error.log
    CustomLog ${APACHE_LOG_DIR}/edu-school-access.log combined
    
    # Security headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
```

#### SSL (HTTPS)

```bash
# Installer Certbot
sudo apt install certbot python3-certbot-apache

# Obtenir un certificat SSL
sudo certbot --apache -d edu-school.com -d www.edu-school.com

# Auto-renouvellement
sudo certbot renew --dry-run
```

### 4. Configuration Nginx

```nginx
server {
    listen 80;
    server_name edu-school.com www.edu-school.com;
    root /var/www/edu-school/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    # Security headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Logs
    error_log /var/log/nginx/edu-school-error.log;
    access_log /var/log/nginx/edu-school-access.log;
}
```

### 5. Permissions des fichiers

```bash
# Propriétaire
sudo chown -R www-data:www-data /var/www/edu-school

# Permissions
sudo find /var/www/edu-school -type d -exec chmod 755 {} \;
sudo find /var/www/edu-school -type f -exec chmod 644 {} \;

# Dossiers en écriture
sudo chmod -R 775 /var/www/edu-school/var
sudo chmod -R 775 /var/www/edu-school/public/uploads
```

### 6. Optimisations PHP

**php.ini (production)**:
```ini
; Performance
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  ; Production uniquement

; Uploads
upload_max_filesize=50M
post_max_size=50M
max_execution_time=300

; Memory
memory_limit=512M

; Sessions
session.gc_maxlifetime=3600
session.cookie_httponly=1
session.cookie_secure=1  ; Si HTTPS
```

### 7. Backup automatique

**Script de backup** (`/usr/local/bin/backup-edu-school.sh`):
```bash
#!/bin/bash

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/edu-school"
APP_DIR="/var/www/edu-school"
DB_NAME="edu_school"
DB_USER="root"
DB_PASS="password"

# Créer le dossier de backup
mkdir -p $BACKUP_DIR

# Backup de la base de données
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup des fichiers uploadés
tar -czf $BACKUP_DIR/uploads_$DATE.tar.gz $APP_DIR/public/uploads

# Supprimer les backups de plus de 30 jours
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
```

```bash
# Rendre exécutable
chmod +x /usr/local/bin/backup-edu-school.sh
```

## 🐛 Dépannage

### Erreur: "An exception occurred in driver"

**Cause**: Connexion à la base de données échouée

**Solution**:
```bash
# Vérifier la connexion MySQL
mysql -u root -p -e "SELECT 1"

# Vérifier DATABASE_URL dans .env.local
grep DATABASE_URL .env.local

# Tester la connexion Doctrine
php bin/console doctrine:query:sql "SELECT 1"
```

### Erreur: "Unable to write in the cache directory"

**Cause**: Permissions insuffisantes

**Solution**:
```bash
# Vérifier les permissions
ls -la var/

# Corriger les permissions
chmod -R 775 var/
chown -R www-data:www-data var/

# Vider le cache
php bin/console cache:clear
```

### Erreur 500: "Internal Server Error"

**Solution**:
```bash
# Voir les logs Apache
tail -f /var/log/apache2/error.log

# Voir les logs Symfony
tail -f var/log/dev.log  # ou prod.log

# Activer le mode debug
APP_DEBUG=true
```

### Extensions PHP manquantes

```bash
# Ubuntu/Debian
sudo apt install php8.1-mysql php8.1-gd php8.1-intl php8.1-xml php8.1-mbstring php8.1-zip php8.1-curl

# CentOS/RHEL
sudo yum install php-mysql php-gd php-intl php-xml php-mbstring php-zip php-curl

# Redémarrer le serveur
sudo systemctl restart apache2  # ou php8.1-fpm
```

### Composer out of memory

```bash
# Augmenter la limite de mémoire temporairement
php -d memory_limit=-1 /usr/local/bin/composer install

# Ou définir dans php.ini
memory_limit=512M
```

## 📚 Commandes utiles

### Développement

```bash
# Créer une entité
php bin/console make:entity

# Créer un contrôleur
php bin/console make:controller

# Créer un formulaire
php bin/console make:form

# Créer une migration
php bin/console make:migration

# Créer un command
php bin/console make:command
```

### Maintenance

```bash
# Lister toutes les routes
php bin/console debug:router

# Lister les services
php bin/console debug:container

# Lister les event listeners
php bin/console debug:event-dispatcher

# Vérifier la sécurité
composer audit
symfony check:security
```

## 🔒 Checklist de sécurité

- [ ] APP_DEBUG=false en production
- [ ] APP_ENV=prod en production
- [ ] Changer APP_SECRET
- [ ] HTTPS activé
- [ ] Certificat SSL valide
- [ ] Headers de sécurité configurés
- [ ] Permissions fichiers correctes
- [ ] Backup automatique configuré
- [ ] Logs activés et surveillés
- [ ] Rate limiting activé
- [ ] CSRF protection activée
- [ ] SQL injection protection (ORM)
- [ ] XSS protection (Twig auto-escape)

---

**Support** : support@edu-school.com  
**Documentation** : https://docs.edu-school.com  
**Dernière mise à jour** : Octobre 2025

