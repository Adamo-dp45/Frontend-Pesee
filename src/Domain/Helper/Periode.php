<?php

namespace App\Domain\Helper;

use Symfony\Component\HttpFoundation\Request;

/*
    - La plage de dates affichée, partagée par le tableau de bord et le bilan.
    - Par défaut le mois en cours : c'est ce qu'on regarde en arrivant, et ça évite d'agréger des
      années de pesées quand personne n'a rien demandé.
*/
final class Periode
{
    private function __construct(
        public readonly string $du,
        public readonly string $au
    ) {
    }

    public static function depuis(Request $request): self
    {
        return new self(
            self::date($request->query->get('du')) ?? (new \DateTimeImmutable('first day of this month'))->format('Y-m-d'),
            self::date($request->query->get('au')) ?? (new \DateTimeImmutable('last day of this month'))->format('Y-m-d')
        );
    }

    /**
     * @return array{du: string, au: string}
     */
    public function versParametres(): array
    {
        return ['du' => $this->du, 'au' => $this->au];
    }

    public function libelle(): string
    {
        $formateur = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);

        return sprintf(
            'du %s au %s',
            $formateur->format(new \DateTimeImmutable($this->du)),
            $formateur->format(new \DateTimeImmutable($this->au))
        );
    }

    // Une date invalide est traitée comme absente : on retombe sur le mois en cours
    private static function date(mixed $valeur): ?string
    {
        if (!is_string($valeur) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) !== 1) {
            return null;
        }

        return $valeur;
    }
}
