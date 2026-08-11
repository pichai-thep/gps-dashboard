export type ReportExportCell = string | number | null | undefined

export function downloadReportCsv(
  filename: string,
  headers: string[],
  rows: ReportExportCell[][],
) {
  const csv = [
    headers.map(csvCell).join(','),
    ...rows.map((row) => row.map(csvCell).join(',')),
  ].join('\n')
  const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

function csvCell(value: ReportExportCell) {
  return `"${String(value ?? '').replace(/"/g, '""')}"`
}
