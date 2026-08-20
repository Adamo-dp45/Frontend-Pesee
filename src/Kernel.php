<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /*
        - TOUTE l'application vit en UTC : PHP, Doctrine et la sérialisation des dates s'y réfèrent.
          Sans réglage explicite, le fuseau vient du 'php.ini' de la machine — celui du poste de
          développement, celui de l'hébergeur, celui du conteneur — et rien ne signale qu'ils
          diffèrent. Une pesée écrite ici et relue là-bas changerait alors d'heure en silence.

        - Le constructeur, et non le 'php.ini' : c'est le seul point que traversent les TROIS portes
          d'entrée — la requête HTTP, la console et les tests. Le poser ailleurs en oublierait une.

        - La Côte d'Ivoire est à UTC+0 et sans heure d'été : l'heure stockée est donc aussi celle
          que lit l'utilisateur, sans conversion.
    */
    public function __construct(string $environment, bool $debug)
    {
        date_default_timezone_set('UTC');

        parent::__construct($environment, $debug);
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
