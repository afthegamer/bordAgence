# Documentation de EmailService

## Description Générale
`EmailService.php` est utilisé pour récupérer des e-mails en utilisant des services de messagerie externes via des requêtes API. Il inclut une fonctionnalité de filtrage pour obtenir des e-mails spécifiques en fonction d'adresses et de sujets définis.

## Fonctionnalités et Méthodes
### Filtrage des E-mails
- **Méthode :** `getEmailsFromLastDays($addresses, $subjectFilters)`
- **Description :** Récupère les e-mails des derniers jours qui correspondent à des adresses e-mail spécifiques et à des filtres de sujet définis.
- **Paramètres :**
  - `$addresses` - Tableau d'adresses e-mail pour le filtrage.
  - `$subjectFilters` - Tableau de mots-clés ou de phrases pour filtrer les sujets.
- **Retour :** Tableau d'e-mails correspondant aux critères de filtrage.

### Autres Méthodes
#### getGmailMessage
- **Méthode :** `getGmailMessage($messageId)`
- **Description :** Récupère un message spécifique dans Gmail en utilisant son identifiant.
- **Paramètres :**
  - `$messageId` - Identifiant du message à récupérer.
- **Retour :** Détails du message Gmail spécifié.

#### getPartBody
- **Méthode :** `getPartBody($message, $partId)`
- **Description :** Extrait une partie spécifique du corps d'un message.
- **Paramètres :**
  - `$message` - Message Gmail complet.
  - `$partId` - Identifiant de la partie du corps à extraire.
- **Retour :** Contenu de la partie spécifiée du corps du message.

#### setAccessToken
- **Méthode :** `setAccessToken($token)`
- **Description :** Configure le token d'accès pour l'authentification avec le service de messagerie.
- **Paramètres :**
  - `$token` - Token d'accès pour l'API de messagerie.

#### extractEmailData
- **Méthode :** `extractEmailData($email)`
- **Description :** Extrait des données pertinentes d'un e-mail, comme l'expéditeur, le sujet, etc.
- **Paramètres :**
  - `$email` - E-mail à partir duquel extraire les données.
- **Retour :** Données extraites de l'e-mail.

#### getHeader
- **Méthode :** `getHeader($message, $headerName)`
- **Description :** Récupère un en-tête spécifique d'un message Gmail.
- **Paramètres :**
  - `$message` - Message Gmail complet.
  - `$headerName` - Nom de l'en-tête à récupérer.
- **Retour :** Valeur de l'en-tête spécifié.

#### getMessageBody
- **Méthode :** `getMessageBody($message)`
- **Description :** Récupère le corps d'un message Gmail.
- **Paramètres :**
  - `$message` - Message Gmail complet.
- **Retour :** Corps du message.

#### decodeBody
- **Méthode :** `decodeBody($body)`
- **Description :** Décode le corps du message encodé.
- **Paramètres :**
  - `$body` - Corps du message encodé.
- **Retour :** Corps du message décodé.

#### convertPlainTextToHtml
- **Méthode :** `convertPlainTextToHtml($text)`
- **Description :** Convertit le texte brut en HTML.
- **Paramètres :**
  - `$text` - Texte brut à convertir.
- **Retour :** Texte converti en HTML.

## Exemple d'Utilisation pour le filtrage des e-mails
```php
// Création d'une instance du service
$emailService = new EmailService();

// Filtrage des e-mails
$addresses = [
    'exemple@exemple.com',
    'exemple@exemple.fr',
    'etc@etc.com'
];
$subjectFilters = [
    'mot-clé',
    'mot-clé 2',
    'bilan',
    'etc'
];
$emails = $emailService->getEmailsFromLastDays($addresses, $subjectFilters);

```