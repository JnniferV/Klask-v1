# KLASK

Application Symfony pour un salon d'orientation : inscription élève, questionnaire 6 sphères,
carte interactive, scan QR, suivi accompagnateur et back-office admin.

| PHP 8.2+ | Symfony 7.4 | MySQL 8 | AssetMapper (sans Node.js) | Mercure + Caddy (Docker) |

---

## Prérequis

Installer **avant** de cloner :

- PHP 8.2+ et **Composer**
- **Symfony CLI** — [symfony.com/download](https://symfony.com/download)
- **MySQL 8** — WAMP suffit sous Windows (MySQL seul, pas Apache)
- **Docker Desktop**
- **mkcert** — **à télécharger par chaque dev** (pas sur GitHub) :
  [releases mkcert](https://github.com/FiloSottile/mkcert/releases) → renommer en `mkcert.exe`
  → placer dans `scripts/tools/` (dossier vide dans le repo)

MySQL et Docker Desktop doivent **tourner** avant de lancer l'app.

---

## Installation (après clone)

Suivre **dans l'ordre**. Ne pas lancer `migrate` seul : toujours `composer db-reset` sur une base fraîche.

```bash
git clone <url-du-repo> klask-dev
cd klask-dev
composer install          # recrée vendor/ + public/bundles/ + assets/vendor/
cp .env.example .env      # Windows CMD : copy .env.example .env
composer db-reset         # drop + create + migrate + fixtures + QR codes
composer db-test          # base klask_test (obligatoire avant phpunit)
.\scripts\start-mobile-https.bat
```

### Environnement (`.env`)

Seul **`.env.example`** est sur GitHub. Après clone : `cp .env.example .env`.

Adapter dans `.env` ou `.env.local` (recommandé pour l'IP mobile) :

```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/klask?serverVersion=8.0.32&charset=utf8mb4"
APP_URL=https://127.0.0.1
```

> **Ne jamais committer** `.env`, `.env.local`, `.env.test` — ni mots de passe MySQL,
> `APP_SECRET` de prod, ni vrais JWT Mercure. Placeholders dans `.env.example` suffisent
> (`change_me`, `!ChangeThisMercureHubJWTSecretKey!`).

`composer db-reset` charge les comptes de démo et regénère les QR codes selon `APP_URL`.

> Après un `git pull` qui touche `migrations/` ou les fixtures :
> `composer install` → `composer db-reset` → `composer db-test`

---

## Tester sur PC

1. Lancer `.\scripts\start-mobile-https.bat` (certificat + Docker + Symfony)
2. Ouvrir **`https://127.0.0.1`** — **sans port** (le proxy écoute sur 443)
3. Ne pas ouvrir `http://localhost:8000` (Symfony en clair, réservé au proxy)

**Hors Windows** :

```bash
docker compose up -d mercure proxy
symfony server:start --port=8000 --listen-ip=0.0.0.0 --no-tls --no-workers
```

Toujours `docker compose up -d mercure proxy` — **pas** `up -d` seul (Postgres → `could not find driver`).

---

## Tester sur mobile (scan QR + caméra)

Le scan exige **HTTPS** et le **même WiFi** que le PC.

1. Trouver l'IP LAN du PC : `ipconfig` → **Adresse IPv4** (ex. `192.168.1.17`)
2. Mettre la même IP dans :
    - `scripts/start-mobile-https.bat` → `set LAN_IP=...`
    - `.env.local` → `APP_URL=https://192.168.1.17`
3. Si l'IP a changé : supprimer `public/certs/dev.pem`, relancer le script
4. `composer db-reset` — regénère les QR codes avec la bonne URL
5. Relancer `.\scripts\start-mobile-https.bat`
6. Sur le téléphone : importer `public/certs/symfony-rootCA.pem` comme certificat CA
    - **iOS** : Réglages → Général → VPN et gestion de l'appareil, puis Réglages de confiance des certificats
    - **Android** : Paramètres → Sécurité → Installer un certificat → CA
7. Ouvrir **`https://<ip-lan>`** sur le mobile

---

## Comptes de test

| Rôle               | Parcours                                   | Identifiants                                         |
| ------------------ | ------------------------------------------ | ---------------------------------------------------- |
| **Élève**          | `/inscription` → `/questionnaire` → `/map` | Code **GRP0001** ou **GRP0002** (pseudo animal auto) |
| **Accompagnateur** | `/login`                                   | `accompagnateur@klask.fr` / `AccKlask2026!`          |
| **Admin**          | `/login` → `/admin`                        | `admin@klask.fr` / `AdminKlask2026!`                 |

Session élève : 24 h. Pas d'email ni mot de passe pour les élèves.

---

## Commandes utiles

```bash
composer db-reset                  # dev : drop + create + migrate + fixtures
composer db-test                   # test : drop + create + migrate (klask_test)
php bin/phpunit                    # après composer db-test
composer refresh                   # cache + migrations + validate schema
php bin/console app:notify-upcoming  # alertes atelier/conf (à croner)
```

Qualité (`.php-cs-fixer.dist.php`, `phpstan.dist.neon`) :

```bash
vendor/bin/php-cs-fixer fix
vendor/bin/phpstan analyse --memory-limit=1G
```

---

## Dépannage

| Symptôme                      | Solution                                                   |
| ----------------------------- | ---------------------------------------------------------- |
| Pas de `dev.pem` / proxy KO   | `mkcert.exe` manquant dans `scripts/tools/`                |
| `could not find driver`       | `docker compose up -d mercure proxy` (pas `up -d` seul)    |
| 502 Bad Gateway               | Symfony arrêté, ou sans `--no-tls` / `--listen-ip=0.0.0.0` |
| Carte / pokes figés           | `docker compose up -d mercure proxy`                       |
| Scan caméra refusé            | URL en `http://` → passer par `https://`                   |
| Mobile : page bloquée         | Importer `symfony-rootCA.pem` sur le téléphone             |
| QR codes mauvaise URL         | Corriger `APP_URL` → `composer db-reset`                   |
| Migration / table manquante   | `composer db-reset` (ne pas migrer sur une base existante) |
| PHPUnit : colonne introuvable | `composer db-test`                                         |
| Port 8000 occupé              | `symfony server:stop`, tuer les `php-cgi.exe`, relancer    |

---
