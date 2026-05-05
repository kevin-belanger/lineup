# Modèle de données - LineUp

## 1. Objectif

Ce document décrit le modèle de données initial de LineUp pour l'itération 1.

Le modèle vise à rester simple, lisible et directement traduisible en migrations Laravel avec MySQL ou MariaDB.

## 2. Tables principales

Le modèle initial repose sur quatre tables principales :

- `users`;
- `classrooms`;
- `subjects`;
- `support_requests`.

Des tables supplémentaires pourront être ajoutées plus tard pour les statistiques avancées, les notifications ou l'historique détaillé des événements.

## 3. Table `users`

La table `users` représente les comptes utilisateurs.

Elle sera basée sur la table standard de Laravel Breeze, avec des champs supplémentaires pour les rôles et l'approbation des comptes.

### Champs

| Champ | Type proposé | Obligatoire | Description |
| --- | --- | --- | --- |
| `id` | bigint unsigned | Oui | Identifiant unique |
| `name` | string | Oui | Nom de l'utilisateur |
| `email` | string | Oui | Adresse courriel ou identifiant de connexion |
| `email_verified_at` | timestamp nullable | Non | Champ standard Laravel |
| `password` | string | Oui | Mot de passe haché |
| `is_student` | boolean | Oui | Indique si l'utilisateur a le rôle étudiant |
| `is_teacher` | boolean | Oui | Indique si l'utilisateur a le rôle enseignant |
| `is_admin` | boolean | Oui | Indique si l'utilisateur a le rôle admin |
| `is_approved` | boolean | Oui | Indique si le compte a été approuvé par un admin |
| `approved_at` | timestamp nullable | Non | Date et heure d'approbation du compte |
| `approved_by` | foreign id nullable | Non | Admin ayant approuvé le compte |
| `is_active` | boolean | Oui | Indique si le compte est actif |
| `remember_token` | string nullable | Non | Champ standard Laravel |
| `created_at` | timestamp nullable | Non | Date de création |
| `updated_at` | timestamp nullable | Non | Date de dernière modification |

### Valeurs par défaut

Lorsqu'un utilisateur crée un compte :

- `is_student = true`;
- `is_teacher = false`;
- `is_admin = false`;
- `is_approved = false`;
- `approved_at = null`;
- `approved_by = null`;
- `is_active = true`.

### Règles métier

- Un compte est étudiant par défaut.
- Un compte nouvellement créé doit être approuvé par un administrateur avant d'être utilisable.
- Un compte non approuvé ne peut pas accéder aux espaces étudiant, enseignant ou admin.
- Un administrateur peut approuver un compte.
- Un administrateur peut attribuer ou retirer les rôles enseignant et admin.
- Un enseignant peut aussi être administrateur.

### Contraintes

- `email` doit être unique.
- `approved_by` référence `users.id`.
- `approved_by` est nullable.

### Index recommandés

- `email`;
- `is_approved`;
- `is_active`;
- `is_teacher`;
- `is_admin`.

## 4. Table `classrooms`

La table `classrooms` représente les locaux physiques.

### Champs

| Champ | Type proposé | Obligatoire | Description |
| --- | --- | --- | --- |
| `id` | bigint unsigned | Oui | Identifiant unique |
| `name` | string | Oui | Nom ou numéro du local |
| `description` | text nullable | Non | Description facultative |
| `is_active` | boolean | Oui | Indique si le local est actif |
| `created_at` | timestamp nullable | Non | Date de création |
| `updated_at` | timestamp nullable | Non | Date de dernière modification |

### Règles métier

- Seuls les locaux actifs sont proposés aux étudiants et aux enseignants.
- Une demande appartient toujours à un seul local.
- Les demandes sont séparées par local.

### Contraintes

- `name` doit être unique.

### Index recommandés

- `name`;
- `is_active`.

## 5. Table `subjects`

La table `subjects` représente les matières.

### Champs

| Champ | Type proposé | Obligatoire | Description |
| --- | --- | --- | --- |
| `id` | bigint unsigned | Oui | Identifiant unique |
| `classroom_id` | foreign id | Oui | Local auquel la matière est associée |
| `name` | string | Oui | Nom de la matière |
| `description` | text nullable | Non | Description facultative |
| `is_active` | boolean | Oui | Indique si la matière est active |
| `created_at` | timestamp nullable | Non | Date de création |
| `updated_at` | timestamp nullable | Non | Date de dernière modification |

