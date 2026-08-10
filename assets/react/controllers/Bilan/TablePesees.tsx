import * as React from 'react'
import { ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/assets/components/ui/badge'
import { ServerDataTable } from '../../components/server/server-data-table'
import { entetesDepuis } from '../../components/server/server-data-table-column-header'
import { MenuActions, texte } from '../../components/cellules'
import { ServerMeta, ServerTableFilter, SortMeta } from '../../hooks/useServerTable'
import { Pesee, StatutReglement } from '../../models/pesee.model'

/*
    - La table du bilan. Elle ne décrit que les colonnes : le tri, les filtres et la pagination
      viennent de 'ServerDataTable', et l'état vit dans l'URL.

    - Les données arrivent en props depuis le contrôleur Symfony, déjà filtrées et cloisonnées par
      l'API. Ce composant ne fait aucun appel réseau.

    - Seules quatre colonnes sont triables : celles que l'API expose à son 'OrderFilter'. Proposer
      une flèche sur les autres promettrait un tri qui retomberait silencieusement sur la date, ce
      qui est pire que pas de flèche du tout.
*/

const REGLEMENT: Record<StatutReglement, { libelle: string; variant: 'default' | 'secondary' | 'outline' }> = {
    SOLDE: { libelle: 'Soldée', variant: 'default' },
    PARTIEL: { libelle: 'Partielle', variant: 'secondary' },
    NON_PAYE: { libelle: 'Non payée', variant: 'outline' },
}

const nombre = (valeur: number | null, unite: string) =>
    valeur ? `${valeur.toLocaleString('fr-FR')} ${unite}` : '—'

type Ligne = Pesee & { url: string; urlPaiement: string | null }

interface Props {
    items: Ligne[]
    meta: ServerMeta
    queryParams: Record<string, string>
    sortMeta: SortMeta
    /* Vrai pour un administrateur ou un opérateur : eux seuls versent aux planteurs */
    payable: boolean
    /* Les maps 'valeur => libellé' des listes déroulantes, bornées au périmètre par l'API */
    sites: Record<string, string>
    referentiels: Record<string, string[]>
}

export default function TablePesees({ items, meta, queryParams, sortMeta, payable, sites, referentiels }: Props) {
    const entete = entetesDepuis(sortMeta)

    const colonnes: ColumnDef<Ligne, unknown>[] = [
        {
            id: 'numticket',
            header: () => entete('numticket', 'Ticket'),
            cell: ({ row }) => (
                <div>
                    <span className="font-mono text-xs">{row.original.numticket ?? row.original.codepesee ?? '—'}</span>
                    {/* Un camion encore sur le pont n'a ni poids net ni montant : il faut que ça se voie */}
                    {row.original.statut === 'EN_COURS' && <div className="text-xs text-muted-foreground">sur le pont</div>}
                </div>
            ),
        },
        {
            id: 'peseeAt2',
            header: () => entete('peseeAt2', 'Date'),
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-xs">
                    {row.original.peseeAt2
                        ? new Date(row.original.peseeAt2).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
                        : '—'}
                </span>
            ),
        },
        {
            id: 'site',
            header: 'Pont bascule',
            cell: ({ row }) => texte(row.original.site?.libellesite),
        },
        {
            id: 'produit',
            header: 'Produit',
            cell: ({ row }) => texte(row.original.produit?.libelle),
        },
        {
            id: 'fournisseur',
            header: 'Planteur',
            cell: ({ row }) => texte(row.original.fournisseur?.nomComplet),
        },
        {
            id: 'immatriculation',
            header: 'Véhicule',
            cell: ({ row }) => texte(row.original.immatriculation),
        },
        {
            id: 'poidsnet',
            header: () => entete('poidsnet', 'Net', true),
            meta: { aDroite: true },
            cell: ({ row }) => (
                <div>
                    <div>{nombre(row.original.poidsnet, 'kg')}</div>
                    {/* Les deux passages sous le net : c'est ce qui permet de contester un poids */}
                    <div className="text-xs text-muted-foreground">
                        {nombre(row.original.poids1, '')} → {nombre(row.original.poids2, '')}
                    </div>
                </div>
            ),
        },
        {
            id: 'montantdu',
            header: () => entete('montantdu', 'Montant', true),
            meta: { aDroite: true },
            cell: ({ row }) => {
                const reste = (row.original.montantdu ?? 0) - (row.original.montantpaye ?? 0)

                return (
                    <div>
                        <div>{nombre(row.original.montantdu, 'F')}</div>
                        {(row.original.montantpaye ?? 0) > 0 && (
                            <div className="text-xs text-muted-foreground">reste {nombre(reste, 'F')}</div>
                        )}
                    </div>
                )
            },
        },
        {
            id: 'statutReglement',
            header: 'Règlement',
            cell: ({ row }) => {
                const etat = row.original.statutReglement ? REGLEMENT[row.original.statutReglement] : null

                return etat ? <Badge variant={etat.variant}>{etat.libelle}</Badge> : <span>—</span>
            },
        },
        {
            id: 'actions',
            header: '',
            meta: { aDroite: true },
            /*
                - Le versement part d'ici : c'est en regardant une pesée qu'on décide de payer, pas en
                  ouvrant un écran de paiements vide. C'est le contrôleur qui décide si la ligne est
                  payable — terminée, non soldée, et l'utilisateur habilité.
            */
            cell: ({ row }) => (
                <MenuActions
                    liens={[
                        { libelle: 'Voir la pesée', href: row.original.url },
                        ...(payable && row.original.urlPaiement ? [{ libelle: 'Verser au planteur', href: row.original.urlPaiement }] : []),
                    ]}
                />
            ),
        },
    ]

    /*
        - Les valeurs des listes déroulantes viennent de '/api/stats/referentiels' : ce sont celles
          RÉELLEMENT rencontrées dans les pesées du périmètre. Un transporteur qui n'est jamais passé
          n'a aucune raison d'apparaître.
    */
    const libre = (nom: string, label: string): ServerTableFilter => ({
        type: 'select',
        name: nom,
        label,
        options: (referentiels[nom] ?? []).map(v => ({ value: v, label: v })),
    })

    const filtres: ServerTableFilter[] = [
        { type: 'text', name: 'recherche', label: 'Rechercher', placeholder: 'Numéro de ticket…' },
        { type: 'select', name: 'site', label: 'Tous les ponts bascules', options: Object.entries(sites).map(([valeur, libelle]) => ({ value: valeur, label: libelle })) },
        {
            type: 'select',
            name: 'statutReglement',
            label: 'Tous les règlements',
            options: [
                { label: 'Non payée', value: 'NON_PAYE' },
                { label: 'Partielle', value: 'PARTIEL' },
                { label: 'Soldée', value: 'SOLDE' },
            ],
        },
        libre('mouvement', 'Toutes les opérations'),
        libre('destination', 'Toutes les destinations'),
        libre('provenance', 'Toutes les provenances'),
        libre('client', 'Tous les clients'),
        libre('transporteur', 'Tous les transporteurs'),
        libre('chauffeur', 'Tous les chauffeurs'),
        libre('vehicule', 'Tous les véhicules'),
    ]

    return (
        <ServerDataTable
            columns={colonnes}
            data={items}
            meta={meta}
            queryParams={queryParams}
            filters={filtres}
            messageVide="Aucune pesée ne correspond aux critères"
        />
    )
}
