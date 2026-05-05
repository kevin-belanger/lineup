# Analyse fonctionnelle - LineUp

## 1. Présentation du projet

**LineUp** est une application destinée aux classes où l'enseignement est individualisé. Elle permet aux étudiants de demander du support à un enseignant sans interrompre le déroulement de la classe, et permet aux enseignants de gérer les demandes selon le local où ils se trouvent.

L'application repose sur une logique de file d'attente par local. Un étudiant choisit le local dans lequel il se trouve, crée une demande d'aide, puis un enseignant présent dans ce même local peut la prendre en charge.

## 2. Objectifs du produit

LineUp vise à :

- organiser les demandes d'aide des étudiants;
- réduire les interruptions informelles en classe;
- donner aux enseignants une vue claire des demandes en attente;
- permettre aux enseignants de s'attribuer des demandes;
- suivre l'état des demandes jusqu'à leur résolution;
- conserver un historique des demandes pour chaque étudiant;
- permettre à l'administration de gérer les locaux, les matières et les rôles utilisateurs.

## 3. Contexte d'utilisation

L'application est utilisée dans une école, dans des classes physiques. Les étudiants et les enseignants sont présents dans un local précis.

Les demandes sont toujours associées à un local. Un enseignant qui choisit un local voit les demandes de ce local seulement. Cela évite que les demandes d'une classe soient mélangées avec celles d'une autre classe.

## 4. Périmètre de l'itération 1

L'itération 1 doit couvrir le fonctionnement essentiel de LineUp :

- création manuelle de comptes;
- rôle étudiant attribué par défaut;
- approbation des nouveaux comptes par un administrateur avant utilisation;
- gestion des rôles par un administrateur;
- choix du local par l'étudiant et l'enseignant;
- création d'une demande par l'étudiant;
- affichage des demandes en attente par local;
- attribution d'une demande à un enseignant;
- liste personnelle des demandes attribuées à un enseignant;
- mise en pause d'une demande;
- signalement par l'étudiant qu'il est prêt à revoir l'enseignant;
- désassignation d'une demande par l'enseignant;
- terminaison d'une demande;
- historique des demandes de l'étudiant;
- interface administrateur pour les locaux, les matières et les utilisateurs.

La réattribution directe d'une demande à un autre enseignant ne fait pas partie de l'itération 1.

## 5. Rôles utilisateurs

### 5.1 Étudiant

L'étudiant est l'utilisateur principal côté demande. Il crée un compte, choisit son local, crée des demandes d'aide et suit leur progression.

Ses besoins principaux sont :

- demander de l'aide rapidement;
- indiquer clairement où il est situé dans la classe;
- préciser la matière, la tuile Moodle où il est rendu et le type de demande;
- voir l'état de ses demandes;
- modifier ou annuler une demande tant qu'elle n'est pas prise en charge;
- indiquer qu'il est prêt à revoir l'enseignant lorsqu'une demande est en pause;
- consulter ses demandes passées.

### 5.2 Enseignant

L'enseignant est responsable de traiter les demandes dans un local. Il choisit un local, voit les demandes en attente, s'attribue des demandes, les met en pause, les termine ou les remet dans la file principale.

Ses besoins principaux sont :

- voir rapidement les demandes en attente dans son local;
- connaître la table de l'étudiant;
- comprendre le type de support demandé;
- s'attribuer une demande;
- gérer sa propre liste de demandes;
- voir les demandes attribuées aux autres enseignants;
- être averti lorsqu'un étudiant indique qu'il est prêt à reprendre une demande en pause;
- terminer une demande lorsqu'elle est résolue.

### 5.3 Administrateur

L'administrateur gère les données structurantes de l'application. Il peut être un enseignant ayant aussi le rôle admin.

Ses besoins principaux sont :

- gérer la liste des locaux;
- gérer la liste des matières;
- gérer les utilisateurs;
- attribuer ou retirer les rôles enseignant et admin.

## 6. Personas

### 6.1 Étudiant - Camille

Camille travaille de façon autonome dans une classe d'enseignement individualisé. Elle avance dans son cours à son rythme, mais elle a parfois besoin d'une explication, d'une validation ou d'une correction.

Camille veut pouvoir demander de l'aide sans se lever et sans interrompre l'enseignant. Elle veut aussi savoir si sa demande est bien en attente ou si un enseignant l'a prise en charge.

