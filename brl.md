### Brl

- 

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



<form method="GET" class="flex items-center gap-3 mb-6">
    <div class="flex items-center gap-2">
        <label for="date_debut" class="text-sm text-muted-foreground whitespace-nowrap">Du</label>
        <input
            type="date"
            id="date_debut"
            name="date_debut"
            value="{{ date_debut }}"
            class="px-3 py-1.5 text-sm border border-input rounded-lg bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
        >
    </div>
    <div class="flex items-center gap-2">
        <label for="date_fin" class="text-sm text-muted-foreground whitespace-nowrap">au</label>
        <input
            type="date"
            id="date_fin"
            name="date_fin"
            value="{{ date_fin }}"
            class="px-3 py-1.5 text-sm border border-input rounded-lg bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
        >
    </div>
    <button
        type="submit"
        class="px-4 py-1.5 text-sm bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 transition-colors"
    >
        Filtrer
    </button>
    <a
        href="{{ path('home') }}"
        class="px-4 py-1.5 text-sm border border-input rounded-lg text-muted-foreground hover:bg-accent transition-colors"
    >
        Ce mois
    </a>
</form>




PS C:\Users\adamo\Documents\Web\Webpesee\Frontend-Webpesee> npx shadcn@latest add tooltip
✔ Checking registry.
✔ Created 1 file:
  - assets\components\ui\tooltip.tsx
The `tooltip` component has been added. Remember to wrap your app with the `TooltipProvider` component.

```tsx title="app/layout.tsx"
import { TooltipProvider } from "@/components/ui/tooltip"

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>
        <TooltipProvider>{children}</TooltipProvider>
      </body>
    </html>
  )
}
```





Certains fichiers ont été créés et/ou mis à jour pour configurer vos nouveaux paquets.
Veuillez les examiner, les modifier et les valider (commit) : ces fichiers vous appartiennent.

 instructions pour doctrine/doctrine-bundle :

  * Modifiez votre configuration DATABASE_URL dans .env

  * Configurez le pilote (postgresql) et
    la version du serveur (16) dans config/packages/doctrine.yaml

 instructions pour phpunit/phpunit :

  * Écrivez des cas de test dans le dossier tests/
  * Utilisez la commande make:test de MakerBundle comme raccourci !
  * Exécutez les tests avec php bin/phpunit

 instructions pour symfony/messenger :

  * Vous êtes prêt à utiliser le composant Messenger. Vous pouvez définir vos propres bus de messages
    ou commencer à utiliser celui par défaut dès maintenant en injectant le service message_bus
    ou en utilisant Symfony\Component\Messenger\MessageBusInterface comme type dans votre code.

  * Pour envoyer des messages vers un transport et les traiter de manière asynchrone :

    1. Mettez à jour la variable d'environnement MESSENGER_TRANSPORT_DSN dans .env si nécessaire
       ainsi que framework.messenger.transports.async dans config/packages/messenger.yaml ;
    2. (si vous utilisez Doctrine) Générez une migration Doctrine avec bin/console doctrine:migration:diff
       et exécutez-la avec bin/console doctrine:migration:migrate
    3. Acheminez vos classes de messages vers le transport asynchrone dans config/packages/messenger.yaml.

  * Consultez la documentation sur https://symfony.com/doc/current/messenger.html

 instructions pour symfony/stimulus-bundle :


  * Ajoutez un attribut data-controller="hello" à n'importe quel élément pour essayer le contrôleur d'exemple.

  * Vous utilisez Symfony Reprise ? Activez Stimulus en ajoutant l'option stimulus au
    plugin Symfony() dans votre fichier vite.config ou rsbuild.config :

        Symfony({
            stimulus: './assets/controllers.json',
        })

  * Vous utilisez Webpack Encore ? Installez le pont Stimulus (bridge), qui n'est plus
    ajouté automatiquement depuis StimulusBundle 3.3 :

      npm install --save-dev @symfony/stimulus-bridge

 instructions pour symfony/mailer :

  * Vous êtes prêt à envoyer des e-mails.

  * Si vous souhaitez envoyer des e-mails via un fournisseur pris en charge, installez
    le pont (bridge) correspondant. Par exemple, exécutez `composer require mailgun-mailer` pour Mailgun.

  * Si vous souhaitez envoyer des e-mails de manière asynchrone :

    1. Installez le composant Messenger en exécutant `composer require messenger` ;
    2. Ajoutez `'Symfony\Component\Mailer\Messenger\SendEmailMessage': amqp` au
       fichier `config/packages/messenger.yaml` sous `framework.messenger.routing`
       et remplacez `amqp` par le nom du transport de votre choix.

  * Consultez la documentation sur https://symfony.com/doc/master/mailer.html

Aucune alerte de vulnérabilité de sécurité trouvée.
