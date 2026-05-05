# Découpage MVP - LineUp

## 1. Objectif du MVP

Le MVP de LineUp doit permettre de valider le fonctionnement essentiel de l'application en contexte réel de classe.

L'objectif n'est pas de construire toutes les fonctionnalités possibles dès le départ, mais de livrer une première version utilisable qui couvre le cycle complet d'une demande :

1. un utilisateur crée un compte;
2. un admin approuve le compte;
3. un étudiant choisit un local;
4. l'étudiant crée une demande;
5. un enseignant choisit le même local;
6. l'enseignant voit la demande;
7. l'enseignant se l'attribue;
8. l'enseignant la met en pause, la termine ou la remet dans la file;
9. l'étudiant suit l'état de sa demande;
10. la demande terminée apparaît dans l'historique.

## 2. Principes de découpage

Le découpage MVP suit trois principes :

- livrer d'abord le parcours central étudiant-enseignant;
- garder l'administration minimale, mais suffisante pour utiliser l'application;
- reporter les fonctions avancées qui ne sont pas nécessaires à la validation initiale.

## 3. MVP - Version 1

La version 1 doit inclure les modules suivants :

- authentification;
- approbation des comptes;
- rôles de base;
- gestion minimale des données de référence;
- espace étudiant;
- espace enseignant;
- cycle complet d'une demande;
- historique étudiant.

## 4. Lot 1 - Fondation technique

### Objectif

Mettre en place le socle Laravel de l'application.

### Fonctionnalités incluses

- initialiser le projet Laravel;
- installer Laravel Breeze;
- configurer Livewire;
- configurer Tailwind CSS;
- configurer MySQL/MariaDB;
- créer les migrations principales;
- créer les modèles Laravel;
- créer les seeders de base;
- configurer les routes principales;
- mettre en place la structure des espaces étudiant, enseignant et admin.

### Critères d'acceptation

- l'application démarre en local;
- un utilisateur peut accéder à l'écran de connexion;
- un utilisateur peut accéder à l'écran d'inscription;
- la base de données contient les tables principales;
- un compte admin initial peut être créé par seeder.

## 5. Lot 2 - Authentification, rôles et approbation

### Objectif

Permettre aux utilisateurs de créer un compte, puis bloquer l'utilisation tant que le compte n'est pas approuvé par un admin.

### Fonctionnalités incluses

- création de compte avec rôle étudiant par défaut;
- compte créé avec `is_approved = false`;
- blocage des espaces protégés pour les comptes non approuvés;
- interface admin pour voir les comptes en attente;
- action admin pour approuver un compte;
- gestion des rôles enseignant et admin;
- désactivation d'un compte.

### Critères d'acceptation

- un nouveau compte est étudiant par défaut;
- un nouveau compte est en attente d'approbation;
- un compte non approuvé ne peut pas créer de demande;
- un admin peut approuver un compte;
- un admin peut attribuer le rôle enseignant;
- un admin peut attribuer le rôle admin;
- un compte approuvé peut accéder à son espace selon ses rôles.

## 6. Lot 3 - Administration des données de référence

### Objectif

Permettre à un admin de préparer les données nécessaires à l'utilisation de LineUp.

### Fonctionnalités incluses

- gestion des locaux;
- gestion des matières;
- association de chaque matière à un local;
- activation et désactivation des locaux;
- activation et désactivation des matières.

### Critères d'acceptation

- un admin peut créer un local;
- un admin peut modifier un local;
- un admin peut désactiver un local;
- un admin peut créer une matière;
- un admin doit associer une matière à un local;
- un admin peut modifier une matière;
- un admin peut désactiver une matière;
- seuls les éléments actifs sont proposés aux étudiants et enseignants.

## 7. Lot 4 - Espace étudiant

### Objectif

Permettre à l'étudiant de créer et suivre ses demandes.

### Fonctionnalités incluses

- choix du local courant;
- sélection automatique du local si une demande active existe déjà;
- tableau de bord étudiant;
- création d'une demande;
- sélection d'une matière active du local courant;
- saisie du numéro de tuile Moodle;
- sélection du type de demande;
- saisie du numéro de table;
- saisie d'un commentaire;
- affichage des demandes actives;
- modification d'une demande en attente;
- annulation d'une demande en attente;
- action **Je suis prêt** lorsqu'une demande est en pause;
- historique des demandes terminées ou annulées.
- limite d'une seule demande active par étudiant;
- confirmation avant changement de local si une demande active doit être annulée.

### Critères d'acceptation

- l'étudiant doit choisir un local avant de créer une demande;
- si l'étudiant a une demande active, son local est automatiquement sélectionné;
- l'étudiant peut créer une demande complète;
- l'étudiant voit seulement les matières actives du local courant;
- l'étudiant doit indiquer le numéro de tuile Moodle où il est rendu;
- une demande est créée au statut `waiting`;
- l'étudiant ne peut pas créer une deuxième demande active;
- changer vers un autre local exige une confirmation et annule les demandes actives;
- l'étudiant voit ses demandes actives;
- l'étudiant peut modifier une demande au statut `waiting`;
- l'étudiant ne peut pas modifier une demande attribuée;
- l'étudiant peut annuler une demande au statut `waiting`;
- l'étudiant peut indiquer qu'il est prêt lorsque la demande est au statut `paused`;
- l'étudiant voit ses demandes terminées ou annulées dans l'historique.