### 6.2 Enseignant - Marc

Marc circule dans la classe et aide les étudiants un à un. Plusieurs étudiants peuvent avoir besoin de lui en même temps.

Marc veut voir clairement qui attend, à quelle table se trouve l'étudiant, et quel type d'aide est demandé. Il veut aussi garder une liste des demandes qu'il a prises en charge afin de ne pas perdre le fil.

### 6.3 Enseignante-admin - Sophie

Sophie enseigne aussi, mais elle a des responsabilités de configuration dans l'application. Elle doit préparer les locaux et les matières.

Sophie veut pouvoir gérer ces éléments sans intervention technique. Elle doit aussi pouvoir donner le rôle enseignant aux nouveaux comptes créés.

## 7. Données principales

### 7.1 Utilisateur

Un utilisateur représente une personne qui peut se connecter à LineUp.

Champs principaux :

- nom;
- courriel ou identifiant de connexion;
- mot de passe;
- rôle étudiant;
- rôle enseignant;
- rôle admin;
- statut d'approbation;
- statut actif ou inactif.

Un nouvel utilisateur reçoit le rôle étudiant par défaut, mais son compte doit être approuvé par un administrateur avant de pouvoir utiliser l'application.

### 7.2 Local

Un local représente une classe physique.

Champs principaux :

- nom ou numéro du local;
- description facultative;
- statut actif ou inactif.

### 7.3 Matière

Une matière représente un domaine ou cours général.

Champs principaux :

- local associé;
- nom de la matière;
- description facultative;
- statut actif ou inactif.

### 7.4 Demande

Une demande représente une demande de support créée par un étudiant.

Champs principaux :

- étudiant demandeur;
- local;
- matière;
- numéro de tuile Moodle;
- numéro de table;
- type de demande;
- commentaire;
- statut;
- enseignant assigné, s'il y en a un;
- date et heure de création;
- date et heure de prise en charge;
- date et heure de dernière mise à jour;
- date et heure de terminaison, si applicable.

## 8. Types de demandes

Une demande doit avoir un type parmi :

- **Explication** : l'étudiant a besoin qu'un concept ou une consigne lui soit expliqué.
- **Validation** : l'étudiant veut confirmer qu'il est sur la bonne voie.
- **Correction** : l'étudiant veut qu'un enseignant corrige ou vérifie son travail.

## 9. Statuts d'une demande

Les statuts proposés pour l'itération 1 sont :

- **En attente** : la demande est dans la file principale du local et n'a pas encore été prise en charge.
- **Attribuée** : un enseignant a pris la demande en charge.
- **En pause** : l'enseignant attend que l'étudiant fasse une tâche supplémentaire. La demande reste attribuée au même enseignant.
- **Prêt à revoir** : l'étudiant indique qu'il est prêt à revoir l'enseignant après une pause.
- **Terminée** : la demande est complétée.
- **Annulée** : l'étudiant a annulé la demande avant sa prise en charge.

## 10. Cycle de vie d'une demande

1. L'étudiant se connecte.
2. L'étudiant choisit son local.
3. L'étudiant crée une demande avec matière, tuile Moodle, type, numéro de table et commentaire.
4. La demande est créée avec le statut **En attente**.
5. La demande apparaît dans la file principale du local.
6. Un enseignant du même local s'attribue la demande.
7. La demande passe au statut **Attribuée**.
8. La demande disparaît de la file principale.
9. La demande apparaît dans la liste personnelle de l'enseignant.
10. L'enseignant aide l'étudiant.
11. L'enseignant peut terminer la demande.
12. L'enseignant peut mettre la demande en pause.
13. Si la demande est en pause, l'étudiant peut indiquer qu'il est prêt à revoir l'enseignant.
14. La demande passe au statut **Prêt à revoir**.
15. L'enseignant reçoit un indicateur ou une notification.
16. L'enseignant reprend la demande.
17. L'enseignant termine la demande lorsque le support est complété.

## 11. Règles métier

### 11.1 Comptes et rôles

- Les utilisateurs créent leur compte manuellement.
- Un nouveau compte reçoit automatiquement le rôle étudiant.
- Un nouveau compte est créé avec le statut **En attente d'approbation**.
- Un compte en attente d'approbation ne peut pas créer de demande.
- Un compte en attente d'approbation ne peut pas accéder aux espaces étudiant, enseignant ou admin.
- Un administrateur doit approuver le compte avant qu'il soit utilisable.
- Seul un administrateur peut attribuer le rôle enseignant.
- Seul un administrateur peut attribuer le rôle admin.
- Un enseignant peut aussi être administrateur.

