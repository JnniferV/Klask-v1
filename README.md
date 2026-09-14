# KLASK

Application Symfony pour un salon d'orientation : inscription élève, questionnaire 6 sphères,
carte interactive, scan QR, suivi accompagnateur et back-office admin.

| PHP 8.2+ | Symfony 7.4 | MySQL 8 | AssetMapper (sans Node.js) | Mercure + Caddy (Docker) |

---

## Fonctionnalités

- **Inscription** : code groupe (`GRP0001`…), pseudo animal auto, scan QR possible avant inscription
- **Questionnaire** : 6 affirmations → notes 1–6 sans répétition → parcours personnalisé (6 étapes)
- **Carte** : sphères, stands, atelier/conférence, capacité temps réel, thème clair/sombre
- **Scan QR** : caméra in-app ou scanner natif du téléphone (URL HTTPS dans le QR)
- **Temps réel (Mercure)** : scores groupe, pokes accompagnateur, alertes élève, notifications admin
- **Sidebar accompagnateur** : grille élèves, poke, alertes inactivité / scan rapide
- **Bilan PDF** : élève et groupe (débloqué après 3 stands ou 1 h de présence)
- **Admin (EasyAdmin)** : activités, placement sur la carte, notifs (immédiates ou programmées),
  paramètres métier, stats, reset event
- **Staff** : mot de passe oublié (nécessite `MAILER_DSN`)
- **Avatars** : 3 visuels de repli dans Git ; bibliothèque complète (~1200 fichiers) livrée à part
  (voir `docs/AVATARS.md`)

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

MySQL WAMP et Docker Desktop doivent **tourner** avant de lancer l'app.

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

`composer db-reset` charge les comptes staff (admin, accompagnateurs) et regénère les QR codes
selon `APP_URL`. **Aucun élève ni score de démo** : les élèves n'existent qu'après `/inscription`.

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

**Notifs programmées + alertes atelier/conf** : lancer en parallèle (terminal séparé) :

```bash
php bin/console messenger:consume scheduler_default -vv
```

La planification est dans `src/Schedule.php` (`app:notify-upcoming`, chaque minute).

---

## Tester sur mobile (scan QR + caméra)

Le scan exige **HTTPS** et le **même WiFi** que le PC.

1. Trouver l'IP LAN du PC : `ipconfig` → **Adresse IPv4** (ex. `192.168.1.33`)
2. Mettre la même IP dans :
    - `scripts/start-mobile-https.bat` → `set LAN_IP=...`
    - `.env.local` → `APP_URL=https://192.168.1.33`
3. Si l'IP a changé : supprimer `public/certs/dev.pem`, relancer le script
4. `composer db-reset` — regénère les QR codes avec la bonne URL
5. Relancer `.\scripts\start-mobile-https.bat`
6. Sur le téléphone : importer `public/certs/symfony-rootCA.pem` comme certificat CA
    - **iOS** : Réglages → Général → VPN et gestion de l'appareil, puis Réglages de confiance des certificats
    - **Android** : Paramètres → Sécurité → Installer un certificat → CA
7. Ouvrir **`https://<ip-lan>`** sur le mobile

---

## Comptes de test

| Rôle                                | Parcours                                   | Identifiants                                                 |
| ----------------------------------- | ------------------------------------------ | ------------------------------------------------------------ |
| **Élève**                           | `/inscription` → `/questionnaire` → `/map` | Code **GRP0001** ou **GRP0002** (pseudo animal auto)         |
| **Accompagnateur**                  | `/login` → `/map`                          | `accompagnateur@klask.fr` / `AccKlask2026!` (groupe GRP0002) |
| **Accompagnateur** (test reset mdp) | `/login`                                   | `jennv.contact@gmail.com` / `TestKlask2026!`                 |
| **Admin**                           | `/login` → `/admin`                        | `admin@klask.fr` / `AdminKlask2026!`                         |

