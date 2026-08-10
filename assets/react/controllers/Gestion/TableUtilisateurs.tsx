import * as React from 'react'
import { ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/assets/components/ui/badge'
import { ServerDataTable } from '../../components/server/server-data-table'
import { entetesDepuis } from '../../components/server/server-data-table-column-header'
import { dateHeure, Etat, MenuActions, texte } from '../../components/cellules'
import { ServerMeta, ServerTableFilter, SortMeta } from '../../hooks/useServerTable'
import { Utilisateur } from '../../models/gestion.model'

/*
    - Les comptes de l'entreprise. Les comptes machine des postes n'y figurent pas : l'API les écarte,
      ils se gèrent depuis la fiche du pont bascule.

    - Ce qu'on a le droit de faire sur chaque ligne est décidé par le contrôleur, qui connaît le
      compte connecté : on ne se modifie ni ne se suspend soi-même, un agent ne gère que des
      opérateurs, et un fondateur n'est suspendu que par le super administrateur.

    - « Modifier » suit donc 'modifiable' et non le seul droit sur la ressource : il s'affichait
      partout, y compris sur sa propre ligne, et la fiche s'ouvrait pour être refusée à
      l'enregistrement.
*/

type Ligne = Utilisateur & {
    url: string
    urlEdit: string
    urlActivation: string
    modifiable: boolean
    suspendable: boolean
    moiMeme: boolean
}

interface Props {
    items: Ligne[]
    meta: ServerMeta
    queryParams: Record<string, string>
    sortMeta: SortMeta
    /* Vrai pour le super administrateur seul : lui seul voit les comptes de plusieurs entreprises */
    multiEntreprise: boolean
    jeton: string
}

const FILTRES: ServerTableFilter[] = [
    { type: 'text', name: 'recherche', label: 'Rechercher', placeholder: 'Nom…' },
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

export default function TableUtilisateurs({ items, meta, queryParams, sortMeta, multiEntreprise, jeton }: Props) {
    const entete = entetesDepuis(sortMeta)

    const colonnes: ColumnDef<Ligne, unknown>[] = [
        {
            id: 'nom',
            header: () => entete('nom', 'Utilisateur'),
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span className="font-medium">{row.original.nomComplet}</span>
                    {/* Le compte qui a inscrit l'entreprise : ni suspendable ni supprimable par un pair */}
                    {row.original.fondateur && <Badge variant="outline">fondateur</Badge>}
                    {row.original.moiMeme && <Badge variant="secondary">vous</Badge>}
                </div>
            ),
        },
        {
            id: 'email',
            header: () => entete('email', 'Email'),
            cell: ({ row }) => <span className="text-xs">{row.original.email}</span>,
        },
        {
            id: 'role',
            header: 'Rôle',
            // 'roleLibelle' vient de l'API : le dictionnaire des rôles n'existe qu'à un seul endroit
            cell: ({ row }) => texte(row.original.roleLibelle),
        },
        ...(multiEntreprise ? [{
            id: 'entreprise',
            header: 'Entreprise',
            cell: ({ row }) => texte(row.original.entreprise?.nom),
        } as ColumnDef<Ligne, unknown>] : []),
        {
            id: 'telephone',
            header: 'Téléphone',
            cell: ({ row }) => texte(row.original.telephone),
        },
        {
            id: 'sitesGeres',
            header: 'Ponts bascules',
            // Un opérateur sans pont bascule ne peut encaisser nulle part : ça se voit d'ici
            cell: ({ row }) => row.original.sitesGeres.length > 0
                ? row.original.sitesGeres.join(', ')
                : <span className="text-muted-foreground">—</span>,
        },
        {
            id: 'statut',
            header: 'État',
            cell: ({ row }) => <Etat statut={row.original.statut} />,
        },
        {
            id: 'createdAt',
            header: () => entete('createdAt', 'Créé le'),
            cell: ({ row }) => <span className="text-xs text-muted-foreground">{dateHeure(row.original.createdAt)}</span>,
        },
        {
            id: 'actions',
            header: '',
            meta: { aDroite: true },
            cell: ({ row }) => {
                const compte = row.original
                const suspendu = compte.statut === 'SUSPENDU'

                return (
                    <MenuActions
                        jeton={jeton}
                        liens={[
                            { libelle: 'Voir', href: compte.url },
                            ...(compte.modifiable ? [{ libelle: 'Modifier', href: compte.urlEdit }] : []),
                        ]}
                        actions={compte.suspendable ? [{
                            libelle: suspendu ? 'Réactiver' : 'Suspendre',
                            action: compte.urlActivation,
                            destructive: !suspendu,
                            confirmer: suspendu ? `Réactiver ${compte.nomComplet} ?` : `Suspendre ${compte.nomComplet} ?`,
                            detail: suspendu
                                ? 'La personne pourra se reconnecter immédiatement.'
                                : 'Sa session en cours est coupée aussitôt : l\'API relit le compte à chaque requête.',
                            libelleAction: suspendu ? 'Réactiver' : 'Suspendre',
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
            messageVide="Aucun compte"
        />
    )
}