### 11.2 Locaux

- Un étudiant doit choisir un local avant de créer une demande.
- Si un étudiant a une demande active lors de sa connexion, le local associé à cette demande est sélectionné automatiquement.
- Un étudiant ne peut avoir qu'une seule demande active à la fois.
- Si un étudiant change de local alors qu'il a une demande active dans un autre local, il doit confirmer le changement.
- Le changement confirmé de local annule les demandes actives de l'étudiant.
- Un enseignant doit choisir un local avant de consulter les demandes.
- Une demande appartient toujours à un seul local.
- Les demandes d'un local ne doivent pas apparaître dans la file principale d'un autre local.

### 11.3 Création de demande

- L'étudiant doit choisir une matière.
- La matière choisie doit appartenir au local courant de l'étudiant.
- L'étudiant doit indiquer le numéro de tuile Moodle où il est rendu.
- L'étudiant doit indiquer son numéro de table.
- L'étudiant doit choisir un type de demande.
- L'étudiant peut écrire un commentaire.
- La demande est créée dans le local choisi par l'étudiant.
- La création est refusée si l'étudiant a déjà une demande active.

### 11.4 Modification et annulation

- L'étudiant peut modifier sa demande tant qu'elle est au statut **En attente**.
- L'étudiant peut annuler sa demande tant qu'elle est au statut **En attente**.
- Une demande attribuée ne peut plus être modifiée librement par l'étudiant.

### 11.5 Attribution

- Un enseignant peut s'attribuer une demande en attente dans son local.
- Lorsqu'une demande est attribuée, elle disparaît de la file principale.
- Une demande attribuée apparaît dans la liste personnelle de l'enseignant.
- Les autres enseignants peuvent voir qu'une demande est attribuée et à qui elle est attribuée.

### 11.6 Mise en pause

- Un enseignant peut mettre en pause une demande qui lui est attribuée.
- Une demande en pause reste attribuée au même enseignant.
- La mise en pause signifie que l'étudiant doit effectuer une tâche supplémentaire avant de revoir l'enseignant.
- L'étudiant voit que sa demande est en pause.
- L'étudiant peut indiquer qu'il est prêt à revoir l'enseignant.
- L'enseignant reçoit un indicateur lorsque l'étudiant est prêt.

### 11.7 Désassignation

- Un enseignant peut se désassigner d'une demande qui lui est attribuée.
- Une demande désassignée retourne dans la file principale du local.
- La désassignation est une action différente de la mise en pause.

### 11.8 Terminaison

- Un enseignant peut terminer une demande qui lui est attribuée.
- Une demande terminée n'apparaît plus dans la file principale ni dans les demandes actives de l'enseignant.
- Une demande terminée apparaît dans l'historique de l'étudiant.

## 12. Parcours utilisateurs

### 12.1 Parcours d'inscription

1. L'utilisateur ouvre LineUp.
2. Il choisit de créer un compte.
3. Il saisit ses informations.
4. Le compte est créé avec le rôle étudiant.
5. Le compte est placé en attente d'approbation.
6. Un administrateur approuve le compte.
7. L'utilisateur peut utiliser l'application.

### 12.2 Parcours étudiant - Créer une demande

1. L'étudiant se connecte.
2. Il choisit son local.
3. Il accède à son espace étudiant.
4. Il crée une demande.
5. Il choisit une matière.
6. Il indique le numéro de tuile Moodle où il est rendu.
7. Il choisit un type de demande.
8. Il indique son numéro de table.
9. Il ajoute un commentaire, si nécessaire.
10. Il envoie la demande.
11. La demande apparaît dans ses demandes en cours.

### 12.3 Parcours étudiant - Suivre une demande

1. L'étudiant consulte ses demandes en cours.
2. Il voit le statut de chaque demande.
3. Si la demande est en attente, il peut la modifier ou l'annuler.
4. Si la demande est en pause, il peut indiquer qu'il est prêt à revoir l'enseignant.
5. Si la demande est terminée, elle apparaît dans son historique.

### 12.4 Parcours enseignant - Prendre une demande