### Règles métier

- Seules les matières actives sont proposées aux étudiants.
- Une matière appartient à un seul local.
- Lorsqu'un étudiant choisit un local, seules les matières actives de ce local sont proposées.
- Une demande appartient à une matière.

### Contraintes

- `classroom_id` référence `classrooms.id`.
- La combinaison `classroom_id` + `name` doit être unique.

### Index recommandés

- `classroom_id`;
- `name`;
- `is_active`.

## 6. Table `support_requests`

La table `support_requests` représente les demandes d'aide créées par les étudiants.

### Champs

| Champ | Type proposé | Obligatoire | Description |
| --- | --- | --- | --- |
| `id` | bigint unsigned | Oui | Identifiant unique |
| `student_id` | foreign id | Oui | Étudiant ayant créé la demande |
| `classroom_id` | foreign id | Oui | Local associé à la demande |
| `subject_id` | foreign id | Oui | Matière associée à la demande |
| `moodle_tile_number` | unsigned integer | Oui | Numéro de tuile Moodle où l'étudiant est rendu |
| `assigned_teacher_id` | foreign id nullable | Non | Enseignant assigné à la demande |
| `table_number` | string | Oui | Numéro ou code de table |
| `type` | string | Oui | Type de demande |
| `status` | string | Oui | Statut courant de la demande |
| `comment` | text nullable | Non | Commentaire écrit par l'étudiant |
| `assigned_at` | timestamp nullable | Non | Date et heure de prise en charge |
| `completed_at` | timestamp nullable | Non | Date et heure de terminaison |
| `created_at` | timestamp nullable | Non | Date de création |
| `updated_at` | timestamp nullable | Non | Date de dernière modification |

### Types de demandes

Valeurs internes :

- `explanation`;
- `validation`;
- `correction`.

Libellés affichés :

- Explication;
- Validation;
- Correction.

### Statuts de demandes

Valeurs internes :

- `waiting`;
- `assigned`;
- `paused`;
- `ready`;
- `completed`;
- `cancelled`.

Libellés affichés :

- En attente;
- Attribuée;
- En pause;
- Prêt à revoir;
- Terminée;
- Annulée.

### Règles métier

#### Création

Lorsqu'une demande est créée :

- `student_id` correspond à l'utilisateur connecté;
- `classroom_id` correspond au local choisi par l'étudiant;
- `subject_id` correspond à une matière active du local choisi;
- `moodle_tile_number` est saisi par l'étudiant;
- un étudiant ne peut pas créer une nouvelle demande s'il a déjà une demande active;
- `assigned_teacher_id = null`;
- `type` est obligatoire;
- `status = waiting`;
- `assigned_at = null`;
- `completed_at = null`.

#### Modification par l'étudiant

- Une demande peut être modifiée par l'étudiant seulement si elle est au statut `waiting`.
- Une demande attribuée ne peut plus être modifiée librement par l'étudiant.

#### Annulation par l'étudiant

- Une demande peut être annulée par l'étudiant seulement si elle est au statut `waiting`.
- Lorsqu'une demande est annulée, `status = cancelled`.

#### Attribution par un enseignant

Lorsqu'un enseignant prend une demande :

- `assigned_teacher_id` reçoit l'id de l'enseignant;
- `assigned_at` reçoit la date et l'heure courantes;
- `status = assigned`.

La demande disparaît alors de la file principale et apparaît dans la liste personnelle de l'enseignant.

#### Mise en pause

Lorsqu'un enseignant met une demande en pause :

- `status = paused`;
- `assigned_teacher_id` reste rempli;
- `assigned_at` reste rempli.

La demande reste attribuée au même enseignant.

#### Étudiant prêt à revoir

Lorsqu'un étudiant indique qu'il est prêt à revoir l'enseignant :

- `status = ready`;
- `assigned_teacher_id` reste rempli;
- `assigned_at` reste rempli.

L'enseignant voit un indicateur dans sa liste personnelle.

#### Désassignation

