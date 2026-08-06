@echo off
REM Demarre la stack dev : proxy Caddy en HTTPS (port 443) + Symfony en clair (port 8000).
REM URL a ouvrir : https://127.0.0.1 (sans port) ou https://%LAN_IP% depuis le mobile.
REM Une seule origine https pour l'app ET le hub Mercure : pas de contenu mixte, pas de CORS.
cd /d "%~dp0.."

REM >>> Seule valeur a adapter quand ton IP WiFi change (ipconfig -> Adresse IPv4).
REM     Mets la meme dans APP_URL (.env.local), puis relance les fixtures (QR codes).
set LAN_IP=192.168.1.17

REM Certificat mkcert (localhost + IP LAN pour le scan QR mobile).
REM Le proxy Caddy consomme directement le couple PEM (plus besoin d'export .p12).
REM Supprime public\certs\dev.pem pour le regenerer apres un changement d'IP.
if not exist "public\certs" mkdir "public\certs"
if not exist "public\certs\dev.pem" (
    scripts\tools\mkcert.exe -install
    scripts\tools\mkcert.exe -cert-file "public\certs\dev.pem" -key-file "public\certs\dev-key.pem" 127.0.0.1 localhost %LAN_IP%
    copy /Y "%LOCALAPPDATA%\mkcert\rootCA.pem" "public\certs\symfony-rootCA.pem"
)

REM Hub Mercure + proxy (ne pas lancer `up -d` seul : demarrerait aussi Postgres)
docker compose up -d mercure proxy

REM --listen-ip=0.0.0.0 est indispensable : le proxy joint l'hote via host.docker.internal
REM --no-tls : c'est le proxy (port 443) qui porte le HTTPS, pas ce serveur
symfony server:stop 2>nul
symfony server:start --port=8000 --listen-ip=0.0.0.0 --no-tls --no-workers
