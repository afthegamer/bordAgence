Documentation de LinkedInController
===================================

Description Générale
--------------------

`LinkedInController.php` gère les interactions entre l'interface utilisateur et les services liés à LinkedIn dans une application Symfony. Ce contrôleur traite les requêtes concernant LinkedIn, facilitant l'authentification des utilisateurs et l'affichage des données LinkedIn.

Fonctionnalités et Méthodes
---------------------------

### Authentification OAuth2 avec LinkedIn

*   **Route :** `/linkedin/oauth`
*   **Méthode :** `redirectToLinkedIn(Request $request): Response`
*   **Description :** Gère le processus d'authentification OAuth2 avec LinkedIn. Crée et stocke un jeton CSRF, construit l'URL d'authentification, et redirige l'utilisateur vers l'authentification LinkedIn.
*   **Retour :** Redirection vers l'URL d'authentification de LinkedIn.

### Récupération et Affichage des Posts LinkedIn

*   **Route :** `/linkedin/posts`
*   **Méthode :** `fetchLinkedInPosts(Request $request): Response`
*   **Description :** Récupère les posts LinkedIn après une authentification réussie. Utilise `LinkedInAuthService` pour l'authentification et `LinkedinClientService` pour récupérer les posts et les détails de l'organisation.
*   **Retour :** Rendu du template avec les données des posts et de l'organisation.

Exemples d'Utilisation
----------------------

*   Redirection vers l'authentification OAuth2 avec LinkedIn pour initier le processus d'authentification.
*   Récupération des posts LinkedIn après une authentification réussie et affichage dans l'interface utilisateur.

Intégration et Dépendances
--------------------------

`LinkedInController` dépend de `LinkedInAuthService` pour gérer l'authentification et de `LinkedinClientService` pour interagir avec l'API LinkedIn. Il est crucial de gérer correctement les sessions, les jetons CSRF, et les réponses.