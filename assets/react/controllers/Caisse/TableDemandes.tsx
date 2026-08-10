import * as React from 'react'
import { ColumnDef } from '@tanstack/react-table'
import { ServerDataTable } from '../../components/server/server-data-table'
import { entetesDepuis } from '../../components/server/server-data-table-column-header'
import { dateHeure, Etat, MenuActions, montant, texte } from '../../components/cellules'
import { ServerMeta, ServerTableFilter, SortMeta } from '../../hooks/useServerTable'
import { DemandeSolde } from '../../models/gestion.model'

/*
    - Les demandes de réapprovisionnement. Le montant accordé peut être inférieur au montant
      demandé : on affiche les deux, sinon un opérateur croit avoir obtenu ce qu'il réclamait.

    - Ce qu'on a le droit de faire sur chaque ligne est décidé par le contrôleur : une demande
      traitée n'offre plus rien, elle a donné lieu à un mouvement de caisse.
*/

type Ligne = DemandeSolde & {
    urlTraitement: string
    urlEdit: string
    urlAnnulation: string
    traitable: boolean
    corrigeable: boolean
}

interface Props {
    items: Ligne[]
    meta: ServerMeta
    queryParams: Record<string, string>
    sortMeta: SortMeta
    /* La map 'id => libellé' que rend 'GestionController::options()' */
    sites: Record<string, string>
    jeton: string
}

export default function TableDemandes({ items, meta, queryParams, sortMeta, sites, jeton }: Props) {
    const entete = entetesDepuis(sortMeta)

    const colonnes: ColumnDef<Ligne, unknown>[] = [
        {
            id: 'createdAt',
            header: () => entete('createdAt', 'Demandée le'),
            cell: ({ row }) => <span className="text-xs">{dateHeure(row.original.createdAt)}</span>,
        },
        {
            id: 'site',
            header: 'Pont bascule',
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">{texte(row.original.site?.libellesite)}</div>
                    {row.original.site?.code && <div className="font-mono text-xs text-muted-foreground">{row.original.site.code}</div>}
                </div>
            ),
        },
        {
            id: 'demandePar',
            header: 'Demandée par',
            cell: ({ row }) => texte(row.original.demandePar?.nomComplet),
        },
        {
            id: 'motif',
            header: 'Motif',
            // C'est sur lui que l'administrateur décide : le cacher oblige à ouvrir chaque demande
            cell: ({ row }) => row.original.motif
                ? <span className="text-xs">{row.original.motif}</span>
                : <span className="text-muted-foreground">—</span>,
        },
        {
            id: 'montantDemande',
            header: () => entete('montantDemande', 'Demandé', true),
            meta: { aDroite: true },
            cell: ({ row }) => montant(row.original.montantDemande),
        },
        {
            id: 'montantAccorde',
            header: 'Accordé',
            meta: { aDroite: true },
            // Un accordé inférieur au demandé n'est pas une erreur : il faut qu'il se voie
            cell: ({ row }) => row.original.montantAccorde === null
                ? <span className="text-muted-foreground">—</span>
                : (
                    <span className={row.original.montantAccorde < row.original.montantDemande ? 'text-amber-600 dark:text-amber-500' : undefined}>
                        {montant(row.original.montantAccorde)}
                    </span>
                ),
        },
        {
            id: 'statut',
            header: 'État',
            cell: ({ row }) => (
                <div>
                    <Etat statut={row.original.statut} />
                    {/* Le motif du rejet est ce que l'opérateur lit avant de redemander : il vit avec l'état, pas ailleurs */}
                    {row.original.motifRejet && <div className="mt-1 text-xs text-muted-foreground">{row.original.motifRejet}</div>}
                </div>
            ),
        },
        {
            id: 'traitePar',
            header: 'Traitée par',
            cell: ({ row }) => row.original.traitePar
                ? (
                    <div>
                        <div className="text-xs">{row.original.traitePar.nomComplet}</div>
                        <div className="text-xs text-muted-foreground">{dateHeure(row.original.traiteAt)}</div>
                    </div>
                )
                : <span className="text-muted-foreground">—</span>,
        },
        {
            id: 'actions',
            header: '',
            meta: { aDroite: true },
            cell: ({ row }) => {
                const demande = row.original

                return (
                    <MenuActions
                        jeton={jeton}
                        liens={[
                            ...(demande.traitable ? [{ libelle: 'Traiter', href: demande.urlTraitement }] : []),
                            ...(demande.corrigeable ? [{ libelle: 'Corriger', href: demande.urlEdit }] : []),
                        ]}
                        actions={demande.corrigeable ? [{
                            libelle: 'Annuler la demande',
                            action: demande.urlAnnulation,
                            destructive: true,
                            confirmer: 'Annuler cette demande ?',
                            detail: 'Elle sort de l\'attente, et le pont bascule pourra en déposer une nouvelle.',
                            libelleAction: 'Annuler la demande',
                        }] : []}
                    />
                )
            },
        },
    ]

    const filtres: ServerTableFilter[] = [
        { type: 'select', name: 'site', label: 'Tous les ponts bascules', options: Object.entries(sites).map(([valeur, libelle]) => ({ value: valeur, label: libelle })) },
        {
            type: 'select',
            name: 'statut',
            label: 'Tous les états',
            options: [
                { label: 'En attente', value: 'EN_ATTENTE' },
                { label: 'Approuvée', value: 'APPROUVEE' },
                { label: 'Rejetée', value: 'REJETEE' },
                { label: 'Annulée', value: 'ANNULEE' },
            ],
        },
    ]

    return (
        <ServerDataTable
            columns={colonnes}
            data={items}
            meta={meta}
            queryParams={queryParams}
            filters={filtres}
            messageVide="Aucune demande de réapprovisionnement"
        />
    )
}