Session élève : 24 h. Pas d'email ni mot de passe pour les élèves.

Mot de passe oublié : configurer `MAILER_DSN` dans `.env.local`.

---

## Commandes utiles

```bash
composer db-reset                         # dev : drop + create + migrate + fixtures
composer db-test                          # test : drop + create + migrate (klask_test)
php bin/phpunit                           # après composer db-test
composer refresh                          # cache + migrations + validate schema
php bin/console app:notify-upcoming       # alertes atelier/conf + notifs programmées (manuel)
php bin/console app:event-reset           # reset event terminé (manuel, destructif)
php bin/console app:avatars:check         # visuels manquants dans public/avatars/
php bin/console debug:scheduler             # prochaines exécutions planifiées
php bin/console messenger:consume scheduler_default -vv   # worker scheduler (dev)
```

Qualité (`.php-cs-fixer.dist.php`, `phpstan.dist.neon`) :

```bash
vendor/bin/php-cs-fixer fix
vendor/bin/phpstan analyse --memory-limit=1G
```

---

## Repartir de zéro (Docker + projet)

Pour un dev bloqué (502, Mercure mort, certificats pourris, base incohérente) :

```powershell
cd klask-dev

# 1. Tout arrêter
symfony server:stop
docker compose down

# 2. (Optionnel) Volumes Docker — efface l'état Mercure/Caddy local
docker compose down -v

# 3. Vérifier que MySQL tourne (WAMP ou service Windows)

# 4. Remettre la base à plat
composer db-reset
composer db-test

# 5. (Si IP LAN a changé ou certif HS) supprimer les certs dev
del public\certs\dev.pem
del public\certs\dev-key.pem

# 6. Relancer la stack
.\scripts\start-mobile-https.bat

# 7. Vérifier
docker compose ps          # mercure + proxy « Up »
# PC : https://127.0.0.1
# Mobile : https://<LAN_IP> + certificat CA importé
```

**Linux / Mac** (étapes 1–4 identiques, puis) :

```bash
docker compose up -d mercure proxy
symfony server:start --port=8000 --listen-ip=0.0.0.0 --no-tls --no-workers
php bin/console messenger:consume scheduler_default -vv   # terminal 2
```

Si Docker Desktop ne répond plus sous Windows : redémarrer Docker Desktop, puis relancer
l'étape 6.

---

## Dépannage

| Symptôme                      | Solution                                                                                   |
| ----------------------------- | ------------------------------------------------------------------------------------------ |
| Pas de `dev.pem` / proxy KO   | `mkcert.exe` manquant dans `scripts/tools/`                                                |
| `could not find driver`       | `docker compose up -d mercure proxy` (pas `up -d` seul)                                    |
| 502 Bad Gateway               | Symfony arrêté, ou sans `--no-tls` / `--listen-ip=0.0.0.0`                                 |
| Carte / pokes / notifs figés  | `docker compose up -d mercure proxy` + worker `scheduler_default` si notifs programmées    |
| Scan caméra refusé            | URL en `http://` → passer par `https://`                                                   |
| Mobile : page bloquée         | Importer `symfony-rootCA.pem` sur le téléphone                                             |
| QR codes mauvaise URL         | Corriger `APP_URL` → `composer db-reset`                                                   |
| Migration / table manquante   | `composer db-reset` (ne pas migrer sur une base existante)                                 |
| PHPUnit : colonne introuvable | `composer db-test`                                                                         |
| Port 8000 occupé              | `symfony server:stop`, tuer les `php-cgi.exe`, relancer                                    |
| CSS / JS pas à jour (mobile)  | Ctrl+F5 ; si `public/assets/` existe : le supprimer ou `php bin/console asset-map:compile` |
| Tous les élèves même avatar   | Normal sans livraison complète — `app:avatars:check` ; voir `docs/AVATARS.md`              |
| Docker incohérent             | Section **Repartir de zéro** ci-dessus                                                     |

---
