### Brl

- **Command**
    > php -S localhost:5000 -t public | symfony serve
    > php bin/console cache:clear
    > php bin/console cache:warmup
    > php bin/console debug:router
    > php bin/console make:controller
    > php bin/console make:entity
    > php bin/console make:form
    > php bin/console make:voter
    > php bin/console make:listener
    > php bin/console make:subscriber
    > php bin/console translation:extract --force fr --format=yaml
- 

- 
> Sur ApiExceptionSubscriber, je ne suis pas d'accord — et je l'ai vérifié plutôt que d'argumenter. Il ne fait pas double emploi avec ApiExceptionHandlerHelper : le helper est appelé dans un catch, là où le contrôleur sait quoi faire de l'erreur ; le subscriber rattrape les appels qui ne sont dans aucun catch. Or il y en a beaucoup — 64 appels API pour 23 catch, et BilanController, PeseeController, TableauDeBordController n'en ont aucun. Je l'ai donc remis, avec un commentaire qui dit précisément en quoi les deux diffèrent — c'est justement la confusion qui t'a fait penser qu'il était devenu inutile. Si tu veux vraiment t'en passer, il faut d'abord entourer chaque lecture d'un try/catch, ce qui ramène le bruit que le subscriber avait supprimé.
> L'alias @ est déclaré dans tsconfig mais pas dans Webpack — les composants shadcn l'utilisent. webpack.config.js
> Un défaut sérieux trouvé en testant, qui touchait tous les écrans. En rejetant sans motif, l'API répondait bien 422 — et rien ne s'affichait. Cause : ApiExceptionHandlerHelper traite tout 422 comme une erreur de validation et boucle sur getViolations(). Or un 422 métier (« une demande est déjà en attente », « solde insuffisant », « déjà traitée ») n'a pas de violations par champ, seulement un message. La boucle tournait à vide, la méthode rendait null, le formulaire se réaffichait muet : le geste semblait n'avoir aucun effet. J'ai ajouté le cas manquant — sans violations, le message devient une erreur globale du formulaire. C'est le helper que j'ai porté de Transport, donc le défaut y est probablement aussi.



Le site web des clients. Il ne touche jamais la base : il appelle l'API avec le jeton de l'utilisateur connecté, et c'est l'API qui tranche toutes les autorisations.

Vue d'ensemble du système, rôles et modèle d'argent : voir le `README.md` à la racine.

- **Développement**
    > composer install
    > npm ci
    > npm run dev  (ou `npm run watch`)
    > php -S localhost:8001 -t public
    > `ENDPOINT` dans `.env.local` doit pointer sur l'API

- **Vérification**
    > vendor/bin/phpstan analyse   : niveau 6, lancer `cache:warmup` d'abord si le conteneur manque
    > npx tsc --noEmit             : les îlots React
    > php bin/console lint:twig templates

- **Git**
    > git push -u origin main

- **Production**
    > La 1ère
        > git clone .. .
        > Pour le `.env..` on peut `cp .env .env.local` ou `composer dump-env prod` qui génère un fichier `.env.local.php` qui est plus optimisé
        > composer install --no-dev --optimize-autoloader
            > composer update phpoffice/phpspreadsheet phpunit/phpunit
        > composer require symfony/apache-pack
        > npm ci && npm run build : indispensable, `public/build/` n'est pas versionné
        > php bin/console cache:clear --env=prod
        > php bin/console cache:warmup --env=prod
    > Les prochaines
        > git pull origin main
        > composer install --no-dev --optimize-autoloader
        > npm ci && npm run build
        > php bin/console cache:clear --env=prod
        > php bin/console cache:warmup --env=prod

- **Bon à savoir**
    > Turbo n'affiche pas une réponse `200` à un `POST` : un formulaire doit rediriger après succès, ou renvoyer `422` pour être réaffiché avec ses erreurs.
    > Le filtre Twig `merge` renumérote les clés entières : les listes déroulantes indexées par identifiant se construisent en PHP (`GestionController::options()`), pas dans le gabarit.
    > Les exports du bilan repaginent l'API : ils sortent tout le résultat filtré, pas la page affichée.
