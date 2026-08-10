<?php

namespace App\Domain\Service;

use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\Periode;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twig\Environment;

/*
    - Exports du bilan, produits côté serveur.

    - Le point qui change tout : ils REPAGINENT l'API pour sortir l'intégralité du résultat
      filtré. L'ancien code exportait en lisant le tableau HTML affiché, donc uniquement la page
      en cours — un bilan de trois mille pesées produisait un fichier de vingt-cinq lignes, sans
      que rien ne le signale.

    - Un plafond existe malgré tout : au-delà, on préfère un fichier tronqué avec un avertissement
      à une requête qui expire au bout de deux minutes.
*/
final class ExportService
{
    private const PAR_PAGE = 500;
    private const LIGNES_MAX = 20_000;

    /** Colonnes du fichier, dans l'ordre de l'écran « BILAN STANDARD ». */
    private const COLONNES = [
        'numticket' => 'N° ticket',
        'peseeAt2' => 'Date',
        'site' => 'Pont bascule',
        'mouvement' => 'Opération',
        'produit' => 'Produit',
        'fournisseur' => 'Planteur',
        'client' => 'Client',
        'provenance' => 'Provenance',
        'destination' => 'Destination',
        'transporteur' => 'Transporteur',
        'chauffeur' => 'Chauffeur',
        'immatriculation' => 'Véhicule',
        'poids1' => 'Poids 1',
        'poids2' => 'Poids 2',
        'poidsnet' => 'Poids net',
    ];

    /** Colonnes valorisées, ajoutées pour « Imprimer avec gain ». */
    private const COLONNES_MONTANTS = [
        'prixunitaire' => 'Prix unitaire',
        'montantdu' => 'Montant dû',
        'montantpaye' => 'Montant payé',
    ];

