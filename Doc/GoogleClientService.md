# Documentation de GoogleClientService

## Description Générale
`GoogleClientService.php` est un service conçu pour intégrer et gérer les interactions avec les API Google, notamment Google Calendar et Google Gmail en lecture seule. Ce service est essentiel pour configurer et maintenir l'authentification OAuth avec Google.

## Fonctionnalités et Méthodes
### Récupération du Client Google
- **Méthode :** `getClient()`
- **Description :** Récupère l'instance du client Google configurée.
- **Retour :** Instance de `Google\Client`.

### Gestion des Tokens d'Accès
- **Méthode :** `setAccessToken($accessToken)`
- **Description :** Configure le token d'accès pour le client Google.
- **Paramètres :** `$accessToken` - Le token d'accès.

- **Méthode :** `refreshTokenIfNeeded(SessionInterface $session)`
- **Description :** Vérifie si le token d'accès est expiré et le rafraîchit si nécessaire.
- **Paramètres :** `$session` - La session pour stocker et récupérer les tokens.

## Exemples d'Utilisation
```php
// Création d'une instance du service GoogleClientService
$googleClientService = new GoogleClientService();

// Récupération de l'instance Client de Google
$client = $googleClientService->getClient();

// Configuration du token d'accès si disponible
if ($session->has('access_token')) {
    $googleClientService->setAccessToken($session->get('access_token'));
}

// Rafraîchir le token si nécessaire
$googleClientService->refreshTokenIfNeeded($session);

// Utilisation du client pour accéder aux services Google
// Par exemple, récupération des événements de Google Calendar
$googleCalendarService = new GoogleCalendarService($client);
$calendarEvents = $googleCalendarService->getEvents();
```

## Intégration et Dépendances
Ce service peut dépendre de la bibliothèque client Google API pour PHP. Il nécessite également une gestion appropriée des clés API et des secrets client pour l'authentification.

---