1. L'enseignant se connecte.
2. Il choisit son local.
3. Il consulte la file principale des demandes en attente.
4. Il sélectionne une demande.
5. Il s'attribue la demande.
6. La demande passe dans sa liste personnelle.
7. L'enseignant va aider l'étudiant à la table indiquée.

### 12.5 Parcours enseignant - Gérer une demande attribuée

1. L'enseignant consulte sa liste personnelle.
2. Il ouvre une demande attribuée.
3. Il peut la marquer comme terminée.
4. Il peut la mettre en pause.
5. Il peut se désassigner de la demande.
6. Si l'étudiant indique qu'il est prêt, l'enseignant voit un indicateur sur la demande.

### 12.6 Parcours administrateur

1. L'administrateur se connecte.
2. Il accède à l'interface d'administration.
3. Il gère les locaux.
4. Il gère les matières.
5. Il consulte les utilisateurs.
6. Il attribue ou retire les rôles enseignant et admin.

## 13. User stories

### 13.1 Étudiant

**US-E01 - Création de compte**

En tant qu'étudiant, je veux créer un compte manuellement afin d'accéder à LineUp.

Critères d'acceptation :

- le compte peut être créé à partir d'un formulaire;
- le compte reçoit automatiquement le rôle étudiant;
- le compte est placé en attente d'approbation;
- l'étudiant ne peut pas utiliser l'application tant que le compte n'est pas approuvé;
- un administrateur peut approuver le compte;
- l'étudiant peut utiliser l'application après approbation.

**US-E02 - Choix du local**

En tant qu'étudiant, je veux choisir mon local afin que ma demande soit envoyée aux enseignants de la bonne classe.

Critères d'acceptation :

- l'étudiant voit la liste des locaux actifs;
- l'étudiant doit choisir un local avant de créer une demande;
- la demande créée est associée au local choisi.

**US-E03 - Création d'une demande**

En tant qu'étudiant, je veux créer une demande avec ma matière, ma tuile Moodle, mon type de besoin, mon numéro de table et un commentaire afin qu'un enseignant puisse m'aider efficacement.

Critères d'acceptation :

- la matière est obligatoire;
- la matière doit appartenir au local courant;
- le numéro de tuile Moodle est obligatoire;
- le type de demande est obligatoire;
- le numéro de table est obligatoire;
- le commentaire est disponible;
- la demande est créée avec le statut **En attente**.

**US-E04 - Modification d'une demande**

En tant qu'étudiant, je veux modifier ma demande tant qu'elle n'est pas prise en charge afin de corriger une information.

Critères d'acceptation :

- une demande au statut **En attente** peut être modifiée par son étudiant;
- une demande attribuée ne peut plus être modifiée par l'étudiant;
- les modifications sont visibles pour les enseignants du local.

**US-E05 - Annulation d'une demande**

En tant qu'étudiant, je veux annuler une demande tant qu'elle n'est pas prise en charge afin de retirer une demande qui n'est plus nécessaire.

Critères d'acceptation :

- une demande au statut **En attente** peut être annulée;
- une demande annulée disparaît de la file principale;
- une demande annulée apparaît dans l'historique de l'étudiant.

**US-E06 - Demande en pause**

En tant qu'étudiant, je veux voir lorsqu'une demande est en pause afin de savoir que je dois faire une tâche avant de revoir l'enseignant.

Critères d'acceptation :

- l'étudiant voit le statut **En pause**;
- l'étudiant peut indiquer qu'il est prêt à revoir l'enseignant;
- l'enseignant assigné reçoit un indicateur lorsque l'étudiant est prêt.

**US-E07 - Historique**

En tant qu'étudiant, je veux consulter mes demandes passées afin de voir ce qui a été terminé ou annulé.

Critères d'acceptation :

- l'étudiant voit ses demandes terminées;
- l'étudiant voit ses demandes annulées;
- l'étudiant ne voit que ses propres demandes dans son historique.

### 13.2 Enseignant

**US-P01 - Choix du local**

En tant qu'enseignant, je veux choisir mon local afin de voir seulement les demandes de la classe où je me trouve.

Critères d'acceptation :

- l'enseignant voit la liste des locaux actifs;
- il doit choisir un local pour accéder aux demandes;
- seules les demandes du local choisi sont affichées.

**US-P02 - Voir la file principale**

En tant qu'enseignant, je veux voir les demandes en attente dans mon local afin de savoir quels étudiants ont besoin d'aide.

