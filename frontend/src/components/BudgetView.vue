<template>
  <section class="container py-4 budget-page">
    <header><div><p class="eyebrow">{{ auth.user?.church?.name }}<span v-if="auth.user?.department?.name"> · {{ auth.user.department.name }}</span></p><h1>{{ tr('Presupuesto', 'Budget') }}</h1><p>{{ tr('Un lugar sencillo para planear tus gastos y conocer lo disponible.', 'A simple place to plan spending and see what is available.') }}</p></div><button class="btn btn-outline-secondary" :disabled="loading || busy" @click="load">{{ tr('Actualizar', 'Refresh') }}</button></header>
    <p v-if="error" class="notice error" role="alert">{{ error }}</p>
    <p v-if="success" class="notice" role="status">{{ success }}</p>
    <div v-if="loading && !data" class="surface">{{ tr('Cargando presupuesto…', 'Loading budget…') }}</div>
    <template v-if="data">
      <div class="toolbar surface">
        <label>{{ tr('Año del presupuesto esperado', 'Expected budget year') }}<input v-model.number="planYear" class="form-control" type="number" min="2000" max="2100" :disabled="busy" /></label>
        <label v-if="data.periods.length > 1">{{ tr('Periodo', 'Period') }}<select v-model="period" class="form-select" :disabled="busy"><option v-for="option in data.periods" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
        <label v-if="data.departments.length > 1">{{ tr('Departamento', 'Department') }}<select v-model="departmentId" class="form-select" :disabled="busy"><option value="">{{ tr('Todos los departamentos', 'All departments') }}</option><option v-for="department in data.departments" :key="department.id" :value="String(department.id)">{{ department.name }}</option></select></label>
        <span class="muted">{{ tr('Importes en USD · Herramienta de planificación', 'Amounts in USD · Planning tool') }}</span>
      </div>
      <section v-if="data.can_publish" class="surface treasury-settings">
        <label class="self-management"><input type="checkbox" :checked="data.self_management" :disabled="busy || loading" @change="updateSettings($event.target.checked)" /><span>{{ tr('Permitir que todos los departamentos registren su presupuesto esperado, saldo inicial e ingresos', 'Allow all departments to record their own expected budget, starting balance and income') }}</span></label>
        <p class="muted">{{ tr('Los departamentos siempre pueden registrar gastos. El presupuesto esperado no aumenta el dinero disponible.', 'Departments can always record expenses. Expected budgets do not increase available money.') }}</p>
        <p v-if="data.self_management" class="notice warning" role="status">{{ tr('Autogestión activada: Tesorería debe informar a los departamentos de cualquier actualización de su presupuesto, incluyendo cambios en el esperado anual, saldo inicial o nuevos ingresos.', 'Self-management is enabled: Treasury must inform departments of any budget updates, including changes to yearly expectations, starting balances, or new income.') }}</p>
        <p v-if="!data.has_treasurer" class="muted">{{ tr('No hay un tesorero asignado: los departamentos pueden administrar sus fondos hasta que se asigne uno.', 'No treasurer is assigned: departments can manage their funding until one is assigned.') }}</p>
      </section>
      <div class="summary">
        <article><span>{{ tr('Esperado para', 'Expected for') }} {{ data.year }}</span><strong>{{ money(expectedTotal) }}</strong><small class="muted">{{ tr('Plan anual · No es dinero disponible', 'Yearly plan · Not available money') }}</small></article>
        <article><span>{{ tr('Saldo inicial', 'Starting balance') }}</span><strong>{{ money(totals.opening_cents) }}</strong></article>
        <article><span>{{ tr('Ingresos adicionales', 'Additional income') }}</span><strong>{{ money(totals.income_cents) }}</strong></article>
        <article><span>{{ tr('Gastos', 'Expenses') }}</span><strong>{{ money(totals.expense_cents) }}</strong></article>
        <article :class="{ negative: totals.balance_cents < 0 }"><span>{{ tr('Disponible', 'Available') }}</span><strong>{{ money(totals.balance_cents) }}</strong></article>
      </div>
      <section v-if="data.can_publish" class="surface treasury-table">
        <h2>{{ tr('Fondos por departamento', 'Department funding') }}</h2>
        <p class="muted">{{ tr('El esperado corresponde al año seleccionado. En existencia es el saldo actual: saldo inicial + ingresos − gastos, con continuidad entre años.', 'Expected amounts apply to the selected year. In existence is the current balance: starting balance + income − expenses, carried across years.') }}</p>
        <div class="table-responsive"><table class="table" data-dt="off">
          <thead><tr><th>{{ tr('Departamento', 'Department') }}</th><th>{{ tr('Esperado anual', 'Yearly expected') }} · {{ data.year }}</th><th>{{ tr('Saldo inicial', 'Starting balance') }}</th><th>{{ tr('En existencia', 'In existence') }}</th><th>{{ tr('Acciones', 'Actions') }}</th></tr></thead>
          <tbody><tr v-for="department in data.departments" :key="department.id">
            <td><button class="department-link" @click="departmentId = String(department.id)"><i :style="{ background: department.color || '#89958d' }"></i>{{ department.name }}</button></td>
            <td><form class="inline-amount" @submit.prevent="saveExpected(department)"><input :value="expectedDrafts[department.id] ?? (department.expected_cents == null ? '' : (department.expected_cents / 100).toFixed(2))" @input="expectedDrafts[department.id] = $event.target.value" :aria-label="tr('Esperado anual: ', 'Yearly expected: ') + department.name" type="number" min="0" max="999999999.99" step="0.01" required class="form-control" :disabled="busy" /><button class="btn btn-outline-secondary" :disabled="busy || loading">{{ tr('Guardar', 'Save') }}</button></form></td>
            <td><template v-if="department.budget"><span>{{ money(department.budget.opening_cents) }}</span><button class="btn btn-outline-secondary opening-edit" :disabled="busy" @click="openFunding(department, 'opening')">{{ tr('Editar', 'Edit') }}</button></template><form v-else class="inline-amount" @submit.prevent="saveOpening(department)"><input v-model="openingDrafts[department.id]" :aria-label="tr('Saldo inicial: ', 'Starting balance: ') + department.name" type="number" min="0" max="999999999.99" step="0.01" required class="form-control" :disabled="busy" /><button class="btn btn-outline-secondary" :disabled="busy || loading">{{ tr('Guardar', 'Save') }}</button></form></td>
            <td class="cash-balance" :class="{ 'text-danger': department.budget?.balance_cents < 0 }">{{ department.budget ? money(department.budget.balance_cents) : '—' }}</td>
            <td><button class="btn btn-outline-secondary" @click="viewRecords(department)">{{ tr('Ver movimientos', 'View records') }}</button> <button class="btn btn-primary" :disabled="busy || !department.budget" @click="openFunding(department, 'income')">+ {{ tr('Agregar fondos', 'Add funds') }}</button></td>
          </tr></tbody>
        </table></div>
      </section>
      <p v-if="totals.balance_cents < 0" class="notice warning">{{ tr('Los gastos superan el dinero registrado. Revisa el presupuesto antes de nuevos gastos.', 'Expenses exceed recorded funds. Review the budget before further spending.') }}</p>
      <section v-if="canWrite && data.can_self_fund && !data.can_publish" class="surface">
        <h2>{{ tr('Presupuesto esperado', 'Expected budget') }} · {{ data.year }}</h2>
        <p class="muted">{{ tr('Registra el presupuesto previsto para el año. Este importe no aumenta el dinero disponible.', 'Record the planned budget for the year. This amount does not increase available money.') }}</p>
        <form class="toolbar" @submit.prevent="saveExpected(selected)">
          <label>{{ tr('Esperado anual', 'Yearly expected') }}<input :value="expectedDrafts[selected.id] ?? (selected.expected_cents == null ? '' : (selected.expected_cents / 100).toFixed(2))" @input="expectedDrafts[selected.id] = $event.target.value" class="form-control" type="number" min="0" max="999999999.99" step="0.01" required :disabled="busy" /></label>
          <button class="btn btn-primary" :disabled="busy || loading">{{ tr('Guardar esperado', 'Save expected budget') }}</button>
        </form>
      </section>
      <section v-if="selected && !selected.budget" class="surface">
        <h2>{{ tr('Configura el saldo inicial', 'Set your starting balance') }}</h2>
        <p>{{ tr('Este departamento aún no tiene una asignación inicial de tesorería. Registra el importe acordado; solo tendrás que hacerlo una vez.', 'This department has no treasury starting allocation yet. Enter the agreed amount; you only need to do this once.') }}</p>
        <form v-if="canWrite && data.can_self_fund" class="toolbar" @submit.prevent="start"><label>{{ tr('Saldo inicial', 'Starting balance') }}<input v-model="opening" class="form-control" type="number" min="0" max="999999999.99" step="0.01" required /></label><button class="btn btn-primary" :disabled="busy || loading">{{ tr('Guardar saldo inicial', 'Save starting balance') }}</button></form>
        <p v-else class="muted">{{ tr('Tesorería debe registrar el saldo inicial. Después podrás registrar gastos.', 'Treasury must set the starting balance. You can then record expenses.') }}</p>
      </section>
      <FormSection :busy="busy" v-if="canWrite && selected?.budget" :key="selected.id" class="surface" :title="tr('Registrar movimiento', 'Record a transaction')">

        <form class="entry-form" @submit.prevent="addEntry">
          <label>{{ tr('Tipo', 'Type') }}<select v-model="entry.kind" class="form-select"><option value="expense">{{ tr('Gasto', 'Expense') }}</option><option v-if="data.can_self_fund" value="income">{{ tr('Ingreso', 'Income') }}</option></select></label>
          <label>{{ tr('Fecha', 'Date') }}<input v-model="entry.occurred_on" type="date" class="form-control" required /></label>
          <label>{{ tr('Importe', 'Amount') }}<input v-model="entry.amount" type="number" min="0.01" max="999999999.99" step="0.01" class="form-control" required /></label>
          <label class="description">{{ tr('Concepto', 'Description') }}<input v-model="entry.description" maxlength="255" class="form-control" required /></label>
          <fieldset v-if="entry.kind === 'expense'" class="asset-fields" :disabled="busy">
            <legend>{{ tr('¿Este gasto incluye un bien para inventario?', 'Does this expense include an inventory item?') }}</legend>
            <select v-model="assetChoice" class="form-select" required :aria-label="tr('Registrar en inventario', 'Add to inventory')">
              <option value="" disabled>{{ tr('Selecciona una opción', 'Choose an option') }}</option>
              <option value="no">{{ tr('No · Servicio u otro gasto', 'No · Service or other expense') }}</option>
              <option v-if="auth.canEdit('inventory')" value="yes">{{ tr('Sí · Registrar el bien en inventario', 'Yes · Add the item to inventory') }}</option>
            </select>
            <div v-if="assetChoice === 'yes'" class="asset-grid">
              <label>{{ tr('Nombre del bien', 'Item name') }}<input v-model="asset.description" class="form-control" maxlength="255" required /></label>
              <label>{{ tr('Cantidad', 'Quantity') }}<input v-model="asset.quantity" type="number" min="1" max="100000" step="1" class="form-control" required /></label>
              <label>{{ tr('Valor unitario', 'Unit value') }}<input v-model="asset.value" type="number" min="0.01" max="999999999.99" step="0.01" class="form-control" required /></label>
              <label>{{ tr('Marca', 'Brand') }}<input v-model="asset.brand" maxlength="255" class="form-control" /></label>
              <label>{{ tr('Modelo', 'Model') }}<input v-model="asset.model" maxlength="255" class="form-control" /></label>
              <label>{{ tr('Serie', 'Serial number') }}<input v-model="asset.serial_number" maxlength="255" class="form-control" /></label>
              <label>{{ tr('Ubicación', 'Location') }}<input v-model="asset.location" maxlength="2000" class="form-control" /></label>
              <p>{{ tr('Valor a inventariar', 'Inventory value') }}: {{ money(Math.round(Number(asset.value || 0) * 100) * Number(asset.quantity || 0)) }}. {{ tr('Se guarda junto con el gasto, sin descontar el importe dos veces.', 'Saved together with the expense, without deducting the amount twice.') }}</p>
            </div>
          </fieldset>
          <button class="btn btn-primary" :disabled="busy || loading">{{ tr('Registrar', 'Record') }}</button>
        </form>
        <small class="muted">{{ tr('Registra cada ingreso una sola vez. Las asignaciones publicadas por tesorería aparecen automáticamente.', 'Record each income once. Published treasury allocations appear automatically.') }}</small>
      </FormSection>
      <section v-if="data.can_publish" class="surface">
        <button class="section-toggle" :aria-expanded="publishOpen" @click="publishOpen = !publishOpen">{{ tr('Carga masiva de asignaciones · CSV', 'Bulk allocations · CSV') }} <span>{{ publishOpen ? '−' : '+' }}</span></button>
        <form v-if="publishOpen" @submit.prevent="publish">
          <p class="muted">{{ tr('Ingresa importes por departamento o carga la plantilla CSV. Revisa la tabla antes de publicar.', 'Enter amounts per department or upload the CSV template. Review the table before publishing.') }}</p>
          <div class="entry-form">
            <label>{{ tr('Asignación', 'Allocation') }}<select v-model="allocation.kind" class="form-select"><option value="opening">{{ tr('Saldo inicial', 'Starting balance') }}</option><option value="income">{{ tr('Ingreso adicional', 'Additional income') }}</option></select></label>
            <label>{{ tr('Fecha', 'Date') }}<input v-model="allocation.occurred_on" type="date" class="form-control" required /></label>
            <label class="description">{{ tr('Concepto', 'Description') }}<input v-model="allocation.description" maxlength="255" class="form-control" required /></label>
          </div>
          <div class="toolbar file-tools"><button type="button" class="btn btn-outline-secondary" @click="downloadTemplate">{{ tr('Descargar plantilla CSV', 'Download CSV template') }}</button><label>{{ tr('Cargar CSV', 'Upload CSV') }}<input type="file" accept=".csv,text/csv" :disabled="busy" @change="previewCsv" /></label></div>
          <p class="notice">{{ allocation.kind === 'opening' ? tr('Inicio de año: registra el esperado anual y el saldo inicial real juntos. El esperado no agrega dinero; el saldo inicial aplica solo su diferencia y conserva los movimientos existentes.', 'Year start: record the yearly expectation and actual starting balance together. Expected amounts do not add money; the starting balance applies only its difference and preserves existing transactions.') : tr('Durante el año: agrega únicamente dinero nuevo. No modifica el esperado anual ni el saldo inicial.', 'During the year: add new money only. Does not change the yearly expectation or starting balance.') }}</p>
          <div class="table-responsive"><table class="table" data-dt="off"><thead><tr><th>{{ tr('Departamento', 'Department') }}</th><th>{{ tr('En existencia', 'In existence') }}</th><th v-if="allocation.kind === 'opening'">{{ tr('Esperado anual', 'Yearly expected') }} · {{ data.year }}</th><th>{{ allocation.kind === 'opening' ? tr('Saldo inicial real', 'Actual starting balance') : tr('Nuevo ingreso', 'New income') }}</th></tr></thead><tbody><tr v-for="department in data.departments" :key="department.id"><td>{{ department.name }}</td><td>{{ department.budget ? money(department.budget.balance_cents) : '—' }}</td><td v-if="allocation.kind === 'opening'"><input v-model="bulkExpected[department.id]" :aria-label="tr('Esperado: ', 'Expected: ') + department.name" type="number" min="0" max="999999999.99" step="0.01" :required="amounts[department.id] !== undefined && amounts[department.id] !== ''" class="form-control" /></td><td><input v-model="amounts[department.id]" :aria-label="department.name" type="number" :min="allocation.kind === 'income' ? 0.01 : 0" max="999999999.99" step="0.01" :required="allocation.kind === 'opening' && bulkExpected[department.id] !== undefined && bulkExpected[department.id] !== ''" class="form-control" /></td></tr></tbody></table></div>
          <div class="toolbar"><span>{{ tr('Total a publicar', 'Total to publish') }}: <strong>{{ money(allocationTotal) }}</strong></span><button class="btn btn-primary" :disabled="busy || loading || !allocationRows.length">{{ tr('Publicar asignaciones', 'Publish allocations') }}</button></div>
        </form>
      </section>
      <section v-if="data.can_view_all && !data.can_publish" class="surface">
        <h2>{{ tr('Presupuesto de toda la iglesia', 'Church-wide budget') }}</h2>
        <p class="muted">{{ tr('Vista de consulta. Selecciona un departamento para revisar sus movimientos.', 'Read-only overview. Select a department to review its transactions.') }}</p>
        <div class="table-responsive"><table class="table" data-dt="off"><thead><tr><th>{{ tr('Departamento', 'Department') }}</th><th>{{ tr('Esperado anual', 'Yearly expected') }} · {{ data.year }}</th><th>{{ tr('Saldo inicial', 'Starting balance') }}</th><th>{{ tr('Ingresos', 'Income') }}</th><th>{{ tr('Gastos', 'Expenses') }}</th><th>{{ tr('Disponible', 'Available') }}</th></tr></thead><tbody><tr v-for="department in data.departments" :key="department.id"><td><button class="department-link" @click="viewRecords(department)"><i :style="{ background: department.color || '#89958d' }"></i>{{ department.name }}</button></td><td>{{ department.expected_cents == null ? '—' : money(department.expected_cents) }}</td><template v-if="department.budget"><td>{{ money(department.budget.opening_cents) }}</td><td>{{ money(department.budget.income_cents) }}</td><td>{{ money(department.budget.expense_cents) }}</td><td>{{ money(department.budget.balance_cents) }}</td></template><td v-else colspan="4" class="muted">{{ tr('Sin saldo inicial', 'No starting balance') }}</td></tr></tbody></table></div>
      </section>
      <section ref="ledgerElement" class="surface ledger">
        <h2>{{ selected ? tr('Movimientos de ', 'Transactions for ') + selected.name : data.can_view_all ? tr('Movimientos de todos los departamentos', 'All department transactions') : tr('Resumen por departamento', 'Department overview') }}</h2>
        <p v-if="selected && !canWrite" class="muted">{{ tr('Solo consulta: puedes analizar los ingresos y gastos, sin editar los registros.', 'Read only: review income and expenses without editing records.') }}</p>
        <p v-if="selected?.budget" class="muted">{{ tr('Saldo inicial registrado por', 'Starting balance recorded by') }} {{ selected.budget.opening_source === 'treasury' ? tr('tesorería', 'treasury') : tr('el departamento', 'the department') }}.</p>
        <div v-if="selected?.budget || data.can_view_all" class="toolbar ledger-filters">
          <label>{{ tr('Tipo de movimiento', 'Transaction type') }}<select v-model="recordKind" class="form-select"><option value="all">{{ tr('Todos', 'All') }}</option><option value="expense">{{ tr('Gastos', 'Expenses') }}</option><option value="income">{{ tr('Ingresos', 'Income') }}</option><option value="opening">{{ tr('Saldo inicial y ajustes', 'Starting balance and adjustments') }}</option></select></label>
          <label>{{ tr('Desde', 'From') }}<input v-model="recordFrom" type="date" class="form-control" /></label>
          <label>{{ tr('Hasta', 'To') }}<input v-model="recordTo" type="date" class="form-control" /></label>
          <button type="button" class="btn btn-outline-secondary" :disabled="loading || busy || !!error || !visibleEntries.length" @click="exportMovementsCsv">{{ tr('Exportar CSV', 'Export CSV') }}</button>
          <button type="button" class="btn btn-outline-secondary" :disabled="loading || busy || !!error || !visibleEntries.length" @click="exportMovementsPdf">{{ tr('Exportar PDF', 'Export PDF') }}</button>
        </div>
        <div class="table-responsive">
        <table v-if="visibleEntries.length" class="table" data-dt="off"><thead><tr><th v-if="!selected">{{ tr('Departamento', 'Department') }}</th><th>{{ tr('Fecha', 'Date') }}</th><th>{{ tr('Concepto', 'Description') }}</th><th>{{ tr('Tipo / origen', 'Type / source') }}</th><th>{{ tr('Registrado por', 'Recorded by') }}</th><th>{{ tr('Importe', 'Amount') }}</th></tr></thead><tbody><tr v-for="row in visibleEntries" :key="row.id"><td v-if="!selected">{{ row.department_name }}</td><td>{{ row.occurred_on }}</td><td>{{ row.description }}<small v-if="row.inventory_item" class="asset-record">{{ tr('Registrado en inventario', 'Added to inventory') }}: {{ row.inventory_item.description }} · {{ row.inventory_item.quantity }} {{ tr('unidades', 'units') }}</small></td><td><span class="kind" :class="row.kind">{{ kindLabel(row.kind) }}</span><small v-if="row.budget_publication_id"> · {{ tr('Tesorería', 'Treasury') }}</small></td><td>{{ row.author?.name || '—' }}</td><td>{{ row.kind === 'expense' ? '−' : row.kind === 'income' ? '+' : '' }}{{ money(row.amount_cents) }}</td></tr></tbody></table><p v-else-if="selected || data.can_view_all" class="muted">{{ tr('No hay movimientos para mostrar con estos filtros.', 'No transactions to show with these filters.') }}</p></div>
      </section>
    </template>
    <div v-if="funding" class="modal-backdrop" @click.self="!busy && (funding = null)">
      <section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="funding-title">
        <header><h2 id="funding-title">{{ funding.kind === 'income' ? tr('Agregar fondos', 'Add funds') : tr('Editar saldo inicial', 'Edit starting balance') }} · {{ funding.department.name }}</h2><button class="btn btn-outline-secondary" :disabled="busy" @click="funding = null">{{ tr('Cerrar', 'Close') }}</button></header>
        <p v-if="funding.kind === 'opening'" class="notice warning">{{ tr('Usa esta opción solo para corregir el saldo inicial. Los ingresos nuevos se registran con Agregar fondos. Los gastos e ingresos existentes se conservarán.', 'Use this only to correct the starting balance. Record new income with Add funds. Existing expenses and income are preserved.') }}</p>
        <form @submit.prevent="saveFunding" class="funding-form"><label>{{ tr('Importe', 'Amount') }}<input v-model="funding.amount" class="form-control" type="number" :min="funding.kind === 'income' ? 0.01 : 0" max="999999999.99" step="0.01" required /></label><label>{{ tr('Fecha', 'Date') }}<input v-model="funding.occurred_on" type="date" class="form-control" required /></label><label>{{ tr('Concepto', 'Description') }}<input v-model="funding.description" maxlength="255" class="form-control" required /></label><p v-if="error" class="notice error" role="alert">{{ error }}</p><button class="btn btn-primary" :disabled="busy || loading">{{ tr('Guardar', 'Save') }}</button></form>
      </section>
    </div>
  </section>
