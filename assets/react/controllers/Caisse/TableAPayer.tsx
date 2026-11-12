import * as React from 'react'
import { ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/assets/components/ui/badge'
import { buttonVariants } from '@/assets/components/ui/button'
import { cn } from '@/assets/lib/utils'
import { ServerDataTable } from '../../components/server/server-data-table'
import { entetesDepuis } from '../../components/server/server-data-table-column-header'
import { dateSeule, numero, texte } from '../../components/cellules'
import { ServerMeta, ServerTableFilter, SortMeta } from '../../hooks/useServerTable'
import { Pesee } from '../../models/pesee.model'

/*
    - La file d'attente du guichet : ce qu'il reste à payer, et rien d'autre. L'opérateur a le
      planteur devant lui — il ne va pas dérouler les dix filtres du bilan, qui est un écran
      d'analyse.

    - Moins de colonnes qu'au bilan, volontairement : le ticket, à qui, combien il reste, et le
      bouton. Tout le reste se lit sur la fiche de la pesée.

    - Le bouton est en clair sur la ligne, pas dans un menu : c'est LE geste de l'écran, et un menu
      ajouterait un clic à chaque planteur de la file.
*/

type Ligne = Pesee & { url: string; urlPaiement: string }

interface Props {
    items: Ligne[]
    meta: ServerMeta
    queryParams: Record<string, string>
    sortMeta: SortMeta
    /* La map 'id => libellé' que rend 'GestionController::options()' */
    sites: Record<string, string>
    /* Vrai pour l'administrateur, qui voit plusieurs postes : l'opérateur n'en tient qu'un */
    multiSite: boolean
}

const francs = (valeur: number) => `${valeur.toLocaleString('fr-FR')} F`

export default function TableAPayer({ items, meta, queryParams, sortMeta, sites, multiSite }: Props) {
    const entete = entetesDepuis(sortMeta)

    const colonnes: ColumnDef<Ligne, unknown>[] = [
        {
            id: 'numticket',
            header: () => entete('numticket', 'Ticket'),
            cell: ({ row }) => (
                <div>
                    <a href={row.original.url} className="font-mono text-xs font-medium underline-offset-4 hover:underline">
                        {row.original.numticket ?? row.original.codepesee ?? '—'}
                    </a>
                    <div className="text-xs text-muted-foreground">
                        {dateSeule(row.original.peseeAt2)}
                    </div>
                </div>
            ),
        },
        {
            id: 'fournisseur',
            header: 'Planteur',
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">{texte(row.original.fournisseur?.nomComplet)}</div>
                    {/* Le motif de non-payabilité vient de l'API : mieux vaut le lire ici qu'après avoir cliqué */}
                    {row.original.fournisseur?.motifNonPayable
                        ? <div className="text-xs text-destructive">{row.original.fournisseur.motifNonPayable}</div>
                        : <div className="text-xs text-muted-foreground">{numero(row.original.fournisseur?.contact1)}</div>}
                </div>
            ),
        },
        ...(multiSite ? [{
            id: 'site',
            header: 'Pont bascule',
            cell: ({ row }) => texte(row.original.site?.libellesite),
        } as ColumnDef<Ligne, unknown>] : []),
        {
            id: 'produit',
            header: 'Produit',
            cell: ({ row }) => (
                <div>
                    <div>{texte(row.original.produit?.libelle)}</div>
                    <div className="text-xs text-muted-foreground">
                        {row.original.poidsnet ? `${row.original.poidsnet.toLocaleString('fr-FR')} kg` : '—'}
                    </div>
                </div>
            ),
        },
        {
            id: 'montantdu',
            header: () => entete('montantdu', 'Reste à payer', true),
            meta: { aDroite: true },
            /*
                - Le chiffre en gros est ce que l'API acceptera ENCORE, pas la créance du planteur.
                  Il comptait auparavant le seul montant confirmé : un versement tout juste engagé
                  n'y changeait donc rien, la ligne affichait le même reste qu'avant et l'opérateur,
                  croyant son geste perdu, recommençait.

                - Les deux lignes en dessous disent d'où vient l'écart. Celle des versements en vol
                  est la plus importante des deux : c'est elle qui explique un reste qui a baissé
                  sans qu'un franc soit encore confirmé.
            */
            cell: ({ row }) => {
                const du = row.original.montantdu ?? 0
                const retenue = row.original.retenue ?? 0
                const transport = row.original.montanttransport ?? 0
                /* Ce qu'on peut REMETTRE au planteur, et non ce que vaut sa récolte : son transport s'y ajoute, et la part mise sur son épargne ne passera pas par le guichet. */
                const payable = row.original.montantpayable ?? Math.max(0, du + transport - retenue)
                const paye = row.original.montantpaye ?? 0
                const enVol = row.original.montantenvol ?? 0
                const aEngager = row.original.resteaengager ?? Math.max(0, payable - paye - enVol)

                return (
                    <div>
                        <div className="font-semibold">{francs(aEngager)}</div>
                        {/* Un versement partiel déjà fait doit se voir : sinon on repaie la totalité */}
                        {paye > 0 && (
                            <div className="text-xs text-muted-foreground">{francs(paye)} déjà versés sur {francs(payable)}</div>
                        )}
                        {/* Les deux écarts avec le ticket que le planteur a en main, où il lit la récolte SEULE */}
                        {transport > 0 && (
                            <div className="text-xs text-muted-foreground">dont {francs(transport)} de transport</div>
                        )}
                        {retenue > 0 && (
                            <div className="text-xs text-muted-foreground">{francs(retenue)} mis en compte sur {francs(du)}</div>
                        )}
                        {enVol > 0 && (
                            <div className="text-xs font-medium text-muted-foreground">{francs(enVol)} en cours de confirmation</div>
                        )}
                    </div>
                )
            },
        },
        {
            id: 'statutReglement',
            header: 'Règlement',
            cell: ({ row }) => row.original.statutReglement === 'PARTIEL'
                ? <Badge variant="secondary">Partielle</Badge>
                : <Badge variant="outline">Non payée</Badge>,
        },
        {
            id: 'actions',
            header: '',
            meta: { aDroite: true },
            /*
                - Le bouton ne mène nulle part quand la pesée n'est pas payable : l'API refuse, et
                  l'opérateur ne l'apprenait qu'après le clic, sur l'écran de versement, par un
                  message qui ne disait pas quoi corriger. Le motif accompagne déjà chaque ligne —
                  autant le rendre lisible là où le geste se décide.

                - Un 'span' et non un bouton désactivé : la cible est un lien, et un lien ne se
                  désactive pas. 'pointer-events-none' coupe le clic, 'aria-disabled' le dit aux
                  lecteurs d'écran.
            */
            cell: ({ row }) => {
                const motif = row.original.motifNonPayable

                if (!motif) {
                    return <a className={buttonVariants({ size: 'sm' })} href={row.original.urlPaiement}>Verser</a>
                }

                /* La colonne « Planteur » porte déjà le sien : le répéter ici ferait lire deux fois la même phrase */
                const dejaDit = motif === row.original.fournisseur?.motifNonPayable

                return (
                    <div className="inline-flex flex-col items-end gap-1">
                        <span
                            className={cn(buttonVariants({ size: 'sm', variant: 'outline' }), 'pointer-events-none opacity-60')}
                            title={motif}
                            aria-disabled="true"
                        >
                            Verser
                        </span>
                        {!dejaDit && <span className="max-w-56 text-right text-xs text-destructive">{motif}</span>}
                    </div>
                )
            },
        },
    ]

    const filtres: ServerTableFilter[] = [
        { type: 'text', name: 'recherche', label: 'Rechercher', placeholder: 'Numéro de ticket…' },
        ...(multiSite
            ? [{ type: 'select', name: 'site', label: 'Tous les ponts bascules', options: Object.entries(sites).map(([valeur, libelle]) => ({ value: valeur, label: libelle })) } as ServerTableFilter]
            : []),
    ]

    return (
        <ServerDataTable
            columns={colonnes}
            data={items}
            meta={meta}
            queryParams={queryParams}
            filters={filtres}
            messageVide="Rien à payer : toutes les pesées terminées sont soldées."
        />
    )
}
