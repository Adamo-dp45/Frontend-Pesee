import * as React from 'react'
import { ColumnDef } from '@tanstack/react-table'
import { ArrowDownLeft, ArrowUpRight } from 'lucide-react'
import { ServerDataTable } from '../../components/server/server-data-table'
import { entetesDepuis } from '../../components/server/server-data-table-column-header'
import { dateHeure, montant, texte } from '../../components/cellules'
import { ServerMeta, ServerTableFilter, SortMeta } from '../../hooks/useServerTable'
import { MouvementCaisse } from '../../models/gestion.model'

/*
    - Le grand livre. Chaque ligne porte le solde AVANT et APRÈS : deux écritures qui se suivent sur
      un même compte doivent se chaîner, le « après » de l'une étant le « avant » de la suivante.
      C'est ce qui rend une anomalie visible à l'œil nu.

    - Une dotation apparaît deux fois : un débit chez l'entreprise, un crédit chez le site. L'argent
      se déplace, il ne se crée pas.

    - Aucune action de ligne : une écriture ne se modifie ni ne s'annule. On la corrige par une
      écriture inverse, jamais en réécrivant l'histoire.
*/

const LIBELLE_TYPE: Record<string, string> = {
    RECHARGE: 'Recharge',
    ATTRIBUTION: 'Attribution à un site',
    DOTATION: 'Dotation reçue',
    PAIEMENT: 'Versement à un planteur',
    REMBOURSEMENT: 'Remboursement',
}

interface Props {
    items: MouvementCaisse[]
    meta: ServerMeta
    queryParams: Record<string, string>
    sortMeta: SortMeta
    /* La map 'id => libellé' que rend 'GestionController::options()' */
    sites: Record<string, string>
}

export default function TableMouvements({ items, meta, queryParams, sortMeta, sites }: Props) {
    const entete = entetesDepuis(sortMeta)

    const colonnes: ColumnDef<MouvementCaisse, unknown>[] = [
        {
            id: 'createdAt',
            header: () => entete('createdAt', 'Date'),
            cell: ({ row }) => <span className="text-xs">{dateHeure(row.original.createdAt)}</span>,
        },
        {
            id: 'compte',
            header: 'Compte',
            // Le compte de l'entreprise n'a pas de site : c'est ce qui distingue les deux niveaux de caisse
            cell: ({ row }) => row.original.compte === 'ENTREPRISE'
                ? <span className="font-medium">Entreprise</span>
                : (
                    <div>
                        <div>{texte(row.original.site?.libellesite)}</div>
                        {row.original.site?.code && <div className="font-mono text-xs text-muted-foreground">{row.original.site.code}</div>}
                    </div>
                ),
        },
        {
            id: 'type',
            header: 'Nature',
            cell: ({ row }) => (
                <div className="flex items-center gap-1.5">
                    {row.original.sens === 'CREDIT'
                        ? <ArrowDownLeft className="size-3.5 text-emerald-600 dark:text-emerald-500" />
                        : <ArrowUpRight className="size-3.5 text-destructive" />}
                    <span>{LIBELLE_TYPE[row.original.type] ?? row.original.type}</span>
                </div>
            ),
        },
        {
            id: 'motif',
            header: 'Motif',
            cell: ({ row }) => row.original.motif
                ? <span className="text-xs">{row.original.motif}</span>
                : <span className="text-muted-foreground">—</span>,
        },
        {
            id: 'montant',
            header: () => entete('montant', 'Montant', true),
            meta: { aDroite: true },
            cell: ({ row }) => (
                <span className={row.original.sens === 'CREDIT' ? 'text-emerald-600 dark:text-emerald-500' : 'text-destructive'}>
                    {row.original.sens === 'CREDIT' ? '+' : '−'} {montant(row.original.montant)}
                </span>
            ),
        },
        {
            id: 'chainage',
            header: 'Avant → après',
            meta: { aDroite: true },
            /* Les deux soldes côte à côte : c'est la lecture qui rend une rupture de chaînage visible sans requête. */
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground">
                    {montant(row.original.soldeAvant)} → <span className="text-foreground">{montant(row.original.soldeApres)}</span>
                </span>
            ),
        },
        {
            id: 'effectuePar',
            header: 'Par',
            // Une écriture sans auteur vient d'un automatisme : la synchronisation ou la passerelle
            cell: ({ row }) => row.original.effectuePar
                ? <span className="text-xs">{row.original.effectuePar.nomComplet}</span>
                : <span className="text-xs text-muted-foreground">Automatique</span>,
        },
    ]

    const filtres: ServerTableFilter[] = [
        {
            type: 'select',
            name: 'compte',
            label: 'Tous les comptes',
            options: [
                { label: 'Entreprise', value: 'ENTREPRISE' },
                { label: 'Ponts bascules', value: 'SITE' },
            ],
        },
        {
            /* 'mouvement' et non 'sens' : ce dernier porte déjà le sens du TRI, et les confondre vidait la liste. */
            type: 'select',
            name: 'mouvement',
            label: 'Entrées et sorties',
            options: [
                { label: 'Entrées', value: 'CREDIT' },
                { label: 'Sorties', value: 'DEBIT' },
            ],
        },
        { type: 'select', name: 'site', label: 'Tous les ponts bascules', options: Object.entries(sites).map(([valeur, libelle]) => ({ value: valeur, label: libelle })) },
    ]

    return (
        <ServerDataTable
            columns={colonnes}
            data={items}
            meta={meta}
            queryParams={queryParams}
            filters={filtres}
            messageVide="Aucune écriture au grand livre"
        />
    )
}
