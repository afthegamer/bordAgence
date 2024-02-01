Documentation de GoogleController
=================================

Description Générale
--------------------

`GoogleController.php` est un contrôleur intégré dans le framework Symfony, conçu pour gérer les interactions entre l'interface utilisateur et les services liés à Google. Il traite les requêtes, appelle les services appropriés et renvoie des réponses à l'utilisateur, en gérant à la fois l'authentification et l'accès aux services Google.

Fonctionnalités et Méthodes
---------------------------

### Authentification Google Login

*   **Route :** `/google/login`
*   **Méthode :** `googleLogin()`
*   **Description :** Initie le processus d'authentification Google, configurant le client pour une authentification hors ligne et redirigeant vers l'URL d'authentification Google.
*   **Retour :** Redirection vers la page de consentement Google.

### Callback d'Authentification Google

*   **Route :** `/callback`
*   **Méthode :** `googleCallback(Request $request, SessionInterface $session)`
*   **Description :** Gère le retour d'authentification de Google, échangeant le code d'authentification contre un token d'accès.
*   **Retour :** Redirection vers la page d'accueil de l'application ou vers la page de login en cas d'échec.

### Affichage des Événements du Calendrier

*   **Route :** `/calendar/events`
*   **Méthode :** `calendarEvents(SessionInterface $session, CalendarService $calendarService)`
*   **Description :** Récupère et affiche les événements du calendrier Google de l'utilisateur après authentification.
*   **Retour :** Liste des événements du calendrier ou un message d'erreur.

Exemples d'Utilisation
----------------------

*   Authentification d'un utilisateur via l'interface web et accès à ses informations de calendrier Google.
*   Affichage des emails et des événements du calendrier après une authentification réussie.

Intégration et Dépendances
--------------------------

`GoogleController` s'appuie sur `GoogleClientService` pour l'authentification et l'accès aux services Google. Il interagit également avec `CalendarService` et `EmailService` pour fournir des fonctionnalités complémentaires. Une gestion correcte des réponses et des erreurs est cruciale pour assurer une expérience utilisateur fluide.