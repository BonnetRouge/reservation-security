# Reservation Security

Projet d'évaluation — Sécurité Web M1 (EEMI)

API REST de réservation (Symfony 7.4 + API Platform, PostgreSQL, JWT, Docker)
développée en deux versions dans ce dépôt : une branche `vulnerable`
contenant volontairement 4 vulnérabilités obligatoires + 1 bonus, et une
branche `secure` où ces failles sont corrigées à la racine.

Le rapport d'audit complet se trouve dans [`SECURITY_AUDIT.md`](./SECURITY_AUDIT.md)
(branche `secure`).

## Stack technique

- Backend : Symfony 7.4 + API Platform
- Base de données : PostgreSQL 16 (Doctrine ORM)
- Authentification : JWT (LexikJWTAuthenticationBundle)
- Conteneurisation : Docker + Docker Compose
- Serveur web : Nginx + PHP-FPM

## Organisation Git

| Branche | Contenu |
|---|---|
| `main` | Socle commun : Docker, Symfony, authentification JWT, entités de base |
| `vulnerable` | Application avec les 4 vulnérabilités obligatoires + 1 bonus, exploitées et documentées (voir `captures/vulnerable/`) |
| `secure` | Corrections appliquées à la racine de chaque faille, avec un commit dédié par correction |

## Installation et lancement

### Prérequis

- Docker Desktop
- Git

### Étapes

```bash
git clone https://github.com/BonnetRouge/reservation-security.git
cd reservation-security
git checkout vulnerable   # ou "secure" pour la version corrigée

docker compose build
docker compose up -d

docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console doctrine:fixtures:load
```

L'application est accessible sur : **http://localhost:8086**

Le client de test HTML (utile pour observer la faille/correction XSS) est
accessible sur : **http://localhost:8086/client-test/index.html**

### Récupérer un token JWT (exemple PowerShell)

```powershell
$response = Invoke-RestMethod -Uri "http://localhost:8086/api/login" -Method Post -ContentType "application/json" -Body '{"email":"user@test.com","password":"password123"}'
$token = $response.token
```

Puis utiliser ce token dans le header `Authorization: Bearer <token>` pour
toutes les requêtes vers `/api/...`.

## Comptes de test

| Email | Mot de passe | Rôle |
|---|---|---|
| `user@test.com` | `password123` | `ROLE_USER` |
| `admin@test.com` | `password123` | `ROLE_ADMIN` |

Ces comptes sont créés automatiquement via
`docker compose exec php php bin/console doctrine:fixtures:load`.

## Documentation des vulnérabilités

Le détail complet (endpoint, cause technique, exploitation, preuve, impact,
criticité, correction) se trouve dans :

- [`SECURITY_AUDIT.md`](./SECURITY_AUDIT.md) — rapport principal
- `captures/vulnerable/` — preuves d'exploitation (requêtes, payloads,
  réponses serveur, captures d'écran Postman) pour chaque faille