</template>

<script setup>
import FormSection from "./FormSection.vue"
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useAuthStore } from '../stores/authStore'
import { useUiStore } from '../stores/uiStore'
import { budgetApi } from '../services/budgetApi'
import jsPDF from 'jspdf'
import 'jspdf-autotable'
const auth = useAuthStore(), ui = useUiStore()
const tr = (es, en) => ui.locale === 'es' ? es : en
const money = cents => new Intl.NumberFormat(ui.locale === 'es' ? 'es-US' : 'en-US', { style: 'currency', currency: 'USD' }).format((cents || 0) / 100)
const today = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}` }
const data = ref(null), loading = ref(false), busy = ref(false), error = ref(''), success = ref(''), period = ref(''), departmentId = ref(String(auth.user?.department_id || '')), opening = ref(''), publishOpen = ref(false)
const ledgerElement = ref(null), assetChoice = ref('')
const asset = reactive({ description: '', quantity: 1, value: '', brand: '', model: '', serial_number: '', location: '' })
async function viewRecords(department) { departmentId.value = String(department.id); await nextTick(); ledgerElement.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }) }
const planYear = ref(new Date().getFullYear()), expectedDrafts = reactive({}), openingDrafts = reactive({}), funding = ref(null)
const entry = reactive({ kind: 'expense', occurred_on: today(), amount: '', description: '' })
const allocation = reactive({ kind: 'opening', occurred_on: today(), description: '' }), amounts = reactive({}), bulkExpected = reactive({})
const entryRequestId = ref(crypto.randomUUID()), publishRequestId = ref(crypto.randomUUID())
const recordKind = ref('all'), recordFrom = ref(''), recordTo = ref('')
const visibleEntries = computed(() => {
  // Only use departments supplied by the permission-scoped budget endpoint.
  const departments = selected.value ? [selected.value] : data.value?.can_view_all ? data.value.departments : []
  return departments.flatMap(department => (department.budget?.entries || []).map(row => ({ ...row, department_name: department.name })))
    .filter(row => (recordKind.value === 'all' || (recordKind.value === 'opening' ? ['opening', 'opening_update'].includes(row.kind) : row.kind === recordKind.value)) && (!recordFrom.value || row.occurred_on >= recordFrom.value) && (!recordTo.value || row.occurred_on <= recordTo.value))
    .sort((a, b) => b.occurred_on.localeCompare(a.occurred_on) || b.id - a.id)
})
const selected = computed(() => data.value?.departments.find(d => String(d.id) === departmentId.value))
const canWrite = computed(() => auth.canEdit('budget') && selected.value && data.value?.writable_department_id === selected.value.id)
const expectedTotal = computed(() => (selected.value ? [selected.value] : data.value?.departments || []).reduce((sum, d) => sum + (d.expected_cents || 0), 0))
const totals = computed(() => (selected.value ? [selected.value] : data.value?.departments || []).reduce((sum, d) => { for (const key of Object.keys(sum)) sum[key] += d.budget?.[key] || 0; return sum }, { opening_cents: 0, income_cents: 0, expense_cents: 0, balance_cents: 0 }))
const allocationRows = computed(() => Object.entries(amounts).filter(([,value]) => value !== '' && value !== null).map(([department_id, amount]) => ({ department_id: Number(department_id), amount, ...(allocation.kind === 'opening' ? { expected_amount: bulkExpected[department_id] ?? '' } : {}) })))
const allocationTotal = computed(() => allocationRows.value.reduce((sum, row) => sum + Math.round(Number(row.amount) * 100), 0))
const kindLabel = kind => ({ opening: tr('Saldo inicial', 'Starting balance'), opening_update: tr('Actualización del saldo inicial', 'Starting balance update'), income: tr('Ingreso', 'Income'), expense: tr('Gasto', 'Expense') }[kind] || kind)
const movementExport = () => ({
  headers: [tr('Departamento', 'Department'), tr('Fecha', 'Date'), tr('Concepto', 'Description'), tr('Tipo', 'Type'), tr('Origen', 'Source'), tr('Registrado por', 'Recorded by'), tr('Importe (USD)', 'Amount (USD)'), tr('Bien en inventario', 'Inventory item'), tr('Cantidad', 'Quantity')],
  rows: visibleEntries.value.map(row => [
    row.department_name, row.occurred_on, row.description, kindLabel(row.kind),
    row.budget_publication_id ? tr('Tesorería', 'Treasury') : tr('Departamento', 'Department'),
    row.author?.name || '', (row.kind === 'expense' ? -row.amount_cents : row.amount_cents) / 100,
    row.inventory_item?.description || '', row.inventory_item?.quantity ?? '',
  ]),
})
const movementFilename = extension => `budget-movements-${selected.value?.id || 'all-departments'}-${today()}.${extension}`
function exportMovementsCsv() {
  if (!visibleEntries.value.length) return
  const { headers, rows } = movementExport()
  const cell = value => {
    let text = typeof value === 'number' ? String(value) : String(value ?? '')
    if (typeof value === 'string' && /^[\s]*[=+@-]/.test(text)) text = "'" + text
    return '"' + text.replaceAll('"', '""') + '"'
  }
  const csv = [headers, ...rows].map(row => row.map(cell).join(',')).join('\r\n')
  const url = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = movementFilename('csv')
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
function exportMovementsPdf() {
  if (!visibleEntries.value.length) return
  const { headers, rows } = movementExport()
  const doc = new jsPDF({ orientation: 'landscape' })
  const width = doc.internal.pageSize.getWidth() - 28
  doc.setFontSize(14)
  const title = doc.splitTextToSize(`${tr('Movimientos', 'Transactions')} · ${selected.value?.name || tr('Todos los departamentos', 'All departments')}`, width)
  doc.text(title, 14, 14)
  doc.setFontSize(9)
  const kind = recordKind.value === 'all' ? tr('Todos', 'All') : recordKind.value === 'opening' ? tr('Saldo inicial y ajustes', 'Starting balance and adjustments') : kindLabel(recordKind.value)
  const context = doc.splitTextToSize(`${auth.user?.church?.name || ''} | ${kind} | ${recordFrom.value || tr('Desde el inicio', 'From the beginning')} – ${recordTo.value || tr('Sin fecha final', 'No end date')} | USD`, width)
  const contextY = 14 + title.length * 6
  doc.text(context, 14, contextY)
  doc.autoTable({ startY: contextY + context.length * 4 + 4, head: [headers], body: rows.map(row => row.map((value, index) => index === 6 ? Number(value).toFixed(2) : value)), styles: { fontSize: 8, overflow: 'linebreak' }, headStyles: { fillColor: [31, 111, 92] }, columnStyles: { 6: { halign: 'right' } }, margin: { bottom: 16 } })
  const pages = doc.getNumberOfPages()
  for (let page = 1; page <= pages; page++) {
    doc.setPage(page)
    doc.setFontSize(8)
    doc.text(`${page} / ${pages}`, doc.internal.pageSize.getWidth() - 24, doc.internal.pageSize.getHeight() - 8)
  }
  doc.save(movementFilename('pdf'))
}
const message = e => Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || tr('No se pudo guardar o cargar el presupuesto.', 'Could not save or load the budget.')
let generation = 0, timer
async function load() {
  const current = ++generation
  loading.value = true
  try { const result = await budgetApi.overview({ year: planYear.value }); if (current !== generation) return; if (!data.value && result.can_view_all) departmentId.value = ''; data.value = result; if (!result.can_self_fund) entry.kind = 'expense'; if (!period.value) period.value = result.period; error.value = '' }
  catch(e) { if (current === generation) error.value = message(e) }
  finally { if (current === generation) loading.value = false }
}
async function save(action) { if (busy.value) return; busy.value = true; error.value = ''; success.value = ''; try { await action(); success.value = tr('Guardado correctamente.', 'Saved successfully.'); await load() } catch(e) { error.value = message(e) } finally { busy.value = false } }
const updateSettings = value => save(() => budgetApi.settings({ self_management: value }))
const saveExpected = department => save(async () => {
  await budgetApi.plan({ department_id: department.id, year: data.value.year, amount: expectedDrafts[department.id] ?? (department.expected_cents / 100).toFixed(2) })
  delete expectedDrafts[department.id]
})
const saveOpening = department => save(async () => {
  await budgetApi.publish({ kind: 'opening', description: tr('Saldo inicial', 'Starting balance'), occurred_on: today(), allocations: [{ department_id: department.id, amount: openingDrafts[department.id] }], request_id: crypto.randomUUID() })
  delete openingDrafts[department.id]
})
function openFunding(department, kind) {
  error.value = ''
  funding.value = { department, kind, amount: kind === 'opening' ? (department.budget.opening_cents / 100).toFixed(2) : '', occurred_on: today(), description: '', request_id: crypto.randomUUID() }
}
const saveFunding = () => save(async () => {
  const { department, kind, amount, description, occurred_on, request_id } = funding.value
  await budgetApi.publish({ kind, description, occurred_on, request_id, allocations: [{ department_id: department.id, amount }] })
  funding.value = null
})
watch(planYear, value => { if (Number.isInteger(value) && value >= 2000 && value <= 2100) { Object.keys(expectedDrafts).forEach(key => delete expectedDrafts[key]); Object.keys(amounts).forEach(key => delete amounts[key]); Object.keys(bulkExpected).forEach(key => delete bulkExpected[key]); publishRequestId.value = crypto.randomUUID(); load() } })
watch(() => funding.value ? [funding.value.amount, funding.value.description, funding.value.occurred_on] : null, () => { if (funding.value && !busy.value) funding.value.request_id = crypto.randomUUID() })
const start = () => save(() => budgetApi.start({ period: period.value, amount: opening.value }))
const addEntry = () => save(async () => { await budgetApi.addEntry({ ...entry, inventory: entry.kind === 'expense' && assetChoice.value === 'yes' ? { ...asset } : null, period: period.value, request_id: entryRequestId.value }); entry.amount = ''; entry.description = ''; assetChoice.value = ''; Object.assign(asset, { description: '', quantity: 1, value: '', brand: '', model: '', serial_number: '', location: '' }); entryRequestId.value = crypto.randomUUID() })
const publish = () => save(async () => { await budgetApi.publish({ ...allocation, bulk: true, year: data.value.year, period: period.value, allocations: allocationRows.value, request_id: publishRequestId.value }); Object.keys(amounts).forEach(key => delete amounts[key]); Object.keys(bulkExpected).forEach(key => delete bulkExpected[key]); allocation.description = ''; publishRequestId.value = crypto.randomUUID() })
async function previewCsv(event) { const file = event.target.files[0]; if (!file) return; busy.value = true; error.value = ''; try { const result = await budgetApi.preview(file, { period: period.value, kind: allocation.kind }); Object.keys(amounts).forEach(key => delete amounts[key]); Object.keys(bulkExpected).forEach(key => delete bulkExpected[key]); result.allocations.forEach(row => { amounts[row.department_id] = row.amount; if (row.expected_amount !== undefined) bulkExpected[row.department_id] = row.expected_amount }); publishOpen.value = true } catch(e) { error.value = message(e) } finally { busy.value = false; event.target.value = '' } }
function downloadTemplate() { const rows = [allocation.kind === 'opening' ? 'department_id,department,expected_amount,amount' : 'department_id,department,amount', ...data.value.departments.map(d => `${d.id},"${d.name.replaceAll('"','""')}",${allocation.kind === 'opening' ? ',' : ''}`)]; const url = URL.createObjectURL(new Blob(['\uFEFF' + rows.join('\r\n')], {type:'text/csv;charset=utf-8'})); const a = document.createElement('a'); a.href = url; a.download = `budget-${allocation.kind}-${data.value.year}.csv`; a.click(); URL.revokeObjectURL(url) }
watch(() => allocation.kind, () => { Object.keys(amounts).forEach(key => delete amounts[key]); Object.keys(bulkExpected).forEach(key => delete bulkExpected[key]); publishRequestId.value = crypto.randomUUID() })
watch(assetChoice, value => { if (value === 'yes') { if (!asset.description) asset.description = entry.description; if (asset.value === '') asset.value = entry.amount } })
watch([asset, assetChoice], () => { if (!busy.value) entryRequestId.value = crypto.randomUUID() }, { deep: true, flush: 'sync' })
watch(entry, () => { if (!busy.value) entryRequestId.value = crypto.randomUUID() }, { deep: true, flush: 'sync' })
watch([allocation, amounts, bulkExpected], () => { if (!busy.value) publishRequestId.value = crypto.randomUUID() }, { deep: true, flush: 'sync' })
watch(period, (value, previous) => { if (previous && value !== previous) { data.value = null; Object.keys(amounts).forEach(key => delete amounts[key]); Object.keys(bulkExpected).forEach(key => delete bulkExpected[key]); publishRequestId.value = crypto.randomUUID(); entryRequestId.value = crypto.randomUUID(); load() } })
onMounted(() => { load(); timer = setInterval(() => { if (!busy.value && !loading.value && document.visibilityState === 'visible') load() }, 30000) })
onUnmounted(() => { generation++; clearInterval(timer) })
</script>

