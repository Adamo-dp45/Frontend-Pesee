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
