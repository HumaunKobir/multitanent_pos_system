import { Fragment } from 'react';
import { cn } from '@/lib/utils';

/**
 * @typedef {'left'|'center'|'right'} DataTableAlign
 */

/**
 * @typedef {object} DataTableColumn
 * @property {string} [id]
 * @property {import('react').ReactNode} header
 * @property {string} [accessorKey] — read cell value from row[accessorKey] when `render` is omitted
 * @property {(row: object, rowIndex: number) => import('react').ReactNode} [render]
 * @property {string} [headerClassName]
 * @property {string} [cellClassName]
 * @property {DataTableAlign} [align]
 */

const alignTh = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
};

const alignTd = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
};

/**
 * @param {object} props
 * @param {DataTableColumn[]} props.columns
 * @param {object[]} props.rows
 * @param {string|((row: object) => string|number)} props.rowKey
 * @param {string} [props.emptyMessage]
 * @param {string} [props.className]
 * @param {string} [props.tableClassName]
 * @param {string} [props.caption] — screen-reader only table caption (no visible row above the header)
 * @param {(row: object, rowIndex: number) => import('react').HTMLAttributes<HTMLTableRowElement>} [props.getRowProps]
 * @param {(row: object, rowIndex: number) => import('react').ReactNode} [props.renderExpandedRow] — optional full-width row rendered after each data row
 */
export function DataTable({
    columns,
    rows,
    rowKey,
    emptyMessage = 'No rows yet.',
    className,
    tableClassName,
    caption,
    getRowProps,
    renderExpandedRow,
    ...props
}) {
    const resolveRowKey = typeof rowKey === 'function' ? rowKey : (row) => row[rowKey];

    const cellContent = (column, row, rowIndex) => {
        if (column.render) {
            return column.render(row, rowIndex);
        }
        if (column.accessorKey !== undefined) {
            const value = row[column.accessorKey];

            return value ?? '—';
        }

        return '—';
    };

    return (
        <div
            data-slot="data-table"
            className={cn(
                'overflow-x-auto rounded-none bg-card shadow-sm ring-1 ring-blue-950/10 dark:border-blue-500/22 dark:ring-blue-400/14',
                className,
            )}
            {...props}
        >
            <table className={cn('w-full min-w-[640px] border-collapse text-sm', tableClassName)}>
                {caption ? <caption className="sr-only">{caption}</caption> : null}
                <thead>
                    <tr className="border-b-2 border-blue-900/80 bg-blue-950 text-blue-50 dark:border-blue-600/45 dark:bg-blue-950 dark:text-blue-50">
                        {columns.map((column, index) => {
                            const key = column.id ?? column.accessorKey ?? `col-${index}`;
                            const align = column.align ?? 'left';

                            return (
                                <th
                                    key={key}
                                    scope="col"
                                    className={cn(
                                        'px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-blue-50 dark:text-blue-50',
                                        alignTh[align],
                                        column.headerClassName,
                                    )}
                                >
                                    {column.header}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td colSpan={columns.length} className="px-4 py-10 text-center text-sm text-muted-foreground">
                                {emptyMessage}
                            </td>
                        </tr>
                    ) : (
                        rows.map((row, rowIndex) => {
                            const rowProps = getRowProps?.(row, rowIndex) ?? {};

                            return (
                            <Fragment key={resolveRowKey(row)}>
                            <tr
                                {...rowProps}
                                className={cn(
                                    'border-b border-blue-200/85 transition-colors duration-200 last:border-b-0 dark:border-blue-900/55',
                                    'hover:bg-blue-950/6 dark:hover:bg-blue-500/12',
                                    rowIndex % 2 === 1 ? 'bg-blue-950/4 dark:bg-blue-950/30' : 'bg-transparent',
                                    rowProps.className,
                                )}
                            >
                                {columns.map((column, colIndex) => {
                                    const key = column.id ?? column.accessorKey ?? `col-${colIndex}`;
                                    const align = column.align ?? 'left';

                                    return (
                                        <td
                                            key={key}
                                            className={cn(
                                                'px-4 py-3 align-middle transition-colors duration-200',
                                                alignTd[align],
                                                column.cellClassName,
                                            )}
                                        >
                                            {cellContent(column, row, rowIndex)}
                                        </td>
                                    );
                                })}
                            </tr>
                            {renderExpandedRow && (
                                <tr className="border-b border-blue-200/85 last:border-b-0 dark:border-blue-900/55">
                                    <td colSpan={columns.length} className="p-0">
                                        {renderExpandedRow(row, rowIndex)}
                                    </td>
                                </tr>
                            )}
                            </Fragment>
                            );
                        })
                    )}
                </tbody>
            </table>
        </div>
    );
}
