import * as React from 'react'
import { ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/assets/components/ui/badge'
import { ServerDataTable } from '../../components/server/server-data-table'
import { dateHeure, MenuActions, texte } from '../../components/cellules'
import { ServerMeta, ServerTableFilter } from '../../hooks/useServerTable'
import { ElementCorbeille } from '../../models/gestion.model'

/*
    - La corbeille, tous clients confondus. Deux gestes par ligne, et chacun peut être refusé pour une
      raison différente — la ligne porte donc ses deux motifs et on les AFFICHE : un bouton grisé sans
      explication laisse chercher pendant dix minutes.

    - Pas d'en-têtes triables : l'API rend une liste déjà ordonnée du plus récemment supprimé au plus
      ancien, sans pagination. Proposer un tri qu'elle ne sait pas honorer laisserait croire au
      classement demandé.
*/

type Ligne = ElementCorbeille & { urlRestauration: string; urlPurge: string }

interface Props {
    items: Ligne[]
    meta: ServerMeta
    queryParams: Record<string, string>
    /* La map 'id => nom' des clients, pour la liste déroulante */
    entreprises: Record<string, string>
    jeton: string
}

export default function TableCorbeille({ items, meta, queryParams, entreprises, jeton }: Props) {
    const colonnes: ColumnDef<Ligne, unknown>[] = [
        {
            id: 'libelle',
            header: 'Élément',
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">{row.original.libelle}</div>
                    {/* D'où il venait : sans cela, deux « Hévéa » de deux postes se confondent */}
                    <div className="text-xs text-muted-foreground">{texte(row.original.contexte)}</div>
                </div>
            ),
        },
        {
            id: 'type',
            header: 'Type',
            cell: ({ row }) => <Badge variant="outline">{row.original.typeLibelle}</Badge>,
        },
        {
            id: 'entreprise',
            header: 'Client',
            cell: ({ row }) => texte(row.original.entreprise),
        },
        {
            id: 'supprime',
            header: 'Supprimé',
            cell: ({ row }) => (
                <div>
                    <div className="whitespace-nowrap text-xs">{dateHeure(row.original.supprimeLe)}</div>
                    {/* 'deletedBy' est un entier en base : l'API en rend le nom, c'est la question qu'on se pose ici */}
                    <div className="text-xs text-muted-foreground">par {texte(row.original.supprimePar)}</div>
                </div>
            ),
        },
        {
            id: 'pesees',
            header: 'Pesées',
            meta: { aDroite: true },
            /* C'est ce chiffre qui décide de la purge : un référentiel très utilisé porte tout un historique de montants. */
            cell: ({ row }) => row.original.peseesCount > 0
                ? row.original.peseesCount.toLocaleString('fr-FR')
                : <span className="text-muted-foreground">0</span>,
        },
        {
            id: 'etat',
            header: 'Ce qui bloque',
            /*
                - Les deux refus possibles, en clair. Un homonyme vivant empêche la restauration ; des
                  pesées rattachées empêchent la purge. Les deux peuvent valoir en même temps.
            */
            cell: ({ row }) => {
                const motifs = [row.original.motifNonRestaurable, row.original.motifNonPurgeable].filter(Boolean)

                if (motifs.length === 0) {
                    return <span className="text-xs text-muted-foreground">rien, les deux gestes sont possibles</span>
                }

                return (
                    <ul className="space-y-0.5">
                        {motifs.map(motif => (
                            <li key={motif} className="text-xs text-muted-foreground">{motif}</li>
                        ))}
                    </ul>
                )
            },
        },
        {
            id: 'actions',
            header: '',
            meta: { aDroite: true },
            cell: ({ row }) => {
                const element = row.original

                return (
                    <MenuActions
                        jeton={jeton}
                        actions={[
                            ...(element.restaurable ? [{
                                libelle: 'Restaurer',
                                action: element.urlRestauration,
                                confirmer: `Restaurer « ${element.libelle} » ?`,
                                detail: 'Il réapparaîtra dans les écrans de son entreprise, avec son prix et son historique.',
                                libelleAction: 'Restaurer',
                            }] : []),
                            ...(element.purgeable ? [{
                                libelle: 'Supprimer définitivement',
                                action: element.urlPurge,
                                destructive: true,
                                confirmer: `Supprimer définitivement « ${element.libelle} » ?`,
                                detail: 'La ligne quitte la base. C\'est le seul geste de l\'application sans retour possible.',
                                libelleAction: 'Supprimer définitivement',
                            }] : []),
                        ]}
                    />
                )
            },
        },
    ]

    const filtres: ServerTableFilter[] = [
        {
            type: 'select',
            name: 'type',
            label: 'Tous les types',
            options: [
                { label: 'Produits', value: 'produit' },
                { label: 'Planteurs', value: 'fournisseur' },
            ],
        },
        {
            type: 'select',
            name: 'entreprise',
            label: 'Tous les clients',
            options: Object.entries(entreprises).map(([valeur, libelle]) => ({ value: valeur, label: libelle })),
        },
    ]

    return (
        <ServerDataTable
            columns={colonnes}
            data={items}
            meta={meta}
            queryParams={queryParams}
            filters={filtres}
            messageVide="La corbeille est vide"
        />
    )
}
