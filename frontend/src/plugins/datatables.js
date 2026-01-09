import $ from 'jquery'
import 'datatables.net-bs5'

window.$ = $
window.jQuery = $

const initDataTable = (table) => {
  if (table.dataset?.dt === 'off') {
    return
  }
  if ($.fn.DataTable.isDataTable(table)) {
    return
  }

  $(table).DataTable({
    paging: true,
    searching: true,
    info: true,
    ordering: true,
    pageLength: 10,
    lengthChange: true,
    autoWidth: false,
  })
}

export const initDatatables = () => {
  document.querySelectorAll('table').forEach((table) => {
    initDataTable(table)
  })
}

export const observeDatatables = () => {
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType !== Node.ELEMENT_NODE) {
          return
        }
        if (node.matches && node.matches('table')) {
          initDataTable(node)
          return
        }
        const tables = node.querySelectorAll?.('table') || []
        tables.forEach((table) => initDataTable(table))
      })
    })
  })

  observer.observe(document.body, { childList: true, subtree: true })
}
