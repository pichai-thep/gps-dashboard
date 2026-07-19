export type ReportPrintCell = string | number | null | undefined

export function openReportPrintWindow(title: string) {
    const target = window.open('', '_blank')

    if (!target) return null

    target.opener = null
    target.document.write(`
        <!doctype html>
        <html>
          <head><title>${escapeHtml(title)}</title></head>
          <body style="font-family: Arial, sans-serif; padding: 24px;">
            <p>Preparing report…</p>
          </body>
        </html>
    `)
    target.document.close()

    return target
}

export function renderReportPrintWindow(
    target: Window,
    options: {
        title: string
        period: string
        headers: string[]
        rows: ReportPrintCell[][]
    }
) {
    const headerHtml = options.headers
        .map((header) => `<th>${escapeHtml(header)}</th>`)
        .join('')
    const rowHtml = options.rows
        .map((row) => `
            <tr>
              ${row.map((cell) => `<td>${escapeHtml(cell)}</td>`).join('')}
            </tr>
        `)
        .join('')

    target.document.open()
    target.document.write(`
        <!doctype html>
        <html>
          <head>
            <meta charset="utf-8">
            <title>${escapeHtml(options.title)}</title>
            <style>
              @page { size: landscape; margin: 12mm; }
              body { color: #111827; font-family: Arial, sans-serif; font-size: 10px; }
              h1 { margin: 0 0 4px; font-size: 20px; }
              .period { margin-bottom: 14px; color: #475569; font-size: 11px; }
              table { width: 100%; border-collapse: collapse; }
              th, td {
                padding: 5px 6px;
                border: 1px solid #94a3b8;
                text-align: left;
                vertical-align: top;
                overflow-wrap: anywhere;
              }
              th { background: #e2e8f0; font-weight: 700; }
              tr:nth-child(even) td { background: #f8fafc; }
            </style>
          </head>
          <body>
            <h1>${escapeHtml(options.title)}</h1>
            <div class="period">${escapeHtml(options.period)}</div>
            <table>
              <thead><tr>${headerHtml}</tr></thead>
              <tbody>${rowHtml}</tbody>
            </table>
            <script>
              window.addEventListener('load', () => {
                window.setTimeout(() => {
                  window.focus();
                  window.print();
                }, 150);
              });
            <\/script>
          </body>
        </html>
    `)
    target.document.close()
}

function escapeHtml(value: ReportPrintCell) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
}
