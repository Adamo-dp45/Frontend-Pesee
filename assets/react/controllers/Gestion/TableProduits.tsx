import * as React from 'react'
import { ColumnDef } from '@tanstack/react-table'
import { ServerDataTable } from '../../components/server/server-data-table'
import { entetesDepuis } from '../../components/server/server-data-table-column-header'
import { dateHeure, Etat, MenuActions, montant, texte } from '../../components/cellules'
import { ServerMeta, ServerTableFilter, SortMeta } from '../../hooks/useServerTable'
import { Produit } from '../../models/gestion.model'

/*
    - Le prix affiché ici est celui qui sert à valoriser les pesées. Il est bidirectionnel : le
      poste l'envoie à chaque synchronisation et le web peut le corriger, le dernier qui modifie
      l'emporte.
*/

type Ligne = Produit & { url: string; urlEdit: string; urlDelete: string }

interface Props {
    items: Ligne[]
    meta: ServerMeta
    queryParams: Record<string, string>
    sortMeta: SortMeta
    /* Faux pour un agent, qui supervise sans tarifer */
    modifiable: boolean
    /* Vrai pour un administrateur seul : la corbeille n'est pas une correction de prix */
    supprimable: boolean
    /* La map 'id => libellé' que rend 'GestionController::options()' */
    sites: Record<string, string>
    jeton: string
}

export default function TableProduits({ items, meta, queryParams, sortMeta, modifiable, supprimable, sites, jeton }: Props) {
    const entete = entetesDepuis(sortMeta)

    const colonnes: ColumnDef<Ligne, unknown>[] = [
        {
            id: 'codeproduit',
            header: () => entete('codeproduit', 'Code'),
            // Le code du poste : c'est par lui que le référentiel du web et celui du poste se rapprochent
            cell: ({ row }) => <span className="font-mono text-xs">{texte(row.original.codeproduit)}</span>,
        },
        {
            id: 'libelle',
            header: () => entete('libelle', 'Produit'),
            cell: ({ row }) => <span className="font-medium">{row.original.libelle}</span>,
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
            id: 'prix',
            header: () => entete('prix', 'Prix au kg', true),
            meta: { aDroite: true },
            // Un produit à zéro n'est pas payable : la pesée s'enregistre mais le versement est refusé
            cell: ({ row }) => row.original.prix > 0
                ? montant(row.original.prix)
                : <span className="text-destructive">Aucun prix</span>,
        },
        {
            id: 'actif',
            header: 'État',
            cell: ({ row }) => <Etat statut={row.original.actif ? 'ACTIF' : 'SUSPENDU'} />,
        },
        {
            id: 'peseesCount',
            header: 'Pesées',
            meta: { aDroite: true },
            /* Compté en SQL par l'API : la liste n'hydrate jamais les pesées. Un produit à zéro se supprime sans conséquence, un produit très utilisé porte tout un historique de montants. */
            cell: ({ row }) => row.original.peseesCount > 0
                ? row.original.peseesCount.toLocaleString('fr-FR')
                : <span className="text-muted-foreground">0</span>,
        },
        {
            id: 'createdAt',
            header: () => entete('createdAt', 'Créé le'),
            // Un produit apparu récemment vient probablement du poste : c'est le signe qu'il attend son prix
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
                        confirmer: `Supprimer « ${row.original.libelle} » ?`,
                        detail: 'Les pesées déjà enregistrées le conservent et gardent leur montant. Le poste pourra le recréer à la prochaine synchronisation.',
                        libelleAction: 'Supprimer',
                    }] : []}
                />
            ),
        },
    ]

    const filtres: ServerTableFilter[] = [
        { type: 'text', name: 'recherche', label: 'Rechercher', placeholder: 'Libellé ou code…' },
        { type: 'select', name: 'site', label: 'Tous les ponts bascules', options: Object.entries(sites).map(([valeur, libelle]) => ({ value: valeur, label: libelle })) },
        {
            type: 'select',
            name: 'actif',
            label: 'Tous les états',
            options: [
                { label: 'Actif', value: 'true' },
                { label: 'Désactivé', value: 'false' },
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
            messageVide="Aucun produit. Ils se créent depuis le poste de pesée, et remontent à la synchronisation."
        />
    )
}
