export type ReportPrintCell = string | number | null | undefined
export interface StyledReportPrintCell {
    value: ReportPrintCell
    highlighted?: boolean
}

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
        rows: Array<Array<ReportPrintCell | StyledReportPrintCell>>
        criteria?: Array<{ label: string; value: ReportPrintCell }>
        summary?: Array<{ label: string; value: ReportPrintCell }>
        criteriaTitle?: string
        summaryTitle?: string
        dataTitle?: string
        wide?: boolean
        columnWidths?: Array<string | undefined>
    }
) {
    const logoUrl = new URL(
        `/logos/${encodeURIComponent(window.location.hostname)}.png`,
        window.location.origin
    ).href
    const fallbackLogoUrl = new URL('/logos/default.png', window.location.origin).href
    const headerHtml = options.headers
        .map((header) => `<th>${escapeHtml(header)}</th>`)
        .join('')
    const columnGroupHtml = options.columnWidths?.length
        ? `<colgroup>${options.headers.map((_, index) => {
            const width = options.columnWidths?.[index]
            return width ? `<col style="width:${escapeHtml(width)}">` : '<col>'
        }).join('')}</colgroup>`
        : ''
    const rowHtml = options.rows
        .map((row) => `
            <tr>
              ${row.map((cell) => {
                  const styled = typeof cell === 'object' && cell !== null
                  const value = styled ? cell.value : cell
                  return `<td${styled && cell.highlighted ? ' class="over-limit"' : ''}>${escapeHtml(value)}</td>`
              }).join('')}
            </tr>
        `)
        .join('')
    const criteriaHtml = printInfoSection(options.criteriaTitle, options.criteria)
    const summaryHtml = printInfoSection(options.summaryTitle, options.summary)
    const dataTitleHtml = options.dataTitle
        ? `<h2 class="section-title">${escapeHtml(options.dataTitle)}</h2>`
        : ''

    target.document.open()
    target.document.write(`
        <!doctype html>
        <html>
          <head>
            <meta charset="utf-8">
            <title>${escapeHtml(options.title)}</title>
            <style>
              @page { size: ${options.wide ? 'A2 landscape' : 'landscape'}; margin: ${options.wide ? '8mm' : '12mm'}; }
              body { color: #111827; font-family: Arial, sans-serif; font-size: ${options.wide ? '9px' : '10px'}; }
              .report-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 24px;
                margin-bottom: 14px;
              }
              .report-heading { min-width: 0; }
              .report-logo { width: auto; max-width: 180px; height: 56px; object-fit: contain; }
              h1 { margin: 0 0 4px; font-size: 20px; }
              .section-title { margin: 12px 0 7px; font-size: 13px; }
              .period { color: #475569; font-size: 11px; }
              .info-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 6px;
                margin-bottom: 10px;
              }
              .info-item {
                padding: 6px 8px;
                border: 1px solid #cbd5e1;
                border-radius: 4px;
                break-inside: avoid;
              }
              .info-label { display: block; color: #64748b; font-size: 9px; }
              .info-value { display: block; margin-top: 2px; font-size: 11px; }
              table { width: 100%; border-collapse: collapse; }
              thead { display: table-header-group; }
              th, td {
                padding: ${options.wide ? '4px 5px' : '5px 6px'};
                border: 1px solid #94a3b8;
                text-align: left;
                vertical-align: top;
                overflow-wrap: anywhere;
              }
              th { background: #e2e8f0; font-weight: 700; }
              tr:nth-child(even) td { background: #f8fafc; }
              td.over-limit {
                color: #7f1d1d;
                font-weight: 700;
                background: #fca5a5 !important;
                border: 2px solid #dc2626;
              }
            </style>
          </head>
          <body>
            <div class="report-header">
              <div class="report-heading">
                <h1>${escapeHtml(options.title)}</h1>
                <div class="period">${escapeHtml(options.period)}</div>
              </div>
              <img
                class="report-logo"
                src="${escapeHtml(logoUrl)}"
                alt="Brand logo"
                onerror="this.onerror=null;this.src='${escapeHtml(fallbackLogoUrl)}'"
              >
            </div>
            ${criteriaHtml}
            ${summaryHtml}
            ${dataTitleHtml}
            <table>
              ${columnGroupHtml}
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

function printInfoSection(
    title: string | undefined,
    items: Array<{ label: string; value: ReportPrintCell }> | undefined,
) {
    if (!items?.length) return ''

    const itemHtml = items.map((item) => `
        <div class="info-item">
          <span class="info-label">${escapeHtml(item.label)}</span>
          <strong class="info-value">${escapeHtml(item.value)}</strong>
        </div>
    `).join('')

    return `
      ${title ? `<h2 class="section-title">${escapeHtml(title)}</h2>` : ''}
      <div class="info-grid">${itemHtml}</div>
    `
}

function escapeHtml(value: ReportPrintCell) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
}
