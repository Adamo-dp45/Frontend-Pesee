import * as React from 'react'
import { ColumnDef } from '@tanstack/react-table'
import { ServerDataTable } from '../../components/server/server-data-table'
import { entetesDepuis } from '../../components/server/server-data-table-column-header'
import { dateHeure, Etat, MenuActions, montant, texte } from '../../components/cellules'
import { ServerMeta, ServerTableFilter, SortMeta } from '../../hooks/useServerTable'
import { Fournisseur } from '../../models/gestion.model'

/*
    - Les planteurs. Le poste ne connaît que leur nom : c'est ici qu'on renseigne le numéro mobile
      money et son réseau. Sans eux la pesée s'enregistre, mais le versement est refusé — d'où la
      colonne « Paiement », qui dit tout de suite qui ne pourra pas être payé.
*/

type Ligne = Fournisseur & { url: string; urlEdit: string; urlDelete: string }

interface Props {
    items: Ligne[]
    meta: ServerMeta
    queryParams: Record<string, string>
    sortMeta: SortMeta
    /* Faux pour un agent, qui supervise sans tarifer */
    modifiable: boolean
    supprimable: boolean
    /* La map 'id => libellé' que rend 'GestionController::options()' */
    sites: Record<string, string>
    jeton: string
}

export default function TableFournisseurs({ items, meta, queryParams, sortMeta, modifiable, supprimable, sites, jeton }: Props) {
    const entete = entetesDepuis(sortMeta)

    const colonnes: ColumnDef<Ligne, unknown>[] = [
        {
            id: 'codefournisseur',
            header: () => entete('codefournisseur', 'Code'),
            // Le code du poste : c'est par lui que le référentiel du web et celui du poste se rapprochent
            cell: ({ row }) => <span className="font-mono text-xs">{texte(row.original.codefournisseur)}</span>,
        },
        {
            id: 'nom',
            header: () => entete('nom', 'Planteur'),
            cell: ({ row }) => <span className="font-medium">{row.original.nomComplet}</span>,
        },
        {
            id: 'site',
            header: 'Pont bascule',
            cell: ({ row }) => (
                <div>
                    <div>{texte(row.original.site?.libellesite)}</div>
                    {row.original.site?.code && <div className="font-mono text-xs text-muted-foreground">{row.original.site.code}</div>}
                </div>
            ),
        },
        {
            id: 'paiement',
            header: 'Paiement',
            /* 'motifNonPayable' est calculé par l'API : les deux écrans ne peuvent pas diverger sur qui est payable. */
            cell: ({ row }) => row.original.motifNonPayable
                ? <span className="text-xs text-destructive">{row.original.motifNonPayable}</span>
                : (
                    <div>
                        <div>{texte(row.original.contact1)} {row.original.reseau && <span className="text-xs text-muted-foreground">· {row.original.reseau}</span>}</div>
                        {/* Le second numéro ne sert pas au versement, mais c'est par lui qu'on joint le planteur quand le premier ne répond plus */}
                        {row.original.contact2 && <div className="text-xs text-muted-foreground">Autre : {row.original.contact2}</div>}
                    </div>
                ),
        },
        {
            id: 'prixspeciale',
            header: () => entete('prixspeciale', 'Prix spécial', true),
            meta: { aDroite: true },
            // Il prime sur le prix du produit : l'afficher évite de chercher pourquoi une pesée est valorisée autrement
            cell: ({ row }) => row.original.prixspeciale
                ? montant(row.original.prixspeciale)
                : <span className="text-muted-foreground">—</span>,
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
            /* Compté en SQL par l'API : la liste n'hydrate jamais les pesées. */
            cell: ({ row }) => row.original.peseesCount > 0
                ? row.original.peseesCount.toLocaleString('fr-FR')
                : <span className="text-muted-foreground">0</span>,
        },
        {
            id: 'createdAt',
            header: () => entete('createdAt', 'Créé le'),
            // Un planteur apparu récemment vient du poste : c'est le signe qu'il attend son numéro mobile money
            cell: ({ row }) => <span className="text-xs text-muted-foreground">{dateHeure(row.original.createdAt)}</span>,
        },
        {
            id: 'actions',
            header: '',
            meta: { aDroite: true },
            cell: ({ row }) => (
                <MenuActions
                    jeton={jeton}
                    liens={[
                        { libelle: 'Voir', href: row.original.url },
                        ...(modifiable ? [{ libelle: 'Modifier', href: row.original.urlEdit }] : []),
                    ]}
                    actions={supprimable ? [{
                        libelle: 'Supprimer',
                        action: row.original.urlDelete,
                        destructive: true,
                        confirmer: `Supprimer « ${row.original.nomComplet} » ?`,
                        detail: 'Les pesées déjà enregistrées le conservent. Le poste pourra le recréer à la prochaine synchronisation, mais sans son numéro mobile money.',
                        libelleAction: 'Supprimer',
                    }] : []}
                />
            ),
        },
    ]

    const filtres: ServerTableFilter[] = [
        { type: 'text', name: 'recherche', label: 'Rechercher', placeholder: 'Nom ou code…' },
        { type: 'select', name: 'site', label: 'Tous les ponts bascules', options: Object.entries(sites).map(([valeur, libelle]) => ({ value: valeur, label: libelle })) },
        {
            type: 'select',
            name: 'statut',
            label: 'Tous les états',
            options: [
                { label: 'Actif', value: 'ACTIF' },
                { label: 'Suspendu', value: 'SUSPENDU' },
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
            messageVide="Aucun planteur. Ils se créent depuis le poste de pesée, et remontent à la synchronisation."
        />
    )
}