    /** Colonnes à aligner à droite et à formater avec séparateurs de milliers. */
    private const COLONNES_NUMERIQUES = [
        'poids1', 'poids2', 'poidsnet', 'prixunitaire', 'montantdu', 'montantpaye',
    ];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly Environment $twig
    ) {
    }

    /** @param array<string, mixed> $requete Les paramètres d'API du résultat filtré, construits par le contrôleur */
    public function excel(array $requete, Periode $periode): StreamedResponse
    {
        $lignes = $this->rassembler($requete);
        $colonnes = self::COLONNES + self::COLONNES_MONTANTS;

        $classeur = new Spreadsheet();
        $feuille = $classeur->getActiveSheet();
        $feuille->setTitle('Bilan');

        $feuille->fromArray(array_values($colonnes), null, 'A1');

        $entete = $feuille->getStyle('A1:' . $feuille->getHighestColumn() . '1');
        $entete->getFont()->setBold(true);
        $entete->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');
        $entete->getFont()->getColor()->setRGB('FFFFFF');
        $entete->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $numero = 2;
        foreach ($lignes as $pesee) {
            $feuille->fromArray($this->versLigne($pesee, array_keys($colonnes)), null, 'A' . $numero);
            ++$numero;
        }

        foreach (range('A', $feuille->getHighestColumn()) as $colonne) {
            $feuille->getColumnDimension($colonne)->setAutoSize(true);
        }

        // La ligne d'en-tête reste visible au défilement : un bilan se lit sur des centaines de lignes
        $feuille->freezePane('A2');

        $reponse = new StreamedResponse(static function () use ($classeur): void {
            (new Xlsx($classeur))->save('php://output');
        });

        $reponse->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $reponse->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $this->nomFichier($periode, 'xlsx')
        ));

        return $reponse;
    }

    /** @param array<string, mixed> $requete Les paramètres d'API du résultat filtré, construits par le contrôleur */
    public function pdf(array $requete, Periode $periode, bool $avecMontants): Response
    {
        $lignes = $this->rassembler($requete);
        $colonnes = $avecMontants ? self::COLONNES + self::COLONNES_MONTANTS : self::COLONNES;

        $html = $this->twig->render('bilan/export.html.twig', [
            'colonnes' => $colonnes,
            // Le gabarit ne devine pas ce qui est un nombre : on le lui dit
            'numeriques' => self::COLONNES_NUMERIQUES,
            'lignes' => array_map(
                fn (array $p) => array_combine(array_keys($colonnes), $this->versLigne($p, array_keys($colonnes))),
                $lignes
            ),
            'periode' => $periode,
            'avecMontants' => $avecMontants,
            'totaux' => $this->totaliser($lignes),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        // Paysage : quinze colonnes ne tiennent pas en portrait
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $this->nomFichier($periode, 'pdf')
            ),
        ]);
    }

    /*
        - Le reçu d'un versement, en A5 portrait : c'est un justificatif de main à main, pas un état.
          Le planteur repart avec, et la RÉFÉRENCE est ce qui permet de retrouver l'opération auprès
          de la passerelle en cas de contestation — d'où sa place en évidence.

        - Le reçu se génère à la demande et n'est pas stocké : il ne fait que remettre en forme des
          données qui vivent dans l'API. Le régénérer donne exactement le même document.

        @param array<string, mixed> $paiement Le versement tel que l'API le rend
    */
    public function recu(array $paiement): Response
    {
        $html = $this->twig->render('paiement/recu.html.twig', ['paiement' => $paiement]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                sprintf('recu_%s.pdf', $paiement['reference'] ?? 'versement')
            ),
        ]);
    }

    /**
     * Parcourt toutes les pages du résultat filtré.
     *
     * @return list<array<string, mixed>>
     */
    private function rassembler(array $requete): array
    {
        $requete['itemsPerPage'] = self::PAR_PAGE;

        $lignes = [];
        $page = 1;

        do {
            $requete['page'] = $page;
            $resultat = $this->api->page('/api/operations', $requete);

            $lignes = array_merge($lignes, $resultat['items']);
            ++$page;

            $reste = count($resultat['items']) === self::PAR_PAGE;
        } while ($reste && count($lignes) < self::LIGNES_MAX);

        return array_slice($lignes, 0, self::LIGNES_MAX);
    }

    /**
     * @param array<string, mixed> $pesee
     * @param list<string>         $colonnes
     *
     * @return list<string|int|null>
     */
    private function versLigne(array $pesee, array $colonnes): array
    {
        $valeurs = [];

        foreach ($colonnes as $colonne) {
            $valeurs[] = match ($colonne) {
                'site' => $pesee['site']['libellesite'] ?? null,
                'produit' => $pesee['produit']['libelle'] ?? null,
                'fournisseur' => $pesee['fournisseur']['nomComplet'] ?? null,
                'peseeAt2' => isset($pesee['peseeAt2'])
                    ? (new \DateTimeImmutable($pesee['peseeAt2']))->format('d/m/Y H:i')
                    : null,
                default => $pesee[$colonne] ?? null,
            };
        }

        return $valeurs;
    }

    /**
     * @param list<array<string, mixed>> $lignes
     *
     * @return array{nbPesees: int, poidsnet: int, montantdu: int, montantpaye: int}
     */
    private function totaliser(array $lignes): array
    {
        $totaux = ['nbPesees' => count($lignes), 'poidsnet' => 0, 'montantdu' => 0, 'montantpaye' => 0];

        foreach ($lignes as $pesee) {
            $totaux['poidsnet'] += (int) ($pesee['poidsnet'] ?? 0);
            $totaux['montantdu'] += (int) ($pesee['montantdu'] ?? 0);
            $totaux['montantpaye'] += (int) ($pesee['montantpaye'] ?? 0);
        }

        return $totaux;
    }

    private function nomFichier(Periode $periode, string $extension): string
    {
        return sprintf('bilan_%s_%s.%s', $periode->du, $periode->au, $extension);
    }
}