Critères d'acceptation :

- seules les demandes au statut **En attente** sont affichées dans la file principale;
- chaque demande affiche la matière, la tuile Moodle, le type, la table et le commentaire;
- les demandes attribuées ne sont pas affichées dans la file principale.

**US-P03 - S'attribuer une demande**

En tant qu'enseignant, je veux m'attribuer une demande afin d'indiquer que je vais m'en occuper.

Critères d'acceptation :

- l'enseignant peut s'attribuer une demande en attente;
- la demande passe au statut **Attribuée**;
- la demande est associée à l'enseignant;
- la demande disparaît de la file principale;
- la demande apparaît dans la liste personnelle de l'enseignant.

**US-P04 - Voir ma liste personnelle**

En tant qu'enseignant, je veux voir les demandes que je me suis attribuées afin de gérer mon travail.

Critères d'acceptation :

- l'enseignant voit ses demandes attribuées;
- l'enseignant voit ses demandes en pause;
- l'enseignant voit les demandes où l'étudiant est prêt à revoir;
- les demandes terminées ne sont plus dans cette liste.

**US-P05 - Mettre une demande en pause**

En tant qu'enseignant, je veux mettre une demande en pause afin de laisser l'étudiant faire une tâche supplémentaire avant de revenir le voir.

Critères d'acceptation :

- seule une demande attribuée à l'enseignant peut être mise en pause par lui;
- la demande reste attribuée au même enseignant;
- l'étudiant voit que la demande est en pause;
- l'étudiant peut signaler qu'il est prêt.

**US-P06 - Terminer une demande**

En tant qu'enseignant, je veux terminer une demande afin d'indiquer que le support est complété.

Critères d'acceptation :

- seule une demande attribuée à l'enseignant peut être terminée par lui;
- la demande passe au statut **Terminée**;
- la demande sort des demandes actives de l'enseignant;
- la demande apparaît dans l'historique de l'étudiant.

**US-P07 - Se désassigner**

En tant qu'enseignant, je veux me désassigner d'une demande afin de la remettre dans la file principale.

Critères d'acceptation :

- l'enseignant peut se désassigner d'une demande qui lui est attribuée;
- la demande n'a plus d'enseignant assigné;
- la demande retourne au statut **En attente**;
- la demande réapparaît dans la file principale du local.

**US-P08 - Voir les demandes des autres enseignants**

En tant qu'enseignant, je veux voir les demandes attribuées aux autres enseignants afin de comprendre la répartition du travail dans le local.

Critères d'acceptation :

- l'enseignant peut voir les demandes attribuées aux autres enseignants du même local;
- le nom de l'enseignant assigné est visible;
- ces demandes sont séparées de sa liste personnelle.

### 13.3 Administrateur

**US-A01 - Gérer les locaux**

En tant qu'administrateur, je veux gérer les locaux afin que les utilisateurs puissent choisir le bon local.

Critères d'acceptation :

- l'administrateur peut créer un local;
- l'administrateur peut modifier un local;
- l'administrateur peut désactiver un local;
- seuls les locaux actifs sont proposés aux utilisateurs.

**US-A02 - Gérer les matières**

En tant qu'administrateur, je veux gérer les matières afin que les étudiants puissent classer leur demande correctement.

Critères d'acceptation :

- l'administrateur peut créer une matière;
- l'administrateur doit associer une matière à un local;
- l'administrateur peut modifier une matière;
- l'administrateur peut désactiver une matière;
- seules les matières actives du local courant sont proposées aux étudiants.

**US-A03 - Gérer les utilisateurs**

En tant qu'administrateur, je veux gérer les utilisateurs afin d'attribuer les bons rôles.

Critères d'acceptation :

- l'administrateur peut voir la liste des utilisateurs;
- l'administrateur peut voir les comptes en attente d'approbation;
- l'administrateur peut approuver un compte;
- l'administrateur peut attribuer le rôle enseignant;
- l'administrateur peut retirer le rôle enseignant;
- l'administrateur peut attribuer le rôle admin;
- l'administrateur peut retirer le rôle admin.

## 14. Permissions

