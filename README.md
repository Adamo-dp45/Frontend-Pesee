### Frontend-Webpesee

- 
- **Git**
    > git push -u origin main

- **Production**
    > La 1ère
        > git clone .. .
        > Pour le `.env..` on peut `cp .env .env.local` ou `composer dump-env prod` qui génère un fichier `.env.local.php` qui est plus optimisé
        > composer install --no-dev --optimize-autoloader
            > composer update phpoffice/phpspreadsheet phpunit/phpunit
        > composer require symfony/apache-pack
        > php bin/console cache:clear --env=prod
        > php bin/console cache:warmup --env=prod
    > Les prochaines
        > git pull origin main
        > composer install --no-dev --optimize-autoloader
        > php bin/console cache:clear --env=prod
        > php bin/console cache:warmup --env=prod