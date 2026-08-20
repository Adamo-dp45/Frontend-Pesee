import * as React from 'react'
import { ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/assets/components/ui/badge'
import { buttonVariants } from '@/assets/components/ui/button'
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
            cell: ({ row }) => {
                const du = row.original.montantdu ?? 0
                const paye = row.original.montantpaye ?? 0

                return (
                    <div>
                        <div className="font-semibold">{francs(Math.max(0, du - paye))}</div>
                        {/* Un versement partiel déjà fait doit se voir : sinon on repaie la totalité */}
                        {paye > 0 && (
                            <div className="text-xs text-muted-foreground">{francs(paye)} déjà versés sur {francs(du)}</div>
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
            cell: ({ row }) => (
                <a className={buttonVariants({ size: 'sm' })} href={row.original.urlPaiement}>Verser</a>
            ),
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
