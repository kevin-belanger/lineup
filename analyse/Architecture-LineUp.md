# Architecture de départ - LineUp

## 1. Stack confirmée

La stack retenue pour LineUp est :

- **Framework applicatif** : Laravel
- **Authentification** : Laravel Breeze
- **Interface dynamique** : Livewire
- **Styles** : Tailwind CSS
- **Base de données** : MySQL ou MariaDB
- **Serveur web** : Apache
- **Langage backend** : PHP 8.3 ou plus récent
- **Gestion des dépendances PHP** : Composer
- **Gestion des dépendances frontend** : Node.js et npm
- **Versionnement** : Git et GitHub
- **Serveur cible** : Linux, local ou VPS

Cette stack permet de construire une application web complète, déployable simplement sur un serveur Apache/MySQL classique.

## 2. Type d'application

LineUp sera une application Laravel monolithique.

Cela signifie que le backend, les vues, les formulaires, l'authentification, les permissions et l'interface utilisateur seront dans une seule application Laravel.

Il n'y aura pas d'API séparée ni de frontend React/Vue séparé dans l'itération 1.

Livewire sera utilisé pour les parties interactives :

- listes de demandes;
- attribution d'une demande;
- changement de statut;
- formulaire de création ou modification;
- indicateurs côté enseignant;
- filtres et vues dynamiques.

## 3. Principes d'architecture

L'application doit rester simple, lisible et facile à déployer.

Les principes retenus sont :

- une seule application Laravel;
- une base de données MySQL/MariaDB;
- des rôles simples : étudiant, enseignant, admin;
- des écrans séparés par rôle;
- une logique métier centralisée dans des services ou actions Laravel;
- des composants Livewire pour les interfaces interactives;
- une séparation claire entre les données de référence, les demandes et les utilisateurs.

## 4. Espaces applicatifs

L'application sera divisée en quatre grands espaces.

### 4.1 Espace public

Accessible sans connexion.

Fonctions principales :

- page de connexion;
- page de création de compte;
- page de récupération de mot de passe, si activée.

### 4.2 Espace étudiant

Accessible aux utilisateurs connectés ayant le rôle étudiant.

Fonctions principales :

- choix du local;
- tableau de bord étudiant;
- création d'une demande;
- modification d'une demande en attente;
- annulation d'une demande en attente;
- affichage des demandes actives;
- bouton **Je suis prêt** pour une demande en pause;
- historique des demandes.

### 4.3 Espace enseignant

Accessible aux utilisateurs ayant le rôle enseignant.

Fonctions principales :

- choix du local;
- file principale des demandes en attente;
- attribution d'une demande;
- liste personnelle des demandes attribuées;
- mise en pause d'une demande;
- terminaison d'une demande;
- désassignation d'une demande;
- consultation des demandes attribuées aux autres enseignants dans le même local.

### 4.4 Espace administration

Accessible aux utilisateurs ayant le rôle admin.

Fonctions principales :

- gestion des locaux;
- gestion des matières;
- gestion des utilisateurs;
- attribution et retrait des rôles enseignant et admin;
- activation ou désactivation des données de référence.

## 5. Modules fonctionnels

### 5.1 Authentification

Responsabilités :

- création de compte;
- connexion;
- déconnexion;
- protection des routes;
- gestion du compte utilisateur.

Choix retenu :

- Laravel Breeze pour l'authentification;
- Blade et Tailwind pour les écrans fournis par Breeze;
- Livewire pour les écrans métier interactifs de LineUp.

Breeze servira de base pour :

- l'inscription;
- la connexion;
- la déconnexion;
- la réinitialisation du mot de passe;
- la gestion de base du profil utilisateur.

La logique propre à LineUp sera ajoutée par-dessus Breeze, notamment :

- attribution automatique du rôle étudiant à la création du compte;
- redirection après connexion selon les rôles;
- protection des espaces étudiant, enseignant et admin.

### 5.2 Gestion des rôles

Responsabilités :

- définir si un utilisateur est étudiant, enseignant ou admin;
- attribuer le rôle étudiant par défaut à l'inscription;
- permettre à un admin de modifier les rôles.

Approche recommandée pour l'itération 1 :

- colonnes booléennes sur la table `users` :
  - `is_student`;
  - `is_teacher`;
  - `is_admin`.

Cette approche est simple et suffisante pour les besoins actuels. Une table de rôles plus avancée pourra être ajoutée plus tard si nécessaire.

### 5.3 Gestion des locaux

Responsabilités :

- créer, modifier et désactiver les locaux;
- afficher seulement les locaux actifs aux étudiants et enseignants;
- associer chaque demande à un local.

### 5.4 Gestion des matières

Responsabilités :

- créer, modifier et désactiver les matières;
- associer chaque matière à un local;
- afficher seulement les matières actives du local courant aux étudiants;
- associer chaque demande à une matière.

### 5.5 Gestion des demandes

Responsabilités :

- créer une demande;
- modifier une demande en attente;
- annuler une demande en attente;
- attribuer une demande à un enseignant;
- mettre une demande en pause;
- signaler que l'étudiant est prêt;
- désassigner une demande;
- terminer une demande;
- afficher les demandes selon le rôle et le local.

