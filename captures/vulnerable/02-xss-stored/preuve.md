# Preuve — XSS stocké (Stored Cross-Site Scripting)

## Endpoint / zone concernée
Champ `note` de l'entité `Reservation`, affiché dans le client de test
`http://localhost:8086/client-test/index.html`.

## Description
Le contenu du champ `note` d'une réservation est inséré directement dans le
DOM via `innerHTML`, sans aucun échappement. Un utilisateur peut donc stocker
du code JavaScript dans une note, qui s'exécutera automatiquement dans le
navigateur de toute personne consultant la liste des réservations (y compris
un administrateur).

## Cause technique
Dans `app/public/client-test/index.html` :
```javascript
div.innerHTML = `<strong>${r.resourceName}</strong> - ${r.note}`;
```
Le contenu de `r.note`, provenant directement de l'API sans sanitisation, est
injecté tel quel dans le HTML de la page.

## Exploitation

1. Connexion en tant que `user@test.com`, récupération du token.

2. Injection du payload dans le champ `note` de la réservation #1 :
```powershell
Invoke-RestMethod -Uri "http://localhost:8086/api/reservations/1" -Method Patch -Headers @{ Authorization = "Bearer $token"; "Content-Type" = "application/merge-patch+json" } -Body '{"note":"<img src=x onerror=alert(document.cookie)>"}'
```

## Payload utilisé
```html
<img src=x onerror=alert(document.cookie)>
```

## Résultat observé
En ouvrant `http://localhost:8086/client-test/index.html` et en s'authentifiant
avec un token JWT (n'importe quel utilisateur, y compris la victime), une
alerte JavaScript se déclenche automatiquement au chargement de la page —
preuve que le script injecté s'exécute dans le contexte de la victime.

Voir captures : `xss-alert-declenchee.png`, `xss-page-avant-alerte.png`.

## Impact
- Technique : exécution de JavaScript arbitraire dans le navigateur de toute
  personne consultant la liste des réservations.
- Métier : un attaquant pourrait voler la session/le token JWT d'un
  administrateur consultant la page, ou rediriger la victime vers un site
  malveillant, ou modifier l'affichage de la page à son insu.

## Criticité
**Élevée** — le payload s'exécute sans interaction de la victime au-delà
d'un simple chargement de page ; impact potentiel sur la confidentialité
(vol de token) et l'intégrité (défacement, actions à l'insu de la victime).

## Captures d'écran

