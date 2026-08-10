import * as React from 'react'
import { ColumnDef } from '@tanstack/react-table'
import { ServerDataTable } from '../../components/server/server-data-table'
import { entetesDepuis } from '../../components/server/server-data-table-column-header'
import { dateHeure, Etat, MenuActions, montant, texte } from '../../components/cellules'
import { ServerMeta, ServerTableFilter, SortMeta } from '../../hooks/useServerTable'
import { Paiement } from '../../models/gestion.model'

/*
    - Les versements aux planteurs, par mobile money. Un versement porte toujours sur une pesée
      précise, et une pesée peut être payée en plusieurs fois.

    - Un versement EN COURS est déjà sorti de la caisse sans être confirmé : c'est lui qui explique
      l'écart entre le solde affiché d'un poste et ce que son opérateur peut réellement engager.

    - Aucune action de ligne : on n'annule pas un versement, c'est la passerelle qui tranche, et un
      échec donne lieu à un remboursement automatique.
*/

type Ligne = Paiement & { urlPesee: string | null; urlRecu: string }

interface Props {
    items: Ligne[]
    meta: ServerMeta
    queryParams: Record<string, string>
    sortMeta: SortMeta
    /* La map 'id => libellé' que rend 'GestionController::options()' */
    sites: Record<string, string>
}

export default function TablePaiements({ items, meta, queryParams, sortMeta, sites }: Props) {
    const entete = entetesDepuis(sortMeta)

    const colonnes: ColumnDef<Ligne, unknown>[] = [
        {
            id: 'reference',
            header: 'Référence',
            // C'est elle qu'on relit sur un reçu ou qu'on donne à la passerelle en cas de litige
            cell: ({ row }) => <span className="font-mono text-xs">{row.original.reference}</span>,
        },
        {
            id: 'createdAt',
            header: () => entete('createdAt', 'Engagé le'),
            cell: ({ row }) => <span className="text-xs">{dateHeure(row.original.createdAt)}</span>,
        },
        {
            id: 'fournisseur',
            header: 'Planteur',
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">{texte(row.original.fournisseur?.nomComplet)}</div>
                    <div className="text-xs text-muted-foreground">
                        {texte(row.original.numeroDestinataire)}
                        {row.original.reseau && ` · ${row.original.reseau}`}
                    </div>
                </div>
            ),
        },
        {
            id: 'site',
            header: 'Pont bascule',
            cell: ({ row }) => texte(row.original.site?.libellesite),
        },
        {
            id: 'operation',
            header: 'Pesée',
            // Un versement sans pesée n'existe pas : le ticket est le lien entre l'argent et la marchandise
            cell: ({ row }) => <span className="font-mono text-xs">{texte(row.original.operation?.numticket)}</span>,
        },
        {
            id: 'montant',
            header: () => entete('montant', 'Montant', true),
            meta: { aDroite: true },
            cell: ({ row }) => montant(row.original.montant),
        },
        {
            id: 'statut',
            header: 'État',
            cell: ({ row }) => (
                <div>
                    <Etat statut={row.original.statut} />
                    {/* Le message de la passerelle explique un échec : sans lui, on relance à l'aveugle */}
                    {row.original.messageErreur && <div className="mt-1 text-xs text-destructive">{row.original.messageErreur}</div>}
                    {row.original.confirmeAt && <div className="mt-1 text-xs text-muted-foreground">{dateHeure(row.original.confirmeAt)}</div>}
                </div>
            ),
        },
        {
            id: 'actions',
            header: '',
            meta: { aDroite: true },
            cell: ({ row }) => (
                <MenuActions
                    liens={[
                        // Le reçu est ce que le planteur emporte : il porte la référence, qui identifie l'opération auprès de la passerelle
                        { libelle: 'Reçu à imprimer', href: row.original.urlRecu },
                        ...(row.original.urlPesee ? [{ libelle: 'Voir la pesée', href: row.original.urlPesee }] : []),
                    ]}
                />
            ),
        },
    ]

    const filtres: ServerTableFilter[] = [
        { type: 'text', name: 'recherche', label: 'Rechercher', placeholder: 'Référence…' },
        { type: 'select', name: 'site', label: 'Tous les ponts bascules', options: Object.entries(sites).map(([valeur, libelle]) => ({ value: valeur, label: libelle })) },
        {
            type: 'select',
            name: 'statut',
            label: 'Tous les états',
            options: [
                { label: 'En attente', value: 'EN_ATTENTE' },
                { label: 'Envoyé', value: 'ENVOYE' },
                { label: 'Réussi', value: 'REUSSI' },
                { label: 'Échoué', value: 'ECHOUE' },
                { label: 'Annulé', value: 'ANNULE' },
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
            messageVide="Aucun versement"
        />
    )
}