Lorsqu'un enseignant se désassigne :

- `assigned_teacher_id = null`;
- `assigned_at = null`;
- `status = waiting`.

La demande retourne dans la file principale du local.

#### Terminaison

Lorsqu'un enseignant termine une demande :

- `status = completed`;
- `completed_at` reçoit la date et l'heure courantes.

La demande sort des demandes actives de l'enseignant et apparaît dans l'historique de l'étudiant.

### Contraintes

- `student_id` référence `users.id`.
- `classroom_id` référence `classrooms.id`.
- `subject_id` référence `subjects.id`.
- `assigned_teacher_id` référence `users.id`.
- `assigned_teacher_id` est nullable.
- `comment` est nullable.
- `assigned_at` est nullable.
- `completed_at` est nullable.

### Index recommandés

- `student_id`;
- `classroom_id`;
- `subject_id`;
- `moodle_tile_number`;
- `assigned_teacher_id`;
- `status`;
- `type`;
- `created_at`;
- `classroom_id, status`;
- `assigned_teacher_id, status`;

## 7. Relations principales

### Utilisateur comme étudiant

Un utilisateur peut créer plusieurs demandes.

```text
users.id -> support_requests.student_id
```

### Utilisateur comme enseignant assigné

Un utilisateur enseignant peut être assigné à plusieurs demandes.

```text
users.id -> support_requests.assigned_teacher_id
```

### Utilisateur comme admin approbateur

Un utilisateur admin peut approuver plusieurs comptes.

```text
users.id -> users.approved_by
```

### Local

Un local peut contenir plusieurs demandes.

```text
classrooms.id -> support_requests.classroom_id
```

### Matière

Une matière appartient à un local.

```text
classrooms.id -> subjects.classroom_id
```

Une matière peut aussi être liée à plusieurs demandes.

```text
subjects.id -> support_requests.subject_id
```

## 8. Règles de suppression recommandées

Pour préserver l'historique, il est préférable d'éviter les suppressions physiques des données importantes.

### Utilisateurs

- Ne pas supprimer physiquement un utilisateur ayant des demandes.
- Utiliser `is_active = false` pour désactiver un compte.

### Locaux

- Ne pas supprimer physiquement un local ayant des demandes.
- Utiliser `is_active = false` pour le retirer des listes.

### Matières

- Ne pas supprimer physiquement une matière ayant des demandes.
- Utiliser `is_active = false`.

### Demandes

- Ne pas supprimer physiquement les demandes.
- Utiliser les statuts `completed` ou `cancelled`.

## 9. Données initiales possibles

Des seeders Laravel pourront créer quelques données de départ :

- un compte admin initial;
- quelques locaux;
- quelques matières;
- éventuellement quelques demandes de démonstration en environnement local.

Les demandes de démonstration ne devraient pas être chargées en production.

## 10. Tables futures possibles

Ces tables ne sont pas nécessaires pour l'itération 1, mais peuvent être utiles plus tard.

### `support_request_events`

Permettrait de conserver chaque changement de statut avec :

- demande concernée;
- ancien statut;
- nouveau statut;
- utilisateur ayant déclenché l'action;
- date et heure.

### `notifications`

Permettrait de gérer des notifications plus avancées.

### `teacher_room_sessions`

Permettrait de suivre les locaux actuellement choisis par les enseignants.

### `settings`

Permettrait de gérer des paramètres globaux de l'application.

## 11. Décisions retenues

- Le modèle initial contient `users`, `classrooms`, `subjects` et `support_requests`.
- Les rôles sont stockés directement sur `users` avec des booléens.
- Les nouveaux comptes doivent être approuvés par un admin.
- Les demandes sont séparées par local.
- Les matières sont associées à un seul local.
- La tuile Moodle n'est pas une donnée de référence : elle est saisie comme chiffre directement sur chaque demande.
- Les types de demandes sont codés dans l'application pour l'itération 1.
- Les statuts de demandes sont codés dans l'application pour l'itération 1.
- Les dates retenues pour les statistiques simples sont `created_at`, `assigned_at` et `completed_at`.
- `assigned_at` est remis à `null` si un enseignant se désassigne.
- L'historique détaillé des événements est reporté à une version future.
