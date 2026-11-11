# Webpesee — le web

> Ce fichier est fait pour qu'on s'y retrouve après six mois d'absence. **Les règles métier ne sont
> pas ici** : elles vivent toutes dans `Backend-Webpesee-V2`, dont le README est le document de
> référence. Celui-ci ne parle que de l'affichage et de ses pièges.

---

## 1. Ce que cette application est — et n'est pas

Une application Symfony qui **consomme l'API** et n'a aucune base de données à elle. Elle
s'authentifie en JWT, garde la charge utile de `/api/me` en session, et **ne décide rien**.

La règle tient en une phrase : **quand un écran doit savoir s'il peut proposer un bouton, l'API le
lui a déjà dit.**

- Les droits arrivent à plat dans `/api/me` (`SITE_VOIR`, `PAIEMENT_PAYER`…). `AccesVoter` ne fait
  qu'un `in_array` dessus — il ne tient aucune table.
- Les verdicts par ligne arrivent avec la ligne : `motifNonPayable` sur une pesée,
  `droitsSurCeCompte` et `motifNonGerable` sur un compte.
- Un motif affiché est **mot pour mot** celui que l'API renverrait en refusant. L'écran annonce la
  réponse du serveur, il ne la paraphrase pas.

Ce dépôt a tenu trois copies de règles de l'API — la table des droits, la hiérarchie des comptes,
les libellés de rôle. Elles avaient commencé à diverger en silence : un bouton absent ne lève
aucune erreur. Elles ont toutes été supprimées. **Ne pas les réintroduire.**

---

## 2. Comment c'est assemblé

```
Twig                 toute la mise en page, les formulaires, les fiches
React (îlots)        seulement les TABLES : tri, filtres, pagination côté serveur
JavaScript maison    assets/modules/*.js — pas de Stimulus pour notre code
Tailwind 4           + des composants shadcn portés en Twig (templates/components/)
```

Les îlots React sont montés par Symfony UX :

```twig
<div {{ react_component('Caisse/TableAPayer', { items, meta, queryParams, sortMeta }) }}></div>
```

⚠ `react_component()` rend des **attributs**, pas une balise. Il va donc **dans** un `<div>`, pas à
la place.

### Le JavaScript

Pas de contrôleur Stimulus pour le code applicatif : un module par sujet dans `assets/modules/`,
branché dans `app.js`. Et `app.js` sépare deux familles, pour une raison qui coûte cher à
redécouvrir :

```js
/* UNE SEULE FOIS pour la session — écouteurs sur 'document' ou 'window', qui survivent à Turbo */
initConfirmation()
initDropdowns()
initSidebarResize()

/* À CHAQUE PAGE — éléments du corps, que Turbo remplace à chaque visite */
document.addEventListener('turbo:load', () => { initTheme(); initSidebar(); … })
```

Reposer un écouteur de `document` à chaque `turbo:load` **l'empile** : au troisième écran visité, un
clic est traité trois fois. Mesuré, puis corrigé. Le corollaire : un écouteur posé une fois ne doit
retenir **aucun élément** — il retrouve ce dont il a besoin au moment du geste, car les nœuds de la
page précédente sont détachés.

---

## 3. La forme d'un écran de liste

Tous les écrans de liste se ressemblent, et c'est voulu.

```php
private const TRIS = ['createdAt', 'montant'];              // liste blanche
private const FILTRES = ['statut' => 'statut', 'site' => 'site'];  // clé du form => filtre de l'API

$donnees = $this->table->handleIndex('/api/…', $request->query->all(), self::FILTRES, self::TRIS);
```

- **La liste blanche des tris n'est pas du zèle** : un tri inconnu fait répondre l'API en erreur,
  ou pire, est ignoré en laissant croire au classement demandé.
- `handleIndex()` rend `items / meta / queryParams / sortMeta`, qu'on passe tels quels au composant.
- Les filtres déclarés en PHP et ceux déclarés dans le `.tsx` **doivent porter les mêmes noms**.
- Chaque ligne est enrichie par le contrôleur avec ses URL et ses booléens d'action. Le droit et
  l'état sont **deux conditions distinctes** : `'traitable' => $enAttente && $traiteur`.

`GestionController` porte les outils communs : `options()` (rend `id => libellé`), `jetonValide()`,
`EN_ERREUR` (422 pour qu'un formulaire invalide ne soit pas servi en 200).

---

## 4. Les pièges qui coûtent une journée

| Piège | Ce qui se passe |
|---|---|
| **Turbo veut une redirection** | Un POST qui rend directement une vue laisse la page figée. Toujours rediriger après écriture. |
| **La confirmation est sur le BOUTON** | `data-confirmer="…"` sur le bouton, jamais un `confirm()` : le module rejoue le **même** clic une fois accepté, donc le jeton CSRF et Turbo suivent naturellement. |
| **`react_component` rend des attributs** | Il se met **dans** une balise, pas à sa place. |
| **`Select` de base-ui veut des `items`** | Lui passer des enfants seuls ne suffit pas. |
| **`array_flip` sur les options** | `options()` rend `id => libellé`, `ChoiceType` attend l'inverse. |
| **`csrf_token_id`** | Celui des formulaires (`'demande'`, `'paiement'`…) n'est **pas** celui des actions de table (`'gestion'`, via `GestionController::JETON`). |
| **`public/build` est suivi par git** | L'hébergeur n'a pas npm. Lancer **`npm run build`** (pas `dev`) avant tout commit, sinon on déploie des bundles de développement, dix fois plus lourds. |
| **Un commentaire Twig dans un littéral** | `{# … #}` au milieu d'un `{% set x = { … } %}` est une erreur de syntaxe. Le mettre au-dessus. |

---

## 5. Où se trouve quoi

```
src/
  Controller/          Un par écran. GestionController porte le commun
  Domain/Helper/       ApiHelper (appels), TableHelper (listes), ApiExceptionHandlerHelper
  Domain/Service/      ApiClientService (jeton, session), ExportService (PDF, Excel)
  Entity/ApiUser.php   L'utilisateur connecté, hydraté depuis /api/me
  Form/                Les FormType
  Security/            ApiAuthenticator, ApiUserProvider, AccesVoter (affichage seulement)
assets/
  app.js               Le point d'entrée, et la séparation « une fois » / « à chaque page »
  modules/*.js         Le JavaScript maison
  react/controllers/   Les tables, par domaine
  react/models/        Les formes que l'API renvoie — miroir des groupes de sérialisation
templates/
  components/          Les composants shadcn portés en Twig
  partials/            _etat (les pastilles), _kpi, _donnee
```

`assets/react/models/` doit suivre les groupes `read:` de l'API. Quand un champ apparaît côté
serveur, il s'ajoute ici — sinon TypeScript ne le connaît pas.

---

## 6. Commandes

```bash
composer install
npm install

npm run dev       # développement
npm run watch     # développement, en continu
npm run build     # production — OBLIGATOIRE avant un commit

php -S localhost:8001 -t public
php bin/console lint:twig templates
php bin/console lint:container
npx tsc --noEmit
```

⚠ Ne pas passer `public/index.php` en script routeur à `php -S` : les fichiers statiques sortent
alors avec une erreur PHP collée à la suite, Turbo ne démarre plus, et rien ne marche.

L'API doit tourner en parallèle (`localhost:8000` par défaut, réglé dans `.env`).