Ce module est le coeur de LineUp.

### 5.6 Historique et suivi

Responsabilités :

- afficher les demandes terminées ou annulées d'un étudiant;
- conserver les dates importantes;
- permettre une lecture simple du parcours d'une demande.

Pour l'itération 1, l'historique peut être basé sur les champs directement présents dans la table des demandes. Une table d'événements détaillée pourra être ajoutée plus tard.

## 6. Modèle de données initial

Le modèle de données détaillé sera produit à l'étape suivante, mais l'architecture prévoit les tables principales suivantes :

- `users`;
- `classrooms`;
- `subjects`;
- `support_requests`.

Tables possibles plus tard :

- `support_request_events`;
- `notifications`;
- `teacher_room_sessions`;
- `settings`.

## 7. Statuts des demandes

Les statuts seront probablement stockés sous forme de chaîne ou d'enum applicatif.

Statuts prévus :

- `waiting` : en attente dans la file principale;
- `assigned` : attribuée à un enseignant;
- `paused` : en pause, toujours attribuée;
- `ready` : l'étudiant est prêt à revoir l'enseignant;
- `completed` : terminée;
- `cancelled` : annulée.

Les libellés visibles dans l'interface seront en français.

## 8. Types de demandes

Types prévus :

- `explanation` : explication;
- `validation` : validation;
- `correction` : correction.

Ces types peuvent être codés dans l'application pour l'itération 1. Une gestion admin des types pourra être ajoutée plus tard si l'école veut les personnaliser.

## 9. Structure Laravel proposée

Structure générale :

```text
app/
  Actions/
    SupportRequests/
  Livewire/
    Student/
    Teacher/
    Admin/
  Models/
  Policies/
  Services/

database/
  migrations/
  seeders/

resources/
  views/
    layouts/
    student/
    teacher/
    admin/

routes/
  web.php
```

### 9.1 Models

Modèles prévus :

- `User`;
- `Classroom`;
- `Subject`;
- `SupportRequest`.

### 9.2 Livewire components

Composants étudiants possibles :

- `Student\Dashboard`;
- `Student\SelectClassroom`;
- `Student\RequestForm`;
- `Student\ActiveRequests`;
- `Student\RequestHistory`.

Composants enseignants possibles :

- `Teacher\Dashboard`;
- `Teacher\SelectClassroom`;
- `Teacher\WaitingQueue`;
- `Teacher\MyRequests`;
- `Teacher\OtherTeacherRequests`;
- `Teacher\RequestDetails`.

Composants admin possibles :

- `Admin\Classrooms`;
- `Admin\Subjects`;
- `Admin\Users`.

### 9.3 Actions métier

Les actions métier évitent de placer toute la logique directement dans les composants Livewire.

Actions possibles :

- `CreateSupportRequest`;
- `UpdateSupportRequest`;
- `CancelSupportRequest`;
- `AssignSupportRequest`;
- `PauseSupportRequest`;
- `MarkSupportRequestReady`;
- `UnassignSupportRequest`;
- `CompleteSupportRequest`.

## 10. Organisation des routes

Routes publiques :

```text
/login
/register
```

Routes étudiant :

```text
/student
/student/classroom
/student/requests
/student/history
```

Routes enseignant :

```text
/teacher
/teacher/classroom
/teacher/queue
/teacher/my-requests
```

Routes admin :

```text
/admin
/admin/classrooms
/admin/subjects
/admin/users
```

Les routes seront protégées par authentification et par rôle.

## 11. Permissions et sécurité

La sécurité doit être appliquée à deux niveaux :

- dans les routes, pour empêcher l'accès aux mauvais espaces;
- dans les actions métier, pour empêcher une action non autorisée.

Exemples :

- un étudiant ne peut modifier que ses propres demandes;
- un étudiant ne peut modifier une demande que si elle est en attente;
- un enseignant ne peut s'attribuer qu'une demande en attente du local choisi;
- un enseignant ne peut terminer qu'une demande qui lui est attribuée;
- un admin peut gérer les données de référence;
- un admin n'est pas automatiquement enseignant, sauf si son compte a aussi le rôle enseignant.

## 12. Gestion du local courant

L'étudiant et l'enseignant doivent choisir un local avant d'utiliser les fonctions principales.

Approche proposée :

- stocker le local courant en session;
- permettre à l'utilisateur de changer de local;
- vérifier le local courant avant de créer ou consulter des demandes.

Exemples :

- `current_classroom_id` dans la session;
- redirection vers le choix du local si aucun local n'est sélectionné.

## 13. Interface utilisateur

L'interface doit être simple, rapide et adaptée à une utilisation en classe.

Principes :

- peu de distractions visuelles;
- boutons d'action clairs;
- statuts bien visibles;
- demandes faciles à scanner;
- priorité aux listes et tableaux de bord;
- interface utilisable sur ordinateur, tablette ou portable.

### 13.1 Étudiant

L'étudiant doit pouvoir créer une demande en peu d'étapes.

L'écran principal devrait afficher :

