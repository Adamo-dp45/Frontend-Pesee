import * as React from 'react'
import { ColumnDef } from '@tanstack/react-table'
import { ServerDataTable } from '../../components/server/server-data-table'
import { entetesDepuis } from '../../components/server/server-data-table-column-header'
import { dateHeure, Etat, MenuActions, montant, texte } from '../../components/cellules'
import { ServerMeta, ServerTableFilter, SortMeta } from '../../hooks/useServerTable'
import { Site } from '../../models/gestion.model'

/*
    - Les ponts bascules. La colonne « Caisse » est celle qu'on regarde en premier : c'est elle qui
      dit si l'opérateur du poste pourra payer les planteurs aujourd'hui.
*/

interface Props {
    items: (Site & { url: string; urlBlocage: string })[]
    meta: ServerMeta
    queryParams: Record<string, string>
    sortMeta: SortMeta
    /* Deux droits distincts côté API, et ils ne se recouvrent pas : le super administrateur modifie un poste sans pouvoir bloquer une caisse qui n'est pas la sienne */
    modifiable: boolean
    blocable: boolean
    /* Vrai pour le super administrateur seul : lui seul voit les postes de plusieurs entreprises */
    multiEntreprise: boolean
    jeton: string
}

const FILTRES: ServerTableFilter[] = [
    { type: 'text', name: 'recherche', label: 'Rechercher', placeholder: 'Code ou libellé…' },
    {
        type: 'select',
        name: 'statut',
        label: 'Tous les états',
        options: [
            { label: 'Actif', value: 'ACTIF' },
            { label: 'Bloqué', value: 'BLOQUE' },
        ],
    },
]

export default function TableSites({ items, meta, queryParams, sortMeta, modifiable, blocable, multiEntreprise, jeton }: Props) {
    const entete = entetesDepuis(sortMeta)

    const colonnes: ColumnDef<Site & { url: string; urlBlocage: string }, unknown>[] = [
        {
            id: 'code',
            header: () => entete('code', 'Code'),
            // Le code que le poste envoie à chaque synchronisation : c'est par lui qu'on l'identifie dans les journaux
            cell: ({ row }) => <span className="font-mono text-xs">{row.original.code}</span>,
        },
        {
            id: 'libellesite',
            header: () => entete('libellesite', 'Pont bascule'),
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">{row.original.libellesite}</div>
                    {row.original.localite && <div className="text-xs text-muted-foreground">{row.original.localite}</div>}
                </div>
            ),
        },
        // Sans elle, le super administrateur lit quatre postes de deux clients sans pouvoir les distinguer
        ...(multiEntreprise ? [{
            id: 'entreprise',
            header: 'Entreprise',
            cell: ({ row }) => texte(row.original.entreprise?.nom),
        } as ColumnDef<Site & { url: string; urlBlocage: string }, unknown>] : []),
        {
            id: 'operateur',
            header: 'Opérateur',
            // Un site sans opérateur reste consultable, mais plus personne n'y encaisse : il faut que ça se voie
            cell: ({ row }) => row.original.operateur
                ? (
                    <div>
                        <div>{texte(row.original.operateur.nomComplet)}</div>
                        {row.original.operateur.email && <div className="text-xs text-muted-foreground">{row.original.operateur.email}</div>}
                    </div>
                )
                : <span className="text-xs text-muted-foreground">Aucun</span>,
        },
        {
            id: 'solde',
            header: () => entete('solde', 'Caisse', true),
            meta: { aDroite: true },
            cell: ({ row }) => montant(row.original.solde),
        },
        {
            id: 'statut',
            header: 'État',
            cell: ({ row }) => <Etat statut={row.original.statut} />,
        },
        {
            id: 'peseesCount',
            header: 'Pesées',
            meta: { aDroite: true },
            /* Le signe de vie du poste : un pont bascule à zéro pesée n'a jamais synchronisé, et le solde de sa caisse ne dit rien de son activité. */
            cell: ({ row }) => row.original.peseesCount > 0
                ? row.original.peseesCount.toLocaleString('fr-FR')
                : <span className="text-muted-foreground">0</span>,
        },
        {
            id: 'createdAt',
            header: () => entete('createdAt', 'En service depuis'),
            cell: ({ row }) => <span className="text-xs text-muted-foreground">{dateHeure(row.original.createdAt)}</span>,
        },
        {
            id: 'actions',
            header: '',
            meta: { aDroite: true },
            cell: ({ row }) => {
                const site = row.original
                const bloque = site.statut === 'BLOQUE'

                return (
                    <MenuActions
                        jeton={jeton}
                        liens={[{ libelle: modifiable ? 'Gérer' : 'Voir', href: site.url }]}
                        actions={blocable ? [{
                            libelle: bloque ? 'Débloquer' : 'Bloquer',
                            action: site.urlBlocage,
                            destructive: !bloque,
                            confirmer: bloque ? 'Débloquer ce pont bascule ?' : 'Bloquer ce pont bascule ?',
                            detail: bloque
                                ? 'La synchronisation reprendra et les paiements redeviendront possibles.'
                                : 'Ses pesées seront refusées et aucun paiement ne pourra plus partir de sa caisse.',
                            libelleAction: bloque ? 'Débloquer' : 'Bloquer',
                        }] : []}
                    />
                )
            },
        },
    ]

    return (
        <ServerDataTable
            columns={colonnes}
            data={items}
            meta={meta}
            queryParams={queryParams}
            filters={FILTRES}
            messageVide="Aucun pont bascule dans votre périmètre"
        />
    )
}
