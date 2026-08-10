import * as React from 'react'
import { ColumnDef, flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table'
import { Input } from '@/assets/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/assets/components/ui/select'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/assets/components/ui/table'
import { ServerMeta, ServerTableFilter, useServerTable } from '../../hooks/useServerTable'
import { ServerDataTablePagination } from './server-data-table-pagination'

/*
    - La table réutilisée par tous les écrans de liste. Elle ne connaît ni les pesées, ni les
      paiements : on lui passe des colonnes et une page de résultats, elle s'occupe du reste.

    - Elle ne charge rien elle-même. Les données arrivent déjà rendues par le contrôleur Symfony,
      qui porte le JWT : aucune authentification à rejouer en JavaScript.

    - Les filtres sont DANS la table, pas dans un formulaire Twig au-dessus : ils écrivent dans la
      même URL que le tri et la pagination, donc un écran filtré se partage tel quel.
*/

interface Props<TData, TValue> {
    columns: ColumnDef<TData, TValue>[]
    data: TData[]
    meta: ServerMeta
    queryParams: Record<string, string>
    filters?: ServerTableFilter[]
    messageVide?: string
}

export function ServerDataTable<TData, TValue>({
    columns,
    data,
    meta,
    queryParams,
    filters = [],
    messageVide = 'Aucun résultat',
}: Props<TData, TValue>) {
    const { navigate, buildUrl } = useServerTable(queryParams)
    const debounce = React.useRef<ReturnType<typeof setTimeout> | null>(null)

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualSorting: true,
        manualPagination: true,
        manualFiltering: true,
        pageCount: meta.totalPages,
    })

    /* Le délai laisse finir la frappe : sans lui, « Gagnoa » partirait en six navigations. */
    const differer = (action: () => void, delai = 400) => {
        if(debounce.current) {
            clearTimeout(debounce.current)
        }

        debounce.current = setTimeout(action, delai)
    }

    return (
        <div className="space-y-3">
            <div className="overflow-hidden rounded-md border">
                {filters.length > 0 && (
                    <div className="flex items-center gap-2 overflow-x-auto border-b px-3 py-3">
                        {filters.map(filtre => {
                            if(filtre.type === 'text') {
                                return (
                                    <Input
                                        key={filtre.name}
                                        placeholder={filtre.placeholder ?? filtre.label}
                                        defaultValue={queryParams[filtre.name] ?? ''}
                                        className="w-72 shrink-0"
                                        onChange={e => {
                                            const valeur = e.target.value

                                            differer(() => navigate({ [filtre.name]: valeur || null, page: '1' }))
                                        }}
                                    />
                                )
                            }

                            if(filtre.type === 'select') {
                                return (
                                    <Select
                                        key={filtre.name}
                                        defaultValue={queryParams[filtre.name] ?? 'all'}
                                        items={{ all: filtre.label, ...Object.fromEntries((filtre.options ?? []).map(o => [o.value, o.label])) }} /*
                                            - Sans 'items', le déclencheur de base-ui affiche la valeur brute : un filtre rechargé depuis l'URL montrait 'BLOQUE' au lieu de 'Bloqué'.
                                        */
                                        onValueChange={v => navigate({ [filtre.name]: v === 'all' ? null : v, page: '1' })}
                                    >
                                        <SelectTrigger className="w-45 shrink-0">
                                            <SelectValue placeholder={filtre.label} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{filtre.label}</SelectItem>
                                            {filtre.options?.map(option => (
                                                <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )
                            }

                            return (
                                <div key={filtre.name} className="flex shrink-0 items-center gap-1.5">
                                    <Input
                                        type="date"
                                        defaultValue={queryParams[`${filtre.name}_debut`] ?? ''}
                                        className="w-40"
                                        onChange={e => differer(() => navigate({ [`${filtre.name}_debut`]: e.target.value || null, page: '1' }), 0)} // Pas de délai sur un sélecteur de date
                                    />
                                    <span className="text-sm text-muted-foreground">→</span>
                                    <Input
                                        type="date"
                                        defaultValue={queryParams[`${filtre.name}_fin`] ?? ''}
                                        className="w-40"
                                        onChange={e => differer(() => navigate({ [`${filtre.name}_fin`]: e.target.value || null, page: '1' }), 0)}
                                    />
                                </div>
                            )
                        })}
                    </div>
                )}

                <div className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map(groupe => (
                                <TableRow key={groupe.id}>
                                    {groupe.headers.map(entete => (
                                        <TableHead key={entete.id} className={entete.column.columnDef.meta?.aDroite ? 'text-right' : undefined}>
                                            {entete.isPlaceholder ? null : flexRender(entete.column.columnDef.header, entete.getContext())}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {table.getRowModel().rows.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={columns.length} className="h-24 text-center text-muted-foreground">
                                        {messageVide}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                table.getRowModel().rows.map(ligne => (
                                    <TableRow key={ligne.id}>
                                        {ligne.getVisibleCells().map(cellule => (
                                            <TableCell
                                                key={cellule.id}
                                                className={cellule.column.columnDef.meta?.aDroite ? 'text-right tabular-nums' : undefined}
                                            >
                                                {flexRender(cellule.column.columnDef.cell, cellule.getContext())}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <ServerDataTablePagination meta={meta} buildUrl={buildUrl} />
        </div>
    )
}
