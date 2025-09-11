# MQ – Guide d'installation et de démarrage

Projet Symfony 7.3 (PHP 8.2) avec envoi d'e-mails (Mailer + Mailcatcher) et traitement asynchrone via Messenger + RabbitMQ (AMQP).

## Prérequis

- PHP 8.2 (CLI et serveur web) + Composer
- MySQL 8 (ou compatible) – ajuster `DATABASE_URL`
- Docker Desktop (pour Mailcatcher et RabbitMQ)
- Extension PHP `amqp` activée (pour le transport AMQP)


## Démarrage rapide

1. Installer les dépendances Composer
```powershell
composer install
```

2. Lancer les services Docker (Mailcatcher + RabbitMQ)
```powershell
# À la racine du projet
docker-compose up -d
```
- Mailcatcher (UI): http://localhost:1080
- SMTP Mailcatcher: 127.0.0.1:1025
- RabbitMQ (UI): http://localhost:15672 (login: guest / mdp: guest)

3. Configurer les variables d'environnement

Éditer `.env` et/ou `.env.dev`.

- Mailer vers Mailcatcher (dev):
```dotenv
MAILER_DSN="smtp://localhost:1025"
```

- RabbitMQ (AMQP): important, vhost par défaut encodé en `%2f`
```dotenv
RABBITMQ_DSN="amqp://guest:guest@127.0.0.1:5672/%2f"
```

4. Créer la base et migrer le schéma
```powershell
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate -n
```

5. Initialiser les transports Messenger (AMQP)
```powershell
php bin/console messenger:setup-transports -vv
```

6. Lancer le consommateur Messenger (dans un terminal dédié)
```powershell
php bin/console messenger:consume async -vv
```

## Points clés de la configuration

- `config/packages/messenger.yaml`
```yaml
framework:
  messenger:
    failure_transport: failed
    transports:
      async:
        dsn: '%env(RABBITMQ_DSN)%'
        options:
          auto_setup: true
          exchange:
            name: messages
            type: fanout
          queues:
            messages: ~
        retry_strategy:
          max_retries: 3
          multiplier: 2
      failed: 'doctrine://default?queue_name=failed'
    default_bus: messenger.bus.default
    routing:
      App\Message\MailNotification: async
      Symfony\Component\Mailer\Messenger\SendEmailMessage: async
```
- `MAILER_DSN` est lu dans `config/packages/mailer.yaml`.
- Le formulaire envoie un email via `HomeController` et/ou un message `App\Message\MailNotification` traité par `App\MessageHandler\MailNotificationHandler`.

## Docker Compose

`compose.yaml` fournit 2 services:
```yaml
a: |-
services:
  mailer:
    image: schickling/mailcatcher
    ports:
      - "1025:1025"
      - "1080:1080"
  rabbitmq:
    image: rabbitmq:4-management
    ports:
      - "5672:5672"   # AMQP
      - "15672:15672" # Management UI
```

## Scénario de test

1. Ouvrir l'UI RabbitMQ: http://localhost:15672 (guest/guest), vérifier l'exchange `messages` (type fanout) et la queue `messages`.
2. Ouvrir l'UI Mailcatcher: http://localhost:1080
3. Lancer le consumer: `php bin/console messenger:consume async -vv`
4. Soumettre le formulaire (route `app_home` → `/home`).
5. L'email doit apparaître dans Mailcatcher et la file RabbitMQ doit être consommée.

## Dépannage

- __No transport supports Messenger DSN "amqp://..."__
  - Installer le package et activer l'extension:
    ```powershell
    composer require symfony/amqp-messenger
    # Activer extension=amqp dans le php.ini utilisé par le CLI (voir php -i)
    php -m | findstr /I amqp
    ```

- __PRECONDITION_FAILED - inequivalent arg 'type' for exchange 'messages'__
  - L'exchange existe déjà avec un type différent.
  - Solutions:
    - Aligner le `type` dans `messenger.yaml` (ex: `fanout`) puis `messenger:setup-transports`.
    - Ou supprimer/renommer l'exchange dans l'UI RabbitMQ, puis relancer `setup-transports`.

- __Vhost / invalide__
  - Utiliser `/` encodé: `/%2f` dans `RABBITMQ_DSN`.

- __Le consumer ne consomme pas__
  - Lancer `php bin/console messenger:consume async -vv` dans un terminal séparé.
  - Vérifier l'UI RabbitMQ: messages en "Ready" qui passent en "Acked".

- __Fallback sans AMQP__
  - En cas d'indisponibilité d'AMQP, utiliser le transport Doctrine:
    ```yaml
    transports:
      async:
        dsn: 'doctrine://default?queue_name=async'
    routing:
      App\Message\MailNotification: async
    ```


## Commandes utiles (référence)
```powershell
# Dépendances
composer install
composer update symfony/amqp-messenger

# Cache
php bin/console cache:clear

# Doctrine
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate -n

# Messenger (AMQP)
php bin/console messenger:setup-transports -vv
php bin/console messenger:consume async -vv
php bin/console messenger:stop-workers
php bin/console debug:messenger

# Docker
docker-compose up -d
docker-compose down
```