| Action | Étudiant | Enseignant | Admin |
| --- | --- | --- | --- |
| Créer un compte | Oui | Oui | Oui |
| Utiliser un compte non approuvé | Non | Non | Non |
| Créer une demande | Oui | Non requis | Non requis |
| Modifier sa demande en attente | Oui | Non | Non |
| Annuler sa demande en attente | Oui | Non | Non |
| Voir ses demandes | Oui | Non | Non |
| Choisir un local enseignant | Non | Oui | Oui si enseignant |
| Voir les demandes d'un local | Non | Oui | Oui si enseignant |
| S'attribuer une demande | Non | Oui | Oui si enseignant |
| Mettre une demande en pause | Non | Oui | Oui si enseignant |
| Terminer une demande | Non | Oui | Oui si enseignant |
| Se désassigner d'une demande | Non | Oui | Oui si enseignant |
| Gérer les locaux | Non | Non | Oui |
| Gérer les matières | Non | Non | Oui |
| Approuver les nouveaux comptes | Non | Non | Oui |
| Gérer les rôles utilisateurs | Non | Non | Oui |

## 15. Écrans principaux

### 15.1 Écrans publics

- Connexion;
- Création de compte.

### 15.2 Espace étudiant

- Choix du local;
- Tableau de bord étudiant;
- Formulaire de création de demande;
- Modification d'une demande en attente;
- Demandes en cours;
- Historique des demandes.

### 15.3 Espace enseignant

- Choix du local;
- File principale des demandes en attente;
- Mes demandes attribuées;
- Demandes attribuées aux autres enseignants;
- Détail d'une demande.

### 15.4 Espace administrateur

- Gestion des locaux;
- Gestion des matières;
- Gestion des utilisateurs et des rôles.
- Approbation des nouveaux comptes.

## 16. Notifications et indicateurs

Pour l'itération 1, les notifications peuvent être simples et visibles dans l'interface.

Exemples :

- indicateur sur une demande lorsque l'étudiant est prêt à revoir l'enseignant;
- badge dans la liste personnelle de l'enseignant;
- mise en évidence visuelle du statut **Prêt à revoir**.

Les notifications en temps réel peuvent être ajoutées plus tard si nécessaire, mais l'interface devrait au minimum se mettre à jour régulièrement ou au changement de page.

## 17. Hors périmètre de l'itération 1

Les éléments suivants sont exclus de la première itération :

- connexion avec compte scolaire;
- réattribution directe d'une demande à un autre enseignant;
- messagerie complète entre étudiant et enseignant;
- notifications poussées avancées;
- statistiques détaillées;
- planification d'horaires;
- gestion avancée des groupes;
- application mobile native.

## 18. Questions ouvertes

Les points suivants devront être clarifiés avant ou pendant la conception technique :

- Le commentaire de la demande est-il obligatoire ou facultatif?
- Le numéro de table est-il un nombre libre ou une liste prédéfinie par local?
- Le numéro de tuile Moodle doit-il accepter seulement des chiffres entiers positifs?
- Un étudiant peut-il avoir plusieurs demandes actives en même temps?
- Un enseignant peut-il choisir plusieurs locaux en même temps?
- Faut-il conserver une trace des changements de statut?
- Faut-il permettre à un admin de désactiver un utilisateur?
- Les données doivent-elles être conservées d'une année scolaire à l'autre?

## 19. Proposition de priorité MVP

### Priorité 1 - Fonctionnement minimal

- inscription et connexion;
- approbation des comptes;
- rôles étudiant, enseignant et admin;
- choix du local;
- création de demandes;
- file d'attente par local;
- attribution d'une demande;
- terminaison d'une demande;
- historique étudiant.

### Priorité 2 - Gestion complète de la demande

- modification et annulation par l'étudiant avant prise en charge;
- mise en pause;
- bouton étudiant **Je suis prêt**;
- indicateur côté enseignant;
- désassignation.

### Priorité 3 - Administration

- gestion des locaux;
- gestion des matières;
- gestion des utilisateurs;
- gestion des rôles.

## 20. Résumé

LineUp est une application de gestion de demandes d'aide en classe, organisée par local physique. Les étudiants créent des demandes structurées avec matière, tuile Moodle, type, table et commentaire. Les enseignants consultent les demandes du local, s'attribuent celles qu'ils prennent en charge, puis les mettent en pause, les terminent ou les remettent dans la file principale.

L'administration permet de contrôler les données de référence et les rôles. L'itération 1 doit se concentrer sur le cycle complet d'une demande, la séparation par local et les permissions essentielles.
