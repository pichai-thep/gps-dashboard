export type ReportExportCell = string | number | null | undefined

export interface ReportExcelRow {
  cells: ReportExportCell[]
  fill?: string
}

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

export async function downloadReportExcel(
  filename: string,
  sheetName: string,
  headers: string[],
  rows: ReportExcelRow[],
) {
  const { default: JSZip } = await import('jszip')
  const zip = new JSZip()
  const safeSheetName = (sheetName.replace(/[\\/*?:[\]]/g, ' ').trim() || 'Report').slice(0, 31)
  const lastColumn = excelColumnName(headers.length)
  const tableRange = `A1:${lastColumn}${rows.length + 1}`

  zip.file('[Content_Types].xml', contentTypesXml)
  zip.folder('_rels')?.file('.rels', packageRelationsXml)
  zip.folder('docProps')?.file('app.xml', appPropertiesXml)
  zip.folder('docProps')?.file('core.xml', corePropertiesXml)
  zip.folder('xl')?.file('workbook.xml', workbookXml(safeSheetName))
  zip.folder('xl')?.file('styles.xml', stylesXml)
  zip.folder('xl')?.folder('_rels')?.file('workbook.xml.rels', workbookRelationsXml)
  zip.folder('xl')?.folder('worksheets')?.file(
    'sheet1.xml',
    worksheetXml(headers, rows, tableRange),
  )

  const blob = await zip.generateAsync({
    type: 'blob',
    mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

function worksheetXml(headers: string[], rows: ReportExcelRow[], tableRange: string) {
  const widths = headers.map((header, index) => {
    const values = [header, ...rows.map((row) => row.cells[index])]
    return Math.min(50, Math.max(12, Math.max(...values.map((value) => String(value ?? '').length)) + 2))
  })
  const columns = widths
    .map((width, index) => `<col min="${index + 1}" max="${index + 1}" width="${width}" customWidth="1"/>`)
    .join('')
  const sheetRows = [
    excelRowXml(headers, 1, 1),
    ...rows.map((row, index) => excelRowXml(row.cells, index + 2, excelStyleIndex(row.fill))),
  ].join('')

  return xmlDocument(`
    <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
      <dimension ref="${tableRange}"/>
      <sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>
      <cols>${columns}</cols>
      <sheetData>${sheetRows}</sheetData>
      <autoFilter ref="${tableRange}"/>
    </worksheet>
  `)
}

function excelRowXml(cells: ReportExportCell[], rowNumber: number, styleIndex: number) {
  const cellXml = cells.map((value, index) => {
    const reference = `${excelColumnName(index + 1)}${rowNumber}`
    return `<c r="${reference}" s="${styleIndex}" t="inlineStr"><is><t xml:space="preserve">${escapeXml(value)}</t></is></c>`
  }).join('')
  return `<row r="${rowNumber}">${cellXml}</row>`
}

function excelStyleIndex(fill?: string) {
  if (fill === 'FFFFC7CE') return 2
  if (fill === 'FFFFEB9C') return 3
  if (fill === 'FFC6EFCE') return 4
  return 0
}

function excelColumnName(columnNumber: number) {
  let result = ''
  for (let current = columnNumber; current > 0; current = Math.floor((current - 1) / 26)) {
    result = String.fromCharCode(65 + ((current - 1) % 26)) + result
  }
  return result
}

function escapeXml(value: ReportExportCell) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;')
}

function xmlDocument(body: string) {
  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>${body}`
}

function workbookXml(sheetName: string) {
  return xmlDocument(`
    <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
      <sheets><sheet name="${escapeXml(sheetName)}" sheetId="1" r:id="rId1"/></sheets>
    </workbook>
  `)
}

const contentTypesXml = xmlDocument(`
  <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  </Types>
`)

const packageRelationsXml = xmlDocument(`
  <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
  </Relationships>
`)

const workbookRelationsXml = xmlDocument(`
  <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  </Relationships>
`)

const stylesXml = xmlDocument(`
  <styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>
    <fills count="6"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFC7CE"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFEB9C"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFC6EFCE"/><bgColor indexed="64"/></patternFill></fill></fills>
    <borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border></borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="3" borderId="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="0" fillId="4" borderId="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="0" fillId="5" borderId="1" applyFill="1" applyBorder="1"/></cellXfs>
    <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
  </styleSheet>
`)

const appPropertiesXml = xmlDocument(`
  <Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>GPS Fleet</Application></Properties>
`)

const corePropertiesXml = xmlDocument(`
  <cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:creator>GPS Fleet</dc:creator></cp:coreProperties>
`)