## 8. Lot 5 - Espace enseignant

### Objectif

Permettre à l'enseignant de gérer les demandes dans son local.

### Fonctionnalités incluses

- choix du local courant;
- file principale des demandes en attente;
- affichage de la matière, de la tuile Moodle, du type, de la table et du commentaire;
- attribution d'une demande;
- liste personnelle des demandes attribuées;
- mise en pause d'une demande;
- indicateur lorsqu'un étudiant est prêt à revoir;
- terminaison d'une demande;
- désassignation d'une demande;
- consultation des demandes attribuées aux autres enseignants du même local.

### Critères d'acceptation

- l'enseignant doit choisir un local avant de voir les demandes;
- l'enseignant voit seulement les demandes du local choisi;
- la file principale affiche seulement les demandes au statut `waiting`;
- l'enseignant peut s'attribuer une demande en attente;
- une demande attribuée disparaît de la file principale;
- une demande attribuée apparaît dans la liste personnelle de l'enseignant;
- l'enseignant peut mettre en pause une demande qui lui est attribuée;
- l'enseignant voit lorsqu'un étudiant est prêt à revoir;
- l'enseignant peut terminer une demande qui lui est attribuée;
- l'enseignant peut se désassigner d'une demande qui lui est attribuée;
- une demande désassignée retourne dans la file principale;
- l'enseignant peut voir les demandes attribuées aux autres enseignants.

## 9. Lot 6 - Polissage MVP et validation

### Objectif

Rendre la première version suffisamment claire, stable et utilisable pour un essai réel.

### Fonctionnalités incluses

- messages de validation clairs;
- affichage lisible des statuts;
- navigation simple entre les espaces;
- redirections selon les rôles;
- état vide des listes;
- vérification des permissions;
- tests des règles métier principales;
- documentation d'installation locale;
- documentation de déploiement initial Apache/MySQL.

### Critères d'acceptation

- les utilisateurs comprennent pourquoi un compte est en attente d'approbation;
- les listes vides affichent un message utile;
- les actions non autorisées sont bloquées;
- les parcours principaux fonctionnent sans erreur;
- l'application peut être installée à partir du dépôt GitHub;
- les étapes de déploiement sont documentées.

## 10. Priorités du MVP

### Priorité haute

- authentification;
- approbation des comptes;
- rôles;
- locaux;
- matières;
- tuile Moodle sur la demande;
- choix du local;
- création de demande;
- file d'attente enseignant;
- attribution;
- pause;
- bouton **Je suis prêt**;
- terminaison;
- désassignation;
- historique étudiant.

### Priorité moyenne

- consultation des demandes attribuées aux autres enseignants;
- rafraîchissement périodique des listes enseignant;
- filtres simples;
- messages de confirmation;
- interface admin plus confortable.

### Priorité basse pour le MVP

- statistiques;
- export de données;
- notifications avancées;
- personnalisation des types de demande;
- historique détaillé des changements de statut.

## 11. Hors périmètre du MVP

Les éléments suivants ne sont pas inclus dans la première version :

- connexion avec compte scolaire;
- réattribution directe d'une demande à un autre enseignant;
- messagerie entre étudiant et enseignant;
- notifications poussées;
- WebSockets;
- statistiques avancées;
- application mobile native;
- gestion des horaires;
- gestion des groupes;
- import massif d'utilisateurs;
- export avancé;
- audit détaillé de chaque changement;
- personnalisation complète des permissions.

## 12. Parcours de validation du MVP

Le MVP sera considéré comme valide si le scénario suivant fonctionne :

1. un admin existe dans l'application;
2. un étudiant crée un compte;
3. l'admin approuve le compte étudiant;
4. l'admin crée un local et une matière;
5. l'admin donne le rôle enseignant à un utilisateur;
6. l'étudiant choisit le local;
7. l'étudiant crée une demande;
8. l'enseignant choisit le même local;
9. l'enseignant voit la demande dans la file;
10. l'enseignant s'attribue la demande;
11. l'étudiant voit que la demande est prise en charge;
12. l'enseignant met la demande en pause;
13. l'étudiant indique qu'il est prêt;
14. l'enseignant voit l'indicateur;
15. l'enseignant termine la demande;
16. l'étudiant voit la demande dans son historique.

## 13. Ordre de développement recommandé

1. Projet Laravel, Breeze, Livewire, Tailwind.
2. Migrations et modèles.
3. Seed admin initial.
4. Authentification, rôles et approbation.
5. Admin des locaux et matières.
6. Espace étudiant.
7. Espace enseignant.
8. Permissions et règles métier.
9. Tests principaux.
10. Documentation GitHub et déploiement.

## 14. Livrables du MVP

Le MVP devrait produire :

- une application Laravel fonctionnelle;
- un dépôt GitHub propre;
- une base de données migrable;
- un compte admin initial;
- une interface étudiant;
- une interface enseignant;
- une interface admin minimale;
- une documentation d'installation;
- une documentation de déploiement Apache/MySQL.

## 15. Décisions retenues

- Le MVP se concentre sur le cycle complet d'une demande.
- Les comptes doivent être approuvés avant utilisation.
- L'admin minimal est inclus dans le MVP.
- Les statistiques avancées sont reportées.
- Les notifications avancées sont reportées.
- Le temps réel WebSocket est reporté.
- Les listes enseignant pourront utiliser un rafraîchissement périodique simple.