<style scoped>
.budget-page { display:grid; gap:20px; font-size:13px; }
header,.toolbar { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
header h1 {font-size:20px;font-weight:500;margin:0 0 8px} header p {margin:0;color:#68766e} .eyebrow {font-size:12px;color:#28705b!important;margin-bottom:6px!important}
h2 {font-size:15px;font-weight:500;margin:0 0 14px}.surface {padding:20px;background:white;border:1px solid #dfe5df;border-radius:10px}.summary {display:grid;grid-template-columns:repeat(5,1fr);gap:12px}.summary article {background:white;border:1px solid #dfe5df;border-radius:9px;padding:18px;display:grid;gap:10px}.summary span,.muted {color:#68766e}.summary strong {font-weight:500;font-size:21px;color:#254b3d}.summary .negative strong {color:#a24035}.btn {width:auto;font-size:12px}.btn-primary {background:#1f6f5c;border-color:#1f6f5c}.toolbar {justify-content:flex-start;align-items:end}.toolbar label {min-width:180px}.entry-form {display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:14px}.entry-form label {flex:1;min-width:120px}.entry-form .description {flex:2}.form-control,.form-select {margin-top:6px}label {font-size:12px;margin:0}.notice {padding:14px;border-radius:7px;background:#eaf3ed;margin:0}.error {background:#fff1ef;color:#8a3931}.warning {background:#fff7e5;color:#7c6128}.section-toggle {display:flex;justify-content:space-between;background:none;border:0;padding:0;color:#254b3d;font-size:15px;width:100%;margin-bottom:14px}.file-tools {margin:18px 0}.table {font-size:12px;margin:14px 0}.table th {font-weight:500;color:#596b61;background:#f6f8f7}.table td,.table th {padding:12px;vertical-align:middle}.table input {max-width:180px}.department-link {display:flex;gap:8px;align-items:center;background:none;border:0;padding:0;color:#28705b;font-size:12px;text-align:left;width:auto}.department-link i {width:8px;height:8px;border-radius:50%}.kind {border-radius:12px;padding:4px 8px;background:#edf1ee}.kind.expense {color:#8a443e;background:#f8e9e6}.kind.income {color:#246046;background:#e6f1e9}@media(max-width:767px){.summary{grid-template-columns:repeat(2,1fr)}.entry-form{display:grid;grid-template-columns:1fr}.toolbar label{width:100%}.summary strong{font-size:18px}}

.self-management { display:flex;align-items:center;gap:10px;font-size:13px; }
.self-management input { width:auto;margin:0;flex:0 0 auto; }
.treasury-settings p { margin:10px 0 0; }
.inline-amount { display:flex;align-items:center;gap:6px;padding:0;border:0;background:none;box-shadow:none;margin:0; }
.inline-amount input { min-width:95px;max-width:135px;margin:0; }
.opening-edit { margin-left:8px; }
.cash-balance { white-space:nowrap;font-weight:500;color:#254b3d; }
.funding-form { display:grid;gap:14px; }
.summary small { font-size:11px; }

.asset-fields { flex-basis:100%;border:1px solid #dfe5df;border-radius:8px;padding:14px;margin:0; }
.asset-fields legend { float:none;width:auto;font-size:13px;margin:0 0 8px; }
.asset-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:14px; }
.asset-grid p { grid-column:1/-1;color:#68766e;margin:0; }
.asset-record { display:block;color:#28705b;margin-top:4px; }
</style>