- le local courant;
- un bouton de création de demande;
- les demandes actives;
- l'état de chaque demande;
- l'accès à l'historique.

### 13.2 Enseignant

L'enseignant doit voir rapidement :

- les demandes en attente;
- ses demandes attribuées;
- les demandes prêtes à revoir;
- les demandes des autres enseignants.

Les actions principales doivent être visibles :

- prendre en charge;
- mettre en pause;
- terminer;
- se désassigner.

### 13.3 Admin

L'interface admin peut être plus classique :

- listes;
- formulaires;
- actions créer, modifier, désactiver;
- filtres simples.

## 14. Mise à jour des listes

Pour l'itération 1, deux options sont possibles :

### Option A - Rafraîchissement simple

Les listes se mettent à jour au chargement de la page ou après une action.

Avantages :

- simple;
- fiable;
- rapide à développer.

Limite :

- l'enseignant ne voit pas toujours instantanément les nouvelles demandes.

### Option B - Rafraîchissement périodique avec Livewire

Les listes se rafraîchissent automatiquement toutes les quelques secondes.

Avantages :

- donne une impression plus temps réel;
- évite d'ajouter une technologie WebSocket au départ.

Limite :

- un peu plus de requêtes au serveur.

Recommandation pour l'itération 1 :

- utiliser Livewire avec un rafraîchissement périodique léger pour les listes enseignant.

Le vrai temps réel avec WebSockets pourra être ajouté plus tard.

## 15. Déploiement

Le déploiement cible est un serveur Linux avec Apache et MySQL/MariaDB.

### 15.1 Composants serveur

Le serveur devra contenir :

- Apache;
- PHP 8.3 ou plus récent;
- extensions PHP nécessaires à Laravel;
- MySQL ou MariaDB;
- Composer;
- Node.js et npm;
- Git.

### 15.2 Dossier de déploiement

Exemple de dossier :

```text
/var/www/lineup
```

Le `DocumentRoot` Apache doit pointer vers :

```text
/var/www/lineup/public
```

La racine complète du projet ne doit pas être exposée directement par Apache.

### 15.3 Fichier d'environnement

Chaque serveur aura son fichier `.env`.

Il contiendra notamment :

- connexion MySQL;
- clé Laravel;
- URL de l'application;
- paramètres de courriel;
- mode debug désactivé en production.

### 15.4 Déploiement manuel initial

Processus typique :

1. récupérer le code depuis GitHub;
2. installer les dépendances PHP;
3. installer les dépendances frontend;
4. compiler les assets;
5. configurer le fichier `.env`;
6. générer la clé Laravel;
7. exécuter les migrations;
8. configurer Apache;
9. activer HTTPS si un domaine est utilisé.

## 16. GitHub

Le projet sera publié sur GitHub.

Le dépôt devrait contenir :

- le code Laravel;
- les migrations;
- les seeders;
- les composants Livewire;
- les vues;
- les tests;
- la documentation du projet.

Le dépôt ne doit pas contenir :

- le fichier `.env`;
- les mots de passe;
- les clés secrètes;
- les fichiers générés inutiles;
- le dossier `vendor`;
- le dossier `node_modules`.

## 17. Environnements

Environnements prévus :

- **local** : développement sur ordinateur;
- **test/staging**, optionnel : validation avant mise en production;
- **production** : serveur réel utilisé par l'école.

Pour le début du projet, un environnement local et un environnement production peuvent suffire.

## 18. Tests

Les tests devraient couvrir les règles métier importantes.

Priorités de test :

- création de compte avec rôle étudiant;
- création de demande;
- modification seulement si la demande est en attente;
- annulation seulement si la demande est en attente;
- attribution par un enseignant;
- disparition de la file principale après attribution;
- mise en pause;
- bouton **Je suis prêt**;
- désassignation;
- terminaison;
- permissions admin.

## 19. Évolution future

Fonctionnalités ou améliorations possibles après l'itération 1 :

- notifications temps réel avec WebSockets;
- statistiques par local, matière ou enseignant;
- réattribution directe à un autre enseignant;
- historique détaillé des changements de statut;
- export de données;
- intégration avec des comptes scolaires;
- gestion avancée des horaires ou périodes;
- application mobile native ou PWA plus poussée.

## 20. Décisions retenues

- L'application sera construite avec Laravel.
- Laravel Breeze sera utilisé pour l'authentification.
- Livewire sera utilisé pour l'interactivité.
- Tailwind CSS sera utilisé pour le style.
- MySQL ou MariaDB sera utilisé pour la base de données.
- Apache sera utilisé comme serveur web.
- L'application sera monolithique.
- Les rôles seront simples dans l'itération 1.
- Le local courant sera stocké en session.
- Les demandes seront séparées par local.
- Les listes enseignant pourront se rafraîchir périodiquement avec Livewire.

## 21. Prochaine étape

La prochaine étape recommandée est de définir le modèle de données détaillé :

- tables;
- colonnes;
- relations;
- contraintes;
- statuts;
- index;
- données initiales.

Ce modèle servira ensuite à créer les migrations Laravel.
