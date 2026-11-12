import * as React from 'react'
import { ColumnDef } from '@tanstack/react-table'
import { ServerDataTable } from '../../components/server/server-data-table'
import { entetesDepuis } from '../../components/server/server-data-table-column-header'
import { dateHeure, Etat, MenuActions, montant, texte } from '../../components/cellules'
import { ServerMeta, ServerTableFilter, SortMeta } from '../../hooks/useServerTable'
import { DemandePrelevement } from '../../models/gestion.model'

/*
    - Les demandes de mise en compte. À côté du taux réclamé, on montre ce qu'il représente sur une
      livraison ordinaire : un chiffre par kilo ne parle à personne tant qu'on ne l'a pas multiplié,
      et c'est pourtant sur lui que l'administrateur décide.

    - Pas de colonne « accordé », contrairement aux demandes de solde : le taux approuvé est celui
      qui a été demandé. L'administrateur ouvre ou refuse, il ne négocie pas l'épargne d'autrui.

    - Une demande porte sur UN PRODUIT, et la colonne rappelle son prix au kilo : le taux doit rester
      en dessous, sinon le planteur repartirait les mains vides de chaque livraison.
*/

type Ligne = DemandePrelevement & {
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

export default function TableDemandesPrelevement({ items, meta, queryParams, sortMeta, sites, jeton }: Props) {
    const entete = entetesDepuis(sortMeta)

    const colonnes: ColumnDef<Ligne, unknown>[] = [
        {
            id: 'createdAt',
            header: () => entete('createdAt', 'Demandée le'),
            cell: ({ row }) => <span className="text-xs">{dateHeure(row.original.createdAt)}</span>,
        },
        {
            id: 'fournisseur',
            header: 'Planteur',
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">{texte(row.original.fournisseur?.nomComplet)}</div>
                    <div className="text-xs text-muted-foreground">{texte(row.original.site?.libellesite)}</div>
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
            id: 'produit',
            header: 'Produit',
            /* Sans lui la ligne ne veut rien dire : 20 F par kilo n'est ni la même décision ni le
               même effort selon qu'il s'agit du cacao ou de l'hévéa. Le prix est juste en dessous,
               parce que c'est lui qui borne le taux. */
            cell: ({ row }) => (
                <div>
                    <div>{texte(row.original.produit?.libelle)}</div>
                    <div className="text-xs text-muted-foreground">
                        {row.original.produit ? `${montant(row.original.produit.prix)} le kilo` : '—'}
                    </div>
                </div>
            ),
        },
        {
            id: 'tauxDemande',
            header: () => entete('tauxDemande', 'Par kilo', true),
            meta: { aDroite: true },
            /* Le taux seul ne dit rien : la ligne du dessous le traduit en francs sur une tonne, qui est l'ordre de grandeur d'une livraison. */
            cell: ({ row }) => (
                <div>
                    <div>{montant(row.original.tauxDemande)}</div>
                    <div className="text-xs text-muted-foreground">
                        soit {montant(row.original.tauxDemande * 1000)} sur 1 000 kg
                    </div>
                </div>
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
                            detail: 'Elle sort de l\'attente, et le planteur pourra en déposer une nouvelle.',
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
            messageVide="Aucune demande de mise en compte"
        />
    )
}
