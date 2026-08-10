import * as React from 'react'
import { ColumnDef } from '@tanstack/react-table'
import { ServerDataTable } from '../../components/server/server-data-table'
import { entetesDepuis } from '../../components/server/server-data-table-column-header'
import { dateHeure, Etat, MenuActions, montant, texte } from '../../components/cellules'
import { ServerMeta, ServerTableFilter, SortMeta } from '../../hooks/useServerTable'
import { Entreprise } from '../../models/gestion.model'

/*
    - Les clients, vus par le super administrateur. Son périmètre s'arrête au provisionnement : il
      voit combien de postes et de comptes chaque entreprise possède, mais ni les pesées ni les
      mouvements de caisse. Ce n'est pas son argent.
*/

type Ligne = Entreprise & { url: string; urlActivation: string }

interface Props {
    items: Ligne[]
    meta: ServerMeta
    queryParams: Record<string, string>
    sortMeta: SortMeta
    /* Deux droits distincts côté API : modifier l'identité d'un client n'emporte pas le droit de lui couper l'accès */
    modifiable: boolean
    blocable: boolean
    jeton: string
}

const FILTRES: ServerTableFilter[] = [
    { type: 'text', name: 'recherche', label: 'Rechercher', placeholder: 'Nom…' },
    {
        type: 'select',
        name: 'statut',
        label: 'Tous les états',
        options: [
            { label: 'Active', value: 'ACTIVE' },
            { label: 'Désactivée', value: 'DESACTIVEE' },
        ],
    },
]

export default function TableEntreprises({ items, meta, queryParams, sortMeta, modifiable, blocable, jeton }: Props) {
    const entete = entetesDepuis(sortMeta)

    const colonnes: ColumnDef<Ligne, unknown>[] = [
        {
            id: 'codeentreprise',
            header: 'Code',
            // Il identifie l'entreprise partout, y compris dans les codes de ses postes
            cell: ({ row }) => <span className="font-mono text-xs">{row.original.codeentreprise}</span>,
        },
        {
            id: 'nom',
            header: () => entete('nom', 'Entreprise'),
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">{row.original.nom}</div>
                    {row.original.adresse && <div className="text-xs text-muted-foreground">{row.original.adresse}</div>}
                </div>
            ),
        },
        {
            id: 'contact',
            header: 'Contact',
            cell: ({ row }) => (
                <div>
                    <div>{texte(row.original.contact1)}</div>
                    {row.original.email && <div className="text-xs text-muted-foreground">{row.original.email}</div>}
                </div>
            ),
        },
        {
            id: 'sitesCount',
            header: 'Postes',
            meta: { aDroite: true },
            /* Comptés en SQL par l'API, corbeille exclue : la liste n'hydrate jamais les sites. */
            cell: ({ row }) => row.original.sitesCount > 0
                ? row.original.sitesCount
                : <span className="text-muted-foreground">0</span>,
        },
        {
            id: 'comptesCount',
            header: 'Comptes',
            meta: { aDroite: true },
            // Les comptes machine des postes en sont exclus : ils ne se gèrent pas depuis l'écran des utilisateurs
            cell: ({ row }) => row.original.comptesCount > 0
                ? row.original.comptesCount
                : <span className="text-muted-foreground">0</span>,
        },
        {
            id: 'solde',
            header: 'Solde',
            meta: { aDroite: true },
            // C'est lui qui alimente les caisses des postes : à sec, plus aucune dotation n'est possible
            cell: ({ row }) => montant(row.original.solde),
        },
        {
            id: 'statut',
            header: 'État',
            cell: ({ row }) => <Etat statut={row.original.statut} />,
        },
        {
            id: 'createdAt',
            header: () => entete('createdAt', 'Cliente depuis'),
            cell: ({ row }) => <span className="text-xs text-muted-foreground">{dateHeure(row.original.createdAt)}</span>,
        },
        {
            id: 'actions',
            header: '',
            meta: { aDroite: true },
            cell: ({ row }) => {
                const entreprise = row.original
                const desactivee = entreprise.statut === 'DESACTIVEE'

                return (
                    <MenuActions
                        jeton={jeton}
                        liens={modifiable ? [{ libelle: 'Modifier', href: entreprise.url }] : []}
                        actions={blocable ? [{
                            libelle: desactivee ? 'Réactiver' : 'Désactiver',
                            action: entreprise.urlActivation,
                            destructive: !desactivee,
                            confirmer: desactivee ? `Réactiver ${entreprise.nom} ?` : `Désactiver ${entreprise.nom} ?`,
                            detail: desactivee
                                ? 'Tous ses comptes retrouvent l\'accès immédiatement.'
                                : 'Tous ses comptes perdent l\'accès d\'un coup. Rien n\'est supprimé : l\'historique reste et la réactivation rend tout le monde à son travail.',
                            libelleAction: desactivee ? 'Réactiver' : 'Désactiver',
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
            messageVide="Aucune entreprise"
        />
    )
}
