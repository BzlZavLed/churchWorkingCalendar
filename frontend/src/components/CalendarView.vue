<template>
  <section class="calendar-page container-xl">
    <div v-for="hold in expiringOwnHolds" :key="hold.id" class="alert alert-warning" role="alert" aria-live="assertive">
      {{ locale === 'es' ? 'Tu reserva está por vencer.' : 'Your reservation is about to expire.' }}
      <button class="btn btn-warning ms-2" :disabled="extendingHold" @click="extendReservation(hold)">
        {{ locale === 'es' ? 'Extender 5 minutos' : 'Extend 5 minutes' }}
      </button>
      <button class="btn btn-outline-secondary ms-2" @click="dismissedHoldWarnings.add(hold.id)">
        {{ locale === 'es' ? 'Dejar vencer' : 'Let expire' }}
      </button>
      <p v-if="holdExtensionError">{{ holdExtensionError }}</p>
    </div>
    <header class="calendar-header">
      <div class="row">
        <div class="col-12 col-lg-4">
          <h1 class="mb-1">{{ t.title }}</h1>
          <p v-if="user?.church" class="small mb-2">
            <strong>{{ canReview ? (locale === 'es' ? 'Vista de secretaría' : 'Clerk view') : (locale === 'es' ? 'Vista de departamento' : 'Department view') }}</strong>
            · {{ user.church.name }}<span v-if="!canReview && user.department"> · {{ user.department.name }}</span>
          </p>
          <button
            v-if="calendarFeedUrl"
            type="button"
            class="calendar-feed-link"
            @click="copyCalendarFeedUrl"
          >
            {{ t.feedLinkLabel }}
          </button>
        </div>
      </div>
      <div class="row" id="filters">
        <label v-if="canReview" class="col-3">
          <span class="form-label small mb-1">{{ t.filterDepartment }}</span>
          <select v-model="departmentFilter" class="form-select">
            <option value="all">{{ t.allDepartments }}</option>
            <option v-for="dept in departments" :key="dept.id" :value="String(dept.id)">
              {{ dept.name }}
            </option>
          </select>
        </label>
        <label v-if="showMonthDropdown" class="col-3">
          <span class="form-label small mb-1">{{ t.monthSelect }}</span>
          <select v-model="monthSelection" class="form-select" @change="applyMonthSelection">
            <option v-for="option in monthOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </label>
        <div class="col-3">
          <div class="calendar-export d-flex flex-column align-items-start gap-2">
            <span class="export-label">{{ t.exportLabel }}</span>
            <div class="calendar-export-row d-flex flex-wrap align-items-center gap-2">
              <div class="calendar-export-buttons d-flex gap-2 align-items-center">
                <button
                  type="button"
                  class="btn btn-outline-secondary icon-button"
                  :title="t.exportCalendar"
                  :aria-label="t.exportCalendar"
                  @click="exportPdf('calendar')"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" class="icon">
                    <rect x="3" y="4" width="18" height="17" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2" />
                    <line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2" />
                    <line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2" />
                    <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2" />
                  </svg>
                </button>
                <button
                  type="button"
                  class="btn btn-outline-secondary icon-button"
                  :title="t.exportList"
                  :aria-label="t.exportList"
                  @click="exportPdf('list')"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" class="icon">
                    <rect x="5" y="4" width="14" height="16" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2" />
                    <line x1="8" y1="8" x2="16" y2="8" stroke="currentColor" stroke-width="2" />
                    <line x1="8" y1="12" x2="16" y2="12" stroke="currentColor" stroke-width="2" />
                    <line x1="8" y1="16" x2="14" y2="16" stroke="currentColor" stroke-width="2" />
                  </svg>
                </button>
              </div>
              <label class="export-toggle">
                <input v-model="includeHistoryExport" type="checkbox" />
                <span>{{ t.exportIncludeHistory }}</span>
              </label>
            </div>
          </div>
        </div>
        <div v-if="showMonthDropdown && !isDayView" class="col-12">
          <div class="month-meta">
            <span>{{ t.monthSelected }}: {{ monthLabel }}</span>
          </div>
        </div>
      </div>
      <aside v-if="canViewUnseenNotes && unseenCount" class="unread-notes-card" aria-labelledby="unread-notes-title">
        <div class="unread-notes-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-3 3V11.5A7.5 7.5 0 0 1 9.5 4H12" />
            <path d="M7 10h6M7 14h9" />
            <circle cx="19" cy="5" r="3" fill="currentColor" stroke="none" />
          </svg>
        </div>
        <div class="unread-notes-copy">
          <div class="unread-notes-heading">
            <h2 id="unread-notes-title">{{ t.unseenNotesAlert }}</h2>
            <span class="unread-notes-count" role="status" aria-live="polite" aria-atomic="true">
              {{ unseenCount }} {{ locale === 'es' ? (unseenCount === 1 ? 'nueva' : 'nuevas') : (unseenCount === 1 ? 'new note' : 'new notes') }}
            </span>
          </div>
          <p>{{ locale === 'es' ? 'Secretaría dejó comentarios en tus eventos. Revísalos para mantenerte al día.' : 'The clerk left comments on your events. Review them to stay up to date.' }}</p>
        </div>
        <button type="button" class="unread-notes-action" @click="openUnseenNotesModal">
          {{ t.unseenNotesButton }}
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-5-5 5 5-5 5" /></svg>
        </button>
      </aside>
      <div class="row">
        <div class="col-12 d-flex justify-content-center">
          <div class="calendar-actions d-flex flex-nowrap align-items-center gap-2">
            <button v-if="isDayView" type="button" class="btn btn-outline-secondary calendar-nav-btn" @click="exitDayView">
              {{ t.back }}
            </button>
            <template v-if="!isDayView">
              <button type="button" class="btn btn-outline-secondary calendar-nav-btn" @click="goPrevMonth">
                {{ t.prev }}
              </button>
              <span class="calendar-label fw-semibold text-center">
                <span v-if="!isMobile">{{ monthLabel }}</span>
                <span v-else>{{ monthLabel }} - {{ weekLabel }}</span>
              </span>
              <button type="button" class="btn btn-outline-secondary calendar-nav-btn" @click="goNextMonth">
                {{ t.next }}
              </button>
            </template>
          </div>
        </div>
      </div>
    </header>

    

    <div v-if="!isDayView" class="calendar-grid" :class="{ week: isMobile }">
      <template v-if="!isMobile">
        <div v-for="day in weekDays" :key="day" class="calendar-cell header">{{ day }}</div>

        <div
          v-for="(cell, index) in calendarCells"
          :key="index"
          class="calendar-cell"
          :class="{ empty: !cell.date, selected: isSelected(cell.date), today: isToday(cell.date) }"
          @click="isSecretary && cell.date && enterDayView(cell.date)"
        >
          <div v-if="cell.date" class="cell-inner">
            <div class="cell-date">
              <span class="cell-date-label" :class="{ 'is-today': isToday(cell.date) }">
                {{ cell.date.getDate() }}
              </span>
              <div class="cell-actions">
                <button type="button" class="day-link" @click.stop="enterDayView(cell.date)">
                  {{ t.dayView }}
                </button>
                <button v-if="canCreateEvents" type="button" class="day-add" @click.stop="enterDayView(cell.date)">
                  +
                </button>
              </div>
            </div>
            <ul class="cell-events">
              <li
                v-for="event in eventsForDate(cell.date).slice(0, 2)"
                :key="event.id"
              >
                <button
                  type="button"
                  class="event-pill event-pill-button"
                  :class="{ 'event-pill--expiring': isExpiringHold(event) }"
                  :style="eventPillStyle(event)"
                  :title="eventMarkerDetails(event)"
                  @click.stop="openEventDetails(event)"
                >
                  <span class="event-status-dot" :class="statusDotClass(event)"></span>
                  {{ eventMarkerLabel(event) }}
                </button>
              </li>
            </ul>
            <button
              v-if="eventsForDate(cell.date).length > 2"
              type="button"
              class="event-more day-link"
              @click.stop="enterDayView(cell.date)"
            >
              {{ t.viewMore }}
            </button>
          </div>
        </div>
      </template>

      <template v-else>
        <div
          v-for="cell in weekCells"
          :key="cell.date.toISOString()"
          class="calendar-cell"
          :class="{ selected: isSelected(cell.date), today: isToday(cell.date) }"
          @click="enterDayView(cell.date)"
        >
          <div class="cell-inner">
            <div class="cell-date">
              <span class="cell-date-label" :class="{ 'is-today': isToday(cell.date) }">
                {{ cell.date.toLocaleDateString(locale, { weekday: 'short', day: 'numeric' }) }}
              </span>
            </div>
            <ul class="cell-events">
              <li
                v-for="event in eventsForDate(cell.date).slice(0, 2)"
                :key="event.id"
              >
                <button
                  type="button"
                  class="event-pill event-pill-button"
                  :class="{ 'event-pill--expiring': isExpiringHold(event) }"
                  :style="eventPillStyle(event)"
                  :title="eventMarkerDetails(event)"
                  @click.stop="openEventDetails(event)"
                >
                  <span class="event-status-dot" :class="statusDotClass(event)"></span>
                  {{ eventMarkerLabel(event) }}
                </button>
              </li>
            </ul>
            <button
              v-if="eventsForDate(cell.date).length > 2"
              type="button"
              class="event-more day-link"
              @click.stop="enterDayView(cell.date)"
            >
              {{ t.viewMore }}
            </button>
          </div>
        </div>
      </template>
    </div>

    <div v-else class="day-view">
      <h2 class="day-title">{{ dayLabel }}</h2>
      <p class="day-hint">{{ canCreateEvents ? t.dayHint : t.dayHintReadonly }}</p>
      <div class="day-hours">
        <div
          v-for="hour in hours"
          :key="hour"
          class="day-hour"
          :class="{ blocked: isHourBlocked(hour) }"
          role="button"
          tabindex="0"
          @click="handleHourClick(hour)"
          @keydown.enter.prevent="handleHourClick(hour)"
          @keydown.space.prevent="handleHourClick(hour)"
        >
          <span class="hour-label">{{ hour }}:00</span>
          <span class="hour-events">
            <span
              v-for="event in eventsForHour(hour)"
              :key="event.id"
              class="event-pill event-pill-button"
              :class="{ 'event-pill--expiring': isExpiringHold(event) }"
              :style="eventPillStyle(event)"
            >
              {{ event.title }}
            </span>
          </span>
        </div>
      </div>
    </div>

    <div class="calendar-legend" v-if="legendDepartments.length">
      <h3 class="legend-title">{{ locale === 'es' ? 'Departamentos' : 'Departments' }}</h3>
      <ul class="legend-list">
        <li v-for="dept in legendDepartments" :key="dept.id" class="legend-item">
          <span class="legend-swatch" :style="{ backgroundColor: dept.color || '#ccc' }"></span>
          <span>{{ dept.name }}</span>
        </li>
      </ul>
    </div>

    <div class="calendar-legend" v-if="statusLegend.length">
      <h3 class="legend-title">{{ locale === 'es' ? 'Estados' : 'Statuses' }}</h3>
      <ul class="legend-list">
        <li v-for="item in statusLegend" :key="item.key" class="legend-item">
          <span class="legend-swatch" :class="item.className"></span>
          <span>{{ item.label }}</span>
        </li>
      </ul>
    </div>

    <section v-if="canViewChangeRequests && rejectedEvents.length" class="review-panel">
      <h2>{{ locale === 'es' ? 'Eventos rechazados' : 'Rejected events' }}</h2>
      <article v-for="event in rejectedEvents" :key="event.id" class="review-item">
        <strong>{{ event.title }}</strong>
        <span>{{ formatEventTime(event) }}</span>
        <p>{{ event.review_note }}</p>
        <button class="btn btn-outline-secondary" @click="openEventDetails(event)">{{ t.viewDetailsButton }}</button>
      </article>
    </section>

    <section v-if="canViewChangeRequests && changeRequestedEvents.length" class="review-panel">
      <div class="review-header">
        <h2 class="review-title">{{ t.changeRequestsTitle }}</h2>
        <p class="review-subtitle">{{ t.changeRequestsSubtitle }}</p>
      </div>
      <div class="review-list">
        <article v-for="event in changeRequestedEvents" :key="event.id" class="review-item">
          <div class="review-info">
            <strong>{{ event.title }}</strong>
            <span>
              {{ formatEventTime(event) }}
              · {{ event.department?.name || t.unknownDepartment }}
            </span>
            <span class="review-status">
              {{ t.locationLabel }}: {{ event.location || '—' }}
            </span>
          </div>
          <div class="review-actions">
            <div class="review-buttons">
              <button
                type="button"
                class="btn btn-outline-secondary"
                @click="openChangeRequest(event)"
              >
                {{ t.editEventButton }}
              </button>
              <button
                type="button"
                class="btn btn-outline-primary"
                @click="openEventDetails(event)"
              >
                {{ t.viewDetailsButton }}
              </button>
            </div>
          </div>
        </article>
      </div>
    </section>

    <section v-if="canReview && clubConflictEvents.length" class="review-panel">
      <div class="review-header">
        <h2 class="review-title">{{ t.clubConflictsTitle }}</h2>
        <p class="review-subtitle">{{ t.clubConflictsSubtitle }}</p>
      </div>
      <div class="review-actions">
        <button type="button" class="btn btn-outline-primary" @click="openClubConflictsModal">
          {{ t.clubConflictsButton }} ({{ clubConflictEvents.length }})
        </button>
      </div>
    </section>

    <EventModal
      :open="isModalOpen && canCreateEvents"
      :selected-date="selectedDate"
      :title="form.title"
      :description="form.description"
      :location="form.location"
      :start-time="form.startTime"
      :end-time="form.endTime"
      :objective-id="form.objectiveId"
      :objectives="objectiveOptions"
      :active-hold-id="activeHoldId"
      :hold-expires-at="activeHold?.expires_at || ''"
      :is-editing="Boolean(editEventId)"
      :is-resuming-hold="isResumingHold"
      :error="formError"
      :notice="formNotice"
      :labels="{ ...t.drawer, saveEvent: locale === 'es' ? 'Reenviar' : 'Resubmit' }"
      @update:title="form.title = $event"
      @update:description="form.description = $event"
      @update:location="form.location = $event"
      @update:startTime="form.startTime = $event"
      @update:endTime="form.endTime = $event"
      @update:objectiveId="form.objectiveId = $event"
      @create-hold="createHold"
      @lock-hold="lockHold"
      @save-edit="saveEditedEvent"
      @close="closeModal"
    />

    <EventDetailsModal
      :open="isEventDetailsOpen"
      :event="selectedEvent"
      :notes="selectedEvent?.notes || []"
      :histories="selectedEvent?.histories || []"
      :can-reply-notes="selectedEvent?.status === 'locked' && (canReview || selectedEvent?.department_id === user?.department_id)"
      :can-review="canReview"
      :saving="eventNoteSaving"
      :error="eventNoteError"
      @review="handleDetailsReview"
      :can-edit-event="canEditSelectedEvent"
      :can-request-open="canRequestOpen(selectedEvent)"
      :request-open-label="locale === 'es' ? 'Solicitar apertura' : 'Request open'"
      @request-open="requestEventOpen"
      :labels="t.eventDetails"
      @close="closeEventDetails"
      @reply-note="handleDetailsReply"
      @edit-event="openEditModal"
    />

    <div v-if="reviewModalOpen" class="modal-backdrop" @click.self="closeReviewModal">
      <div class="modal-panel">
        <header class="modal-header">
          <h2>{{ t.reviewModalTitle }}</h2>
          <button type="button" class="modal-close" @click="closeReviewModal">×</button>
        </header>
        <p class="event-details-text">{{ reviewModalMessage }}</p>
        <div class="action-row">
          <button type="button" @click="closeReviewModal">{{ t.reviewModalClose }}</button>
        </div>
      </div>
    </div>

    <div v-if="unseenNotesOpen" class="modal-backdrop" @click.self="closeUnseenNotesModal">
      <div class="modal-panel modal-panel--lg">
        <header class="modal-header">
          <h2>{{ t.unseenNotesTitle }}</h2>
          <button type="button" class="modal-close" @click="closeUnseenNotesModal">×</button>
        </header>
        <div class="event-details">
          <p v-if="unseenNotesLoading" class="event-details-text">{{ t.unseenNotesLoading }}</p>
          <p v-else-if="unseenNotes.length === 0" class="event-details-text">{{ t.unseenNotesEmpty }}</p>
          <div v-else class="history-list">
            <article
              v-for="group in unseenNoteGroups"
              :key="group.eventId"
              class="history-item unseen-note-group"
            >
              <button
                type="button"
                class="unseen-note-toggle"
                @click="toggleUnseenEvent(group.eventId)"
              >
                <div>
                  <div class="event-details-title">{{ group.eventTitle }}</div>
                  <div class="history-meta">
                    {{ t.unseenNotesDate }}: {{ formatHistoryValue(group.startAt) }}
                  </div>
                  <div class="history-meta">
                    {{ t.unseenNotesDepartment }}: {{ group.departmentName }}
                  </div>
                  <div class="history-meta">
                    {{ t.unseenNotesLocation }}: {{ group.location }}
                  </div>
                </div>
                <span class="chip">{{ group.notes.length }}</span>
              </button>

              <div v-if="isUnseenEventExpanded(group.eventId)" class="mt-3">
                <ul class="history-list">
                  <li
                    v-for="note in group.notes"
                    :key="note.id"
                    class="history-item note-item"
                    :class="noteClass(note)"
                  >
                    <div class="history-meta">
                      {{ note.author?.name || '—' }} · {{ formatHistoryValue(note.created_at) }}
                    </div>
                    <div class="history-note">{{ note.note }}</div>
                    <label class="form-check-label small mt-2">
                      <input
                        class="form-check-input me-2"
                        type="checkbox"
                        :checked="false"
                        :disabled="markingSeenNoteIds.has(note.id)"
                        @change="toggleNoteSeen(note, $event.target.checked)"
                      />
                      {{ t.unseenNotesMarkRead }}
                    </label>
                  </li>
                </ul>
                <div class="mt-3">
                  <button type="button" class="btn btn-outline-primary" @click="openEventFromNote(group.notes[0])">
                    {{ t.unseenNotesOpenEvent }}
                  </button>
                </div>
              </div>
            </article>
          </div>
        </div>
        <div class="action-row">
          <button type="button" @click="closeUnseenNotesModal">{{ t.reviewModalClose }}</button>
        </div>
      </div>
    </div>

    <div v-if="clubConflictsOpen" class="modal-backdrop" @click.self="closeClubConflictsModal">
      <div class="modal-panel modal-panel--lg">
        <header class="modal-header">
          <h2>{{ t.clubConflictsModalTitle }}</h2>
          <button type="button" class="modal-close" @click="closeClubConflictsModal">×</button>
        </header>
        <div v-if="clubConflictEvents.length === 0" class="event-details-text">
          {{ t.clubConflictsEmpty }}
        </div>
        <div v-else class="event-details">
          <ul class="history-list">
            <li v-for="event in clubConflictEvents" :key="event.id" class="history-item">
              <div class="history-meta">
                <strong>{{ t.clubConflictsEvent }}:</strong> {{ event.title }}
              </div>
              <div class="event-details-text">
                <strong>{{ t.clubConflictsDepartment }}:</strong> {{ event.department?.name || t.unknownDepartment }}
              </div>
              <div class="event-details-text">
                <strong>{{ t.clubConflictsDate }}:</strong> {{ formatEventTime(event) }}
              </div>
              <div class="review-actions mt-2">
                <button type="button" class="btn btn-outline-primary" @click="openEventDetails(event)">
                  {{ t.clubConflictsReview }}
                </button>
                <button type="button" class="btn btn-outline-secondary" @click="openEventDetails(event)">
                  {{ t.viewDetailsButton }}
                </button>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <section v-if="canReview" class="review-panel">
      <div class="review-header">
        <div>
          <h2 class="review-title">{{ locale === 'es' ? 'Eventos' : 'Events' }} · {{ reviewScopeLabel }}</h2>
          <p class="review-subtitle">{{ t.reviewSubtitle }}</p>
        </div>

      </div>
      <div class="review-filters row g-2">
        <label class="col-12 col-sm-6 col-lg-3">
          <span class="form-label small mb-1">{{ t.filterStatus }}</span>
          <select v-model="statusFilter" class="form-select">
            <option value="all">{{ locale === 'es' ? 'Todos los estados' : 'All statuses' }}</option>
            <option value="to_handle">{{ locale === 'es' ? 'Por atender' : 'To handle' }}</option>
            <option value="pending">{{ t.statusPending }}</option>
            <option value="resubmitted">{{ locale === 'es' ? 'Reenviados' : 'Resubmitted' }}</option>
            <option value="approved">{{ t.statusApproved }}</option>
            <option value="denied">{{ t.statusDenied }}</option>
            <option value="changes_requested">{{ t.statusChanges }}</option>
          </select>
        </label>
        <label class="col-12 col-sm-6 col-lg-3">
          <span class="form-label small mb-1">{{ t.filterDepartment }}</span>
          <select v-model="reviewDepartmentFilter" class="form-select">
            <option value="all">{{ t.allDepartments }}</option>
            <option v-for="dept in departmentOptions" :key="dept.id" :value="String(dept.id)">
              {{ dept.name }}
            </option>
          </select>
        </label>
      </div>
      <div v-if="reviewGroups.length === 0" class="review-empty">
        {{ locale === 'es' ? 'No hay eventos para este período y los filtros seleccionados.' : 'No events match this period and the selected filters.' }}
      </div>
      <div v-else class="review-list">
        <article v-for="group in reviewGroups" :key="group.date" class="review-group">
          <h3 class="review-date">{{ formatReviewDate(group.date) }}</h3>
          <div class="review-items">
            <div v-for="event in group.events" :key="event.id" class="review-item">
              <div class="review-info">
                <strong>{{ event.title }}</strong>
                <span>
                  {{ formatEventTime(event) }}
                  · {{ event.department?.name || t.unknownDepartment }}
                </span>
                <span class="review-status">
                  {{ t.locationLabel }}: {{ event.location || '—' }}
                </span>
                <span class="review-status">
                  <span class="status-dot" :class="statusClass(event.review_status)"></span>
                  {{ t.reviewStatus }}: {{ reviewStatusLabel(event.review_status) }}
                </span>
                <span v-if="event.open_requested_at" class="review-status">{{ locale === 'es' ? 'Apertura solicitada' : 'Opening requested' }}</span>
                <span v-if="event.accepted_at" class="review-status">
                  {{ t.reviewAcceptedAt }}: {{ formatDateOnly(event.accepted_at) }}
                </span>
              </div>
              <div class="review-actions">
                <div class="review-buttons">
                  <button type="button" class="btn btn-outline-primary" @click="openEventDetails(event)">
                    {{ locale === 'es' ? 'Notas y revisión' : 'Notes and review' }}
                  </button>
                </div>
                <div class="review-history">
                  <div class="history-header">
                    <h4 class="history-title">{{ t.historyTitle }}</h4>
                    <button
                      v-if="historyForEvent(event).length"
                      type="button"
                      class="history-toggle"
                      @click="toggleHistory(event.id)"
                    >
                      {{ isHistoryExpanded(event.id) ? t.historyHide : t.historyShow }}
                    </button>
                  </div>
                  <p v-if="historyForEvent(event).length === 0" class="history-empty">
                    {{ t.historyEmpty }}
                  </p>
                  <ul v-else-if="isHistoryExpanded(event.id)" class="history-list review-scroll">
                    <li
                      v-for="entry in historyForEvent(event)"
                      :key="entry.id"
                      class="history-item note-item"
                      :class="historyEntryClass(entry)"
                    >
                      <div class="history-meta">{{ formatHistoryMeta(entry) }}</div>
                      <div class="history-note">{{ entry.note }}</div>
                      <div v-if="entry.reply" class="history-reply note-item note-item--outgoing">
                        <div class="history-meta">{{ t.historyDepartmentReplyLabel }}</div>
                        <div class="history-note">{{ entry.reply }}</div>
                      </div>
                      <div v-else-if="entry.authorName" class="history-meta">
                        {{ entry.authorName }}
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </article>
      </div>
    </section>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '../stores/authStore'
import { useCalendarStore } from '../stores/calendarStore'
import { useLiveUpdateStore } from '../stores/liveUpdateStore'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'
import { objectiveApi } from '../services/objectiveApi'
import { publicApi } from '../services/publicApi'
import { eventActionLabel } from '../utils/eventWorkflow'
import { calendarApi } from '../services/calendarApi'
import EventModal from './EventModal.vue'
import EventDetailsModal from './EventDetailsModal.vue'

const authStore = useAuthStore()
const calendarStore = useCalendarStore()
const liveUpdateStore = useLiveUpdateStore()
const uiStore = useUiStore()
const { events, holds } = storeToRefs(calendarStore)
const { locale } = storeToRefs(uiStore)
const { version: liveUpdateVersion, lastUpdate } = storeToRefs(liveUpdateStore)

const user = computed(() => authStore.user)
const isSuperAdmin = computed(() => authStore.isSuperAdmin)
const isSecretary = computed(() => authStore.isSecretary)
const role = computed(() => authStore.role)
const canCreateEvents = computed(() => !isSecretary.value)
const canReview = computed(() => isSecretary.value || isSuperAdmin.value)
const canViewUnseenNotes = computed(
  () => !isSecretary.value && !isSuperAdmin.value && Boolean(user.value?.department_id)
)
const canViewChangeRequests = computed(
  () => !isSecretary.value && !isSuperAdmin.value && Boolean(user.value?.department_id)
)
const isHoldExpired = (event) => {
  if (!event || event.status !== 'hold') {
    return false
  }
  if (!event.expires_at) {
    return true
  }
  const expiresAt = new Date(event.expires_at).getTime()
  if (Number.isNaN(expiresAt)) {
    return true
  }
  return expiresAt <= Date.now()
}

const expiringHoldIds = ref(new Set())

const isExpiringHold = (event) => {
  if (!event) {
    return false
  }
  return expiringHoldIds.value.has(event.id)
}

const markExpiringHold = (eventId) => {
  const next = new Set(expiringHoldIds.value)
  next.add(eventId)
  expiringHoldIds.value = next
}

const clearExpiringHold = (eventId) => {
  if (!expiringHoldIds.value.has(eventId)) {
    return
  }
  const next = new Set(expiringHoldIds.value)
  next.delete(eventId)
  expiringHoldIds.value = next
}

const flashExpiredHold = (event) => {
  if (!event || isExpiringHold(event)) {
    return
  }
  markExpiringHold(event.id)
  window.setTimeout(() => {
    calendarStore.removeEvent(event.id)
    clearExpiringHold(event.id)
  }, 700)
}

const flashExpiredHolds = () => {
  const expired = (events.value || []).filter((event) => isHoldExpired(event))
  if (!expired.length) {
    return
  }
  expired.forEach((event) => flashExpiredHold(event))
}

const activeHold = computed(() =>
  holds.value.find((event) => event.id === activeHoldId.value && !isHoldExpired(event))
)
const canEditSelectedEvent = computed(() => {
  const event = selectedEvent.value
  if (!event) {
    return false
  }
  if (isSecretary.value || isSuperAdmin.value) {
    return false
  }
  if (event.status !== 'locked' || event.review_status !== 'changes_requested') {
    return false
  }
  return Boolean(user.value?.department_id) && user.value.department_id === event.department_id
})
const unseenCount = computed(() => unseenNotes.value.length)
const showMonthDropdown = computed(() => !isDayView.value)
const departmentName = computed(() => {
  if (user.value?.role === 'secretary') {
    return translations[locale.value].appLayout.roleLabels.secretary
  }
  return user.value?.department?.name || t.value.unknownDepartment
})

const currentMonth = ref(new Date())
const isMobile = ref(false)
const selectedDate = ref(null)
const weekAnchor = ref(new Date())
const activeHoldId = ref(null)
const formError = ref('')
const formNotice = ref('')
const isResumingHold = ref(false)
const objectives = ref([])
const isDayView = ref(false)
const departments = ref([])
const isModalOpen = ref(false)
const isEventDetailsOpen = ref(false)
const selectedEvent = ref(null)
const eventNoteSaving = ref(false)
const eventNoteError = ref('')
const departmentFilter = ref('all')
const statusFilter = ref('all')
const reviewDepartmentFilter = ref('all')
const monthSelection = ref('')
const expandedHistoryIds = ref(new Set())
const reviewModalOpen = ref(false)
const reviewModalMessage = ref('')
const editEventId = ref(null)
const includeHistoryExport = ref(false)
const unseenNotes = ref([])
const unseenNotesOpen = ref(false)
const unseenNotesLoading = ref(false)
const expandedUnseenEventIds = ref(new Set())
const markingSeenNoteIds = ref(new Set())
const clubConflictsOpen = ref(false)
const churchSlug = ref('')

const form = reactive({
  title: '',
  description: '',
  location: '',
  startTime: '09:00',
  endTime: '10:00',
  objectiveId: '',
})

const t = computed(() => translations[locale.value].calendar)
const effectiveChurchId = computed(() => user.value?.church_id || user.value?.department?.church_id || null)
const calendarFeedUrl = computed(() => {
  if (!churchSlug.value) {
    return ''
  }

  return `${window.location.origin}/api/public/churches/${churchSlug.value}/events.ics`
})

const weekDays = computed(() => t.value.days)

const loadChurchFeedUrl = async () => {
  if (!effectiveChurchId.value) {
    churchSlug.value = ''
    return
  }

  try {
    const churches = await publicApi.listChurches()
    churchSlug.value = churches.find((church) => church.id === effectiveChurchId.value)?.slug || ''
  } catch {
    churchSlug.value = ''
  }
}

const copyCalendarFeedUrl = async () => {
  if (!calendarFeedUrl.value) {
    return
  }

  try {
    await navigator.clipboard.writeText(calendarFeedUrl.value)
    uiStore.showToast(t.value.feedCopySuccess, 'success')
  } catch {
    uiStore.showToast(t.value.feedCopyFailed, 'error')
  }
}

const monthLabel = computed(() =>
  currentMonth.value.toLocaleDateString(locale.value, { month: 'long', year: 'numeric' })
)

const monthOptions = computed(() => {
  const baseYear = currentMonth.value.getFullYear()
  const options = []
  for (let month = 0; month < 12; month += 1) {
    const date = new Date(baseYear, month, 1)
    options.push({
      value: `${baseYear}-${String(month + 1).padStart(2, '0')}`,
      label: date.toLocaleDateString(locale.value, { month: 'long', year: 'numeric' }),
    })
  }
  return options
})

const weekLabel = computed(() => {
  const base = selectedDate.value || weekAnchor.value || currentMonth.value
  const start = new Date(base)
  start.setDate(base.getDate() - base.getDay())
  const startOfMonth = new Date(base.getFullYear(), base.getMonth(), 1)
  startOfMonth.setHours(0, 0, 0, 0)
  const diff = start - startOfMonth
  const oneWeek = 7 * 24 * 60 * 60 * 1000
  const weekNumber = Math.max(1, Math.floor(diff / oneWeek) + 1)
  return `${t.value.weekPrefix} ${weekNumber}`
})

const dayLabel = computed(() => {
  if (!selectedDate.value) {
    return ''
  }
  return selectedDate.value.toLocaleDateString(locale.value, {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
  })
})

const calendarCells = computed(() => {
  const year = currentMonth.value.getFullYear()
  const month = currentMonth.value.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const startOffset = firstDay.getDay()
  const totalDays = lastDay.getDate()

  const cells = []
  for (let i = 0; i < startOffset; i += 1) {
    cells.push({ date: null })
  }

  for (let day = 1; day <= totalDays; day += 1) {
    cells.push({ date: new Date(year, month, day) })
  }

  while (cells.length % 7 !== 0) {
    cells.push({ date: null })
  }

  return cells
})

const weekCells = computed(() => {
  const base = selectedDate.value || weekAnchor.value || new Date(currentMonth.value)
  const start = new Date(base)
  start.setDate(base.getDate() - base.getDay())
  start.setHours(0, 0, 0, 0)

  const cells = []
  for (let i = 0; i < 7; i += 1) {
    const day = new Date(start)
    day.setDate(start.getDate() + i)
    cells.push({ date: day })
  }
  return cells
})

const displayEvents = computed(() => {
  let list = events.value || []
  list = list.filter((event) => event.status !== 'cancelled' && event.review_status !== 'denied')
  list = list.filter((event) => !isHoldExpired(event) || isExpiringHold(event))
  if (departmentFilter.value && departmentFilter.value !== 'all') {
    const departmentId = Number(departmentFilter.value)
    list = list.filter((event) => event.department_id === departmentId)
  }
  if (statusFilter.value && statusFilter.value !== 'all') {
    list = list.filter((event) => event.review_status === statusFilter.value)
  }
  return list
})

const clubConflictEvents = computed(() => {
  if (!canReview.value) {
    return []
  }
  return (events.value || []).filter((event) => event.requires_club_review)
})

const eventsByDate = computed(() => {
  const map = {}
  const list = displayEvents.value
  list.forEach((event) => {
    const parsed = new Date(event.start_at)
    const parsedEnd = new Date(event.end_at || event.start_at)
    if (Number.isNaN(parsed.getTime()) || Number.isNaN(parsedEnd.getTime())) {
      return
    }
    const startDay = new Date(parsed)
    startDay.setHours(0, 0, 0, 0)
    const endDay = new Date(parsedEnd)
    endDay.setHours(0, 0, 0, 0)
    if (endDay < startDay) {
      return
    }
    for (let day = new Date(startDay); day <= endDay; day.setDate(day.getDate() + 1)) {
      const dateKey = day.toISOString().slice(0, 10)
      if (!map[dateKey]) {
        map[dateKey] = []
      }
      map[dateKey].push(event)
    }
  })
  return map
})

const departmentMap = computed(() => {
  const map = new Map()
  departments.value.forEach((dept) => {
    map.set(dept.id, dept)
  })
  return map
})

const departmentOptions = computed(() => {
  if (departments.value.length) {
    return departments.value
  }
  const map = new Map()
  ;(events.value || []).forEach((event) => {
    if (event.department_id && event.department) {
      map.set(event.department_id, event.department)
    }
  })
  return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name))
})

const eventColor = (event) => {
  return event.department?.color || departmentMap.value.get(event.department_id)?.color || ''
}

const parseHexColor = (value) => {
  if (!value) {
    return null
  }
  const hex = value.trim()
  if (hex === 'transparent' || hex === 'none') {
    return null
  }
  if (!/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(hex)) {
    return null
  }
  let normalized = hex.slice(1)
  if (normalized.length === 3) {
    normalized = normalized
      .split('')
      .map((ch) => ch + ch)
      .join('')
  }
  const intValue = Number.parseInt(normalized, 16)
  return {
    r: (intValue >> 16) & 255,
    g: (intValue >> 8) & 255,
    b: intValue & 255,
  }
}

const isLightColor = (value) => {
  const rgb = parseHexColor(value)
  if (!rgb) {
    return false
  }
  const luminance = (0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b) / 255
  return luminance > 0.85
}

const eventPillStyle = (event) => {
  const color = eventColor(event)
  if (!color || color === 'transparent' || color === 'none' || color === 'rgba(0,0,0,0)') {
    return {
      backgroundColor: '#e9ecef',
      color: '#111111',
      border: '1px solid #111111',
    }
  }
  if (isLightColor(color)) {
    return {
      backgroundColor: color,
      color: '#111111',
      border: '1px solid #111111',
    }
  }
  return { backgroundColor: color }
}

const legendDepartments = computed(() => departmentOptions.value)
const statusLegend = computed(() => [
  {
    key: 'locked',
    label: t.value.eventDetails?.statusLocked || 'Locked',
    className: 'legend-swatch-status legend-swatch-status--locked',
  },
  {
    key: 'pending',
    label: t.value.eventDetails?.reviewPending || 'Pending',
    className: 'legend-swatch-status legend-swatch-status--pending',
  },
  {
    key: 'accepted',
    label: t.value.eventDetails?.finalOutcomeAccepted || 'Accepted',
    className: 'legend-swatch-status legend-swatch-status--accepted',
  },
  {
    key: 'rejected',
    label: t.value.eventDetails?.finalOutcomeRejected || 'Rejected',
    className: 'legend-swatch-status legend-swatch-status--rejected',
  },
])

const eventsForDate = (date) => {
  if (!date) {
    return []
  }
  const key = date.toISOString().slice(0, 10)
  return eventsByDate.value[key] || []
}

const eventMarkerLabel = (event) => {
  const title = (event.title || '').trim()
  if (!title) {
    return t.value.untitled
  }
  const words = title.split(/\s+/).filter(Boolean)
  if (words.length <= 3) {
    return title
  }
  return `${words.slice(0, 3).join(' ')}...`
}

const eventMarkerDetails = (event) => {
  const title = event.title || t.value.untitled
  const objectiveLabel = t.value.eventDetails?.objective || 'Objective'
  const statusLabel = t.value.eventDetails?.status || 'Status'
  const objective = event.objective?.name || '—'
  const status = event.status || '—'
  return `• ${title}\n• ${objectiveLabel}: ${objective}\n• ${statusLabel}: ${status}`
}

const statusDotClass = (event) => {
  if (event.review_status === 'approved') {
    return 'event-status-dot--accepted'
  }
  if (event.review_status === 'denied') {
    return 'event-status-dot--rejected'
  }
  if (event.status === 'locked') {
    return 'event-status-dot--locked'
  }
  return 'event-status-dot--pending'
}

const objectiveOptions = computed(() => {
  const list = objectives.value || []
  if (isSuperAdmin.value) {
    return list.map((objective) => ({
      ...objective,
      display_name: objective.department?.name
        ? `${objective.name} - ${objective.department.name}`
        : objective.name,
    }))
  }

  const departmentId = user.value?.department_id
  if (!departmentId) {
    return []
  }
  return list
    .filter((objective) => objective.department_id === departmentId)
    .map((objective) => ({
      ...objective,
      display_name: objective.name,
    }))
})

const eventsForHour = (hour) => {
  if (!selectedDate.value) {
    return []
  }
  const start = new Date(selectedDate.value)
  start.setHours(hour, 0, 0, 0)
  const end = new Date(selectedDate.value)
  end.setHours(hour + 1, 0, 0, 0)

  return displayEvents.value.filter((event) => {
    const eventStart = new Date(event.start_at)
    const eventEnd = new Date(event.end_at)
    return start < eventEnd && end > eventStart
  })
}

const isHourBlocked = (hour) => {
  if (!selectedDate.value) {
    return false
  }
  const start = new Date(selectedDate.value)
  start.setHours(hour, 0, 0, 0)
  const end = new Date(selectedDate.value)
  end.setHours(hour + 1, 0, 0, 0)
  return calendarStore.isSlotBlocked(start.toISOString(), end.toISOString())
}

const hours = Array.from({ length: 24 }, (_, index) => index)

const isSelected = (date) => {
  if (!date || !selectedDate.value) {
    return false
  }
  return date.toDateString() === selectedDate.value.toDateString()
}

const isToday = (date) => {
  if (!date) {
    return false
  }
  const today = new Date()
  return date.toDateString() === today.toDateString()
}

const enterDayView = (date) => {
  selectedDate.value = date
  weekAnchor.value = new Date(date)
  currentMonth.value = new Date(date)
  isDayView.value = true
}

const clearSelection = () => {
  selectedDate.value = null
  activeHoldId.value = null
  formError.value = ''
  formNotice.value = ''
}

const closeModal = () => {
  isModalOpen.value = false
  activeHoldId.value = null
  editEventId.value = null
  isResumingHold.value = false
  formError.value = ''
  formNotice.value = ''
}

const openEventDetails = async (event) => {
  clubConflictsOpen.value = false
  if (isHoldExpired(event)) {
    flashExpiredHold(event)
    return
  }
  if (canResumeHold(event)) {
    openHoldModal(event)
    return
  }
  selectedEvent.value = event
  eventNoteError.value = ''
  isEventDetailsOpen.value = true
  await markNotesSeenForEvent(event)
}

const openChangeRequest = (event) => {
  if (canEditEvent(event)) {
    prefillFormFromEvent(event)
    isModalOpen.value = true
    return
  }
  openEventDetails(event)
}

const closeEventDetails = () => {
  isEventDetailsOpen.value = false
  selectedEvent.value = null
}

const openEditModal = () => {
  if (!selectedEvent.value) {
    return
  }
  prefillFormFromEvent(selectedEvent.value)
  isModalOpen.value = true
}

const exitDayView = () => {
  isDayView.value = false
  selectedDate.value = null
  activeHoldId.value = null
  isResumingHold.value = false
  formError.value = ''
  formNotice.value = ''
  isModalOpen.value = false
}

const combineDateTime = (date, time) => {
  const [hours, minutes] = time.split(':').map(Number)
  const combined = new Date(date)
  combined.setHours(hours || 0, minutes || 0, 0, 0)
  return combined
}

const extractErrorMessage = (err) => {
  const data = err?.response?.data
  if (!data) {
    return ''
  }
  if (typeof data.message === 'string' && data.message.trim()) {
    return data.message
  }
  const firstKey = data.errors ? Object.keys(data.errors)[0] : null
  if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey][0]) {
    return data.errors[firstKey][0]
  }
  return ''
}

const createHold = async () => {
  if (!canCreateEvents.value) {
    return
  }
  if (!selectedDate.value) {
    return
  }
  formError.value = ''
  formNotice.value = ''

  const startAt = combineDateTime(selectedDate.value, form.startTime)
  const endAt = combineDateTime(selectedDate.value, form.endTime)

  if (endAt <= startAt) {
    formError.value = 'End time must be after start time.'
    return
  }

  if (calendarStore.isSlotBlocked(startAt.toISOString(), endAt.toISOString())) {
    formError.value = 'That time slot is already blocked.'
    return
  }

  try {
    const event = await calendarStore.createHold({
      title: form.title || null,
      description: form.description || null,
      location: form.location || null,
      objective_id: form.objectiveId || null,
      start_at: startAt.toISOString(),
      end_at: endAt.toISOString(),
    })
    activeHoldId.value = event.id
    const expiresAt = event.expires_at ? new Date(event.expires_at) : null
    if (expiresAt && !Number.isNaN(expiresAt.getTime())) {
      const time = expiresAt.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
      const template = t.value.drawer?.statusLegendHoldExpires || 'at {time}'
      formNotice.value = `Hold created. Expira ${template.replace('{time}', time)}`
    } else {
      formNotice.value = 'Hold created. Lock to confirm.'
    }
  } catch (err) {
    formError.value = extractErrorMessage(err) || 'Unable to create hold.'
  }
}

const lockHold = async () => {
  if (!canCreateEvents.value) {
    return
  }
  if (!activeHoldId.value) {
    return
  }
  formError.value = ''
  formNotice.value = ''
  try {
    if (!form.objectiveId) {
      formError.value = t.value.errors.selectObjective
      return
    }
    await calendarStore.updateEvent(activeHoldId.value, {
      title: form.title || t.value.untitled,
      description: form.description || null,
      location: form.location || null,
      objective_id: form.objectiveId || null,
      start_at: combineDateTime(selectedDate.value, form.startTime).toISOString(),
      end_at: combineDateTime(selectedDate.value, form.endTime).toISOString(),
    })
    await calendarStore.lockEvent(activeHoldId.value)
    formNotice.value = 'Event locked.'
  } catch (err) {
    formError.value = extractErrorMessage(err) || 'Unable to lock event.'
  }
}

const saveEditedEvent = async () => {
  if (!editEventId.value || !selectedDate.value) {
    return
  }
  const startAt = combineDateTime(selectedDate.value, form.startTime)
  const endAt = combineDateTime(selectedDate.value, form.endTime)

  if (endAt <= startAt) {
    formError.value = 'End time must be after start time.'
    return
  }

  formError.value = ''
  formNotice.value = ''

  try {
    await calendarStore.updateEvent(editEventId.value, {
      title: form.title,
      description: form.description,
      location: form.location,
      objective_id: form.objectiveId || null,
      start_at: startAt.toISOString(),
      end_at: endAt.toISOString(),
    })
    formNotice.value = t.value.errors.eventUpdated
    editEventId.value = null
    isModalOpen.value = false
  } catch {
    formError.value = t.value.errors.eventUpdateFailed
  }
}

const handleHourClick = (hour) => {
  const hourEvents = eventsForHour(hour)
  if (isSecretary.value) {
    if (hourEvents.length > 0) {
      openEventDetails(hourEvents[0])
    }
    return
  }

  if (hourEvents.length > 0) {
    const event = hourEvents[0]
    if (canResumeHold(event)) {
      openHoldModal(event)
      return
    }
    if (canEditEvent(event)) {
      prefillFormFromEvent(event)
      isModalOpen.value = true
      return
    }
    openEventDetails(event)
    return
  }

  if (!canCreateEvents.value) {
    return
  }

  selectHour(hour)
}

const selectHour = (hour) => {
  if (!canCreateEvents.value) {
    return
  }
  if (!selectedDate.value) {
    return
  }
  const startHour = `${hour.toString().padStart(2, '0')}:00`
  const endHour = `${(hour + 1).toString().padStart(2, '0')}:00`
  form.startTime = startHour
  form.endTime = endHour
  formError.value = ''
  formNotice.value = ''
  isModalOpen.value = true
}

const canEditEvent = (event) => {
  if (!event) {
    return false
  }
  if (isSecretary.value || isSuperAdmin.value) {
    return false
  }
  if (event.status !== 'locked' || event.review_status !== 'changes_requested') {
    return false
  }
  return Boolean(user.value?.department_id) && user.value.department_id === event.department_id
}

const canResumeHold = (event) => {
  if (!event || event.status !== 'hold') {
    return false
  }
  if (isSecretary.value || isSuperAdmin.value) {
    return false
  }
  if (isHoldExpired(event)) {
    return false
  }
  return Boolean(user.value?.department_id) && user.value.department_id === event.department_id
}

const openHoldModal = (event) => {
  prefillFormFromEvent(event, { isHold: true })
  activeHoldId.value = event.id
  editEventId.value = null
  isResumingHold.value = true
  isModalOpen.value = true
}

const prefillFormFromEvent = (event, options = {}) => {
  if (!event) {
    return
  }
  const start = new Date(event.start_at)
  const end = new Date(event.end_at)
  selectedDate.value = new Date(start)
  form.title = event.title || ''
  form.description = event.description || ''
  form.location = event.location || ''
  form.objectiveId = event.objective_id ? String(event.objective_id) : ''
  form.startTime = start.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
  form.endTime = end.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
  if (!options.isHold) {
    editEventId.value = event.id
  }
  formError.value = ''
  formNotice.value = ''
}

const loadMonth = async () => {
  const year = currentMonth.value.getFullYear()
  const month = currentMonth.value.getMonth()
  const start = new Date(year, month, 1)
  start.setHours(0, 0, 0, 0)
  const end = new Date(year, month + 1, 0)
  end.setHours(23, 59, 59, 999)
  await calendarStore.fetchRange(start.toISOString(), end.toISOString())
}

const loadDepartments = async (churchId) => {
  if (!churchId) {
    departments.value = []
    return
  }
  try {
    departments.value = await publicApi.listDepartments(churchId)
  } catch {
    departments.value = []
  }
}

const shiftPeriod = (direction) => {
  const base = isMobile.value ? weekAnchor.value || currentMonth.value : currentMonth.value
  const date = new Date(base)
  if (isMobile.value) {
    date.setDate(date.getDate() + direction * 7)
    weekAnchor.value = date
    currentMonth.value = date
  } else {
    date.setMonth(date.getMonth() + direction)
    currentMonth.value = date
  }
}

const goPrevMonth = () => {
  shiftPeriod(-1)
}

const goNextMonth = () => {
  shiftPeriod(1)
}

const applyMonthSelection = () => {
  if (!monthSelection.value) {
    return
  }
  const [year, month] = monthSelection.value.split('-').map(Number)
  if (!year || !month) {
    return
  }
  currentMonth.value = new Date(year, month - 1, 1)
  if (selectedDate.value) {
    selectedDate.value = new Date(year, month - 1, selectedDate.value.getDate())
  }
  weekAnchor.value = new Date(currentMonth.value)
}

const reviewGroups = computed(() => {
  if (!canReview.value) {
    return []
  }
  let list = (events.value || [])
    .filter((event) => event.status === 'locked')
    .filter((event) => {
      if (statusFilter.value === 'all') return true
      if (statusFilter.value === 'to_handle') return ['pending', 'resubmitted'].includes(event.review_status) || Boolean(event.open_requested_at)
      return event.review_status === statusFilter.value
    })
    .sort((a, b) => new Date(a.start_at) - new Date(b.start_at))

  if (reviewDepartmentFilter.value && reviewDepartmentFilter.value !== 'all') {
    const departmentId = Number(reviewDepartmentFilter.value)
    list = list.filter((event) => event.department_id === departmentId)
  }

  const range = reviewRange.value
  const grouped = new Map()
  list.forEach((event) => {
    const startAt = new Date(event.start_at)
    if (range && (startAt < range.start || startAt > range.end)) {
      return
    }
    const dateKey = startAt.toISOString().slice(0, 10)
    if (!grouped.has(dateKey)) {
      grouped.set(dateKey, [])
    }
    grouped.get(dateKey).push(event)
  })

  return Array.from(grouped.entries()).map(([date, events]) => ({ date, events }))
})

const rejectedEvents = computed(() => (events.value || []).filter((event) =>
  event.status === 'locked' && event.review_status === 'denied' && event.department_id === user.value?.department_id
))
const canRequestOpen = (event) => event?.status === 'locked' && event.review_status === 'approved'
  && event.department_id === user.value?.department_id && !event.open_requested_at && !canReview.value
const requestEventOpen = async () => {
  try {
    const event = await calendarApi.requestOpen(selectedEvent.value.id)
    calendarStore.upsertEvent(event)
    selectedEvent.value = event
  } catch (error) {
    reviewModalMessage.value = extractErrorMessage(error)
    reviewModalOpen.value = true
  }
}

const changeRequestedEvents = computed(() => {
  if (!canViewChangeRequests.value) {
    return []
  }
  const list = displayEvents.value
    .filter((event) => event.review_status === 'changes_requested')
    .filter((event) => event.department_id === user.value?.department_id)
    .sort((a, b) => new Date(a.start_at) - new Date(b.start_at))

  const range = reviewRange.value
  if (!range) {
    return list
  }
  return list.filter((event) => {
    const startAt = new Date(event.start_at)
    return startAt >= range.start && startAt <= range.end
  })
})

const reviewScopeLabel = computed(() => {
  if (isDayView.value) {
    return t.value.reviewDaily
  }
  if (isMobile.value) {
    return t.value.reviewWeekly
  }
  return t.value.reviewMonthly
})

const reviewRange = computed(() => {
  if (isDayView.value && selectedDate.value) {
    const start = new Date(selectedDate.value)
    start.setHours(0, 0, 0, 0)
    const end = new Date(selectedDate.value)
    end.setHours(23, 59, 59, 999)
    return { start, end }
  }

  if (isMobile.value) {
    const cells = weekCells.value
    if (cells.length === 0) {
      return null
    }
    const start = new Date(cells[0].date)
    start.setHours(0, 0, 0, 0)
    const end = new Date(cells[cells.length - 1].date)
    end.setHours(23, 59, 59, 999)
    return { start, end }
  }

  const year = currentMonth.value.getFullYear()
  const month = currentMonth.value.getMonth()
  const start = new Date(year, month, 1)
  start.setHours(0, 0, 0, 0)
  const end = new Date(year, month + 1, 0)
  end.setHours(23, 59, 59, 999)
  return { start, end }
})

const formatReviewDate = (dateKey) => {
  const date = new Date(`${dateKey}T00:00:00`)
  return date.toLocaleDateString(locale.value, {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
  })
}

const formatEventTime = (event) => {
  const start = new Date(event.start_at)
  const end = new Date(event.end_at)
  return `${start.toLocaleTimeString(locale.value, { hour: '2-digit', minute: '2-digit' })} - ${end.toLocaleTimeString(
    locale.value,
    { hour: '2-digit', minute: '2-digit' }
  )}`
}

const formatDateOnly = (value) => {
  if (!value) {
    return '—'
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }
  return date.toLocaleDateString(locale.value)
}

const reviewStatusLabel = (status) => {
  if (!status) {
    return t.value.statusPending
  }
  const map = {
    pending: t.value.statusPending,
    resubmitted: locale.value === 'es' ? 'Reenviado' : 'Resubmitted',
    approved: t.value.statusApproved,
    denied: t.value.statusDenied,
    changes_requested: t.value.statusChanges,
  }
  return map[status] || status
}

const statusClass = (status) => {
  if (status === 'approved') {
    return 'status-approved'
  }
  if (status === 'denied') {
    return 'status-denied'
  }
  return 'status-pending'
}

const formatHistoryValue = (value) => {
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  if (typeof value === 'string' && !Number.isNaN(Date.parse(value))) {
    return new Date(value).toLocaleString(locale.value)
  }
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    return value.toLocaleString(locale.value)
  }
  return String(value)
}

const historyForEvent = (event) => {
  const historyEntries = (event?.histories || [])
    .map((entry) => ({
      id: `history-${entry.id}`,
      kind: 'status_change',
      note: [eventActionLabel(entry.action, locale.value), entry.note].filter(Boolean).join(' · '),
      reply: null,
      created_at: entry.created_at,
      authorRole: entry.user?.role || 'secretary',
      authorName: entry.user?.name || '',
    }))

  const noteEntries = (event?.notes || []).filter((entry) => !entry.event_history_id).map((entry) => ({
    id: `note-${entry.id}`,
    kind: 'single_note',
    note: entry.note,
    reply: entry.reply || null,
    created_at: entry.created_at,
    authorRole: entry.author?.role || '',
    authorName: entry.author?.name || '',
  }))

  return [...historyEntries, ...noteEntries].sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
}

const isSecretaryHistoryEntry = (entry) => {
  return entry.authorRole === 'secretary' || entry.authorRole === 'superadmin'
}

const historyEntryClass = (entry) => (
  isSecretaryHistoryEntry(entry) ? 'note-item--incoming' : 'note-item--outgoing'
)

const formatHistoryMeta = (entry) => {
  const date = entry.created_at ? new Date(entry.created_at).toLocaleString(locale.value) : '—'
  const sourceLabel = isSecretaryHistoryEntry(entry)
    ? t.value.historySecretaryLabel
    : t.value.historyDepartmentLabel
  const typeLabel = entry.kind === 'status_change'
    ? t.value.historyStatusChangeLabel
    : t.value.historySingleNoteLabel
  return `${typeLabel} · ${sourceLabel} · ${date}`
}

const isSecretaryNote = (note) => {
  const role = note?.author_role || note?.author?.role
  return role === 'secretary' || role === 'superadmin'
}

const noteClass = (note) => (isSecretaryNote(note) ? 'note-item--incoming' : 'note-item--outgoing')

const getNoteEvent = (note) => {
  return events.value.find((item) => item.id === note.event_id) || note.event || null
}

const unseenNoteGroups = computed(() => {
  const groups = new Map()

  unseenNotes.value.forEach((note) => {
    const event = getNoteEvent(note)
    const eventId = note.event_id || event?.id || `note-${note.id}`
    if (!groups.has(eventId)) {
      groups.set(eventId, {
        eventId,
        eventTitle: event?.title || t.value.untitled,
        startAt: event?.start_at || null,
        departmentName: event?.department?.name || t.value.unknownDepartment,
        location: event?.location || '—',
        notes: [],
      })
    }

    groups.get(eventId).notes.push(note)
  })

  return [...groups.values()]
    .map((group) => ({
      ...group,
      notes: [...group.notes].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)),
    }))
    .sort((a, b) => new Date(b.notes[0]?.created_at || 0) - new Date(a.notes[0]?.created_at || 0))
})

const fetchUnseenNotes = async () => {
  if (!canViewUnseenNotes.value) {
    return
  }
  unseenNotesLoading.value = true
  try {
    const response = await calendarApi.fetchUnseenNotes()
    unseenNotes.value = response.notes || []
  } catch {
    unseenNotes.value = []
  } finally {
    unseenNotesLoading.value = false
  }
}

const openUnseenNotesModal = async () => {
  unseenNotesOpen.value = true
  await fetchUnseenNotes()
  expandedUnseenEventIds.value = new Set(unseenNoteGroups.value.map((group) => group.eventId))
}

const closeUnseenNotesModal = () => {
  unseenNotesOpen.value = false
}

const openClubConflictsModal = () => {
  clubConflictsOpen.value = true
}

const closeClubConflictsModal = () => {
  clubConflictsOpen.value = false
}

const markNoteSeen = async (note) => {
  try {
    await calendarStore.markNoteSeen(note.id)
    unseenNotes.value = unseenNotes.value.filter((item) => item.id !== note.id)
  } catch {
    // no-op
  }
}

const toggleUnseenEvent = (eventId) => {
  const next = new Set(expandedUnseenEventIds.value)
  if (next.has(eventId)) {
    next.delete(eventId)
  } else {
    next.add(eventId)
  }
  expandedUnseenEventIds.value = next
}

const isUnseenEventExpanded = (eventId) => expandedUnseenEventIds.value.has(eventId)

const toggleNoteSeen = async (note, checked) => {
  if (!checked) {
    return
  }

  const next = new Set(markingSeenNoteIds.value)
  next.add(note.id)
  markingSeenNoteIds.value = next

  try {
    await markNoteSeen(note)
  } finally {
    const updated = new Set(markingSeenNoteIds.value)
    updated.delete(note.id)
    markingSeenNoteIds.value = updated
  }
}

const markNotesSeenForEvent = async (event) => {
  if (!canViewUnseenNotes.value || !event?.notes?.length) {
    return
  }
  const unseen = event.notes.filter((note) => !note.seen_note && isSecretaryNote(note))
  for (const note of unseen) {
    await markNoteSeen(note)
  }
}

const openEventFromNote = async (note) => {
  const event = events.value.find((item) => item.id === note.event_id) || note.event
  if (event) {
    openEventDetails(event)
  }
}

const handleDetailsReply = async ({ reply }) => {
  if (!selectedEvent.value?.id || eventNoteSaving.value) return
  eventNoteSaving.value = true
  eventNoteError.value = ''
  try {
    await calendarStore.addNote(selectedEvent.value.id, { note: reply })
    selectedEvent.value = events.value.find(item => item.id === selectedEvent.value.id)
  } catch (error) {
    eventNoteError.value = error.response?.data?.message || t.value.errors.noteSendFailed
  } finally {
    eventNoteSaving.value = false
  }
}

const handleDetailsReview = async ({ status, note }) => {
  if (!selectedEvent.value?.id || eventNoteSaving.value) return
  eventNoteSaving.value = true
  eventNoteError.value = ''
  try {
    selectedEvent.value = await calendarStore.reviewEvent(selectedEvent.value.id, {
      review_status: status, review_note: note || null,
    })
  } catch (error) {
    eventNoteError.value = error.response?.data?.message || t.value.errors.reviewFailed
  } finally {
    eventNoteSaving.value = false
  }
}

const toggleHistory = (eventId) => {
  const next = new Set(expandedHistoryIds.value)
  if (next.has(eventId)) {
    next.delete(eventId)
  } else {
    next.add(eventId)
  }
  expandedHistoryIds.value = next
}

const isHistoryExpanded = (eventId) => expandedHistoryIds.value.has(eventId)

const closeReviewModal = () => {
  reviewModalOpen.value = false
  reviewModalMessage.value = ''
}

const exportPdf = async (view) => {
  try {
    const response = await calendarApi.exportCalendar(view, includeHistoryExport.value, locale.value)
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const link = document.createElement('a')
    const now = new Date().toISOString().slice(0, 10)
    link.href = window.URL.createObjectURL(blob)
    link.download = `calendar-${view}-${now}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(link.href)
  } catch {
    // no-op: export errors are handled silently for now
  }
}

const handleResize = () => {
  isMobile.value = window.innerWidth <= 900
}

const holdClock = ref(Date.now())
const dismissedHoldWarnings = reactive(new Set())
const extendingHold = ref(false)
const holdExtensionError = ref('')
const expiringOwnHolds = computed(() => holds.value.filter((hold) => {
  const remaining = new Date(hold.expires_at).getTime() - holdClock.value
  return hold.created_by === user.value?.id && remaining > 0 && remaining <= 60000
    && !dismissedHoldWarnings.has(hold.id)
}))
const extendReservation = async (hold) => {
  extendingHold.value = true
  holdExtensionError.value = ''
  try {
    const data = await calendarApi.extendHold(hold.id)
    calendarStore.upsertEvent(data)
  } catch (error) {
    holdExtensionError.value = extractErrorMessage(error) || (locale.value === 'es' ? 'No se pudo extender la reserva.' : 'Unable to extend reservation.')
  } finally {
    extendingHold.value = false
  }
}
let holdCleanupTimer = null

watch(currentMonth, async () => {
  monthSelection.value = `${currentMonth.value.getFullYear()}-${String(currentMonth.value.getMonth() + 1).padStart(2, '0')}`
  await loadMonth()
})

watch(liveUpdateVersion, async () => {
  const update = lastUpdate.value
  if (!update || !['event', 'event_note'].includes(update.entity)) {
    return
  }

  await loadMonth()
  await fetchUnseenNotes()

  if (selectedEvent.value?.id) {
    selectedEvent.value = events.value.find((item) => item.id === selectedEvent.value.id) || selectedEvent.value
  }

})

onMounted(async () => {
  handleResize()
  window.addEventListener('resize', handleResize)
  objectives.value = await objectiveApi.list()
  await loadChurchFeedUrl()
  await loadDepartments(user.value?.church_id)
  monthSelection.value = `${currentMonth.value.getFullYear()}-${String(currentMonth.value.getMonth() + 1).padStart(2, '0')}`
  await loadMonth()
  await fetchUnseenNotes()
  calendarStore.connectRealtime()
  liveUpdateStore.connect()
  flashExpiredHolds()
  holdCleanupTimer = window.setInterval(() => { holdClock.value = Date.now(); flashExpiredHolds() }, 1000)
})

watch(user, async (next) => {
  await loadChurchFeedUrl()
  if (next?.church_id) {
    await loadDepartments(next.church_id)
  }
  await fetchUnseenNotes()
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  if (holdCleanupTimer) {
    window.clearInterval(holdCleanupTimer)
    holdCleanupTimer = null
  }
})
</script>

<style scoped>
.unread-notes-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin: 1.25rem 0;
  padding: 1.15rem 1.25rem;
  border: 1px solid #cddfd6;
  border-left: 4px solid #267461;
  border-radius: .85rem;
  background: linear-gradient(110deg, #edf6f1, #f9fbfa);
  box-shadow: 0 3px 12px rgb(28 76 59 / 5%);
}
.unread-notes-icon {
  display: grid;
  place-items: center;
  flex: 0 0 3rem;
  height: 3rem;
  border-radius: .8rem;
  background: #dceee4;
  color: #216751;
}
.unread-notes-icon svg { width: 1.65rem; height: 1.65rem; }
.unread-notes-copy { flex: 1; min-width: 0; }
.unread-notes-heading { display: flex; align-items: center; flex-wrap: wrap; gap: .65rem; }
.unread-notes-heading h2 { margin: 0; font-size: .875rem; font-weight: 600; color: #204b3d; }
.unread-notes-count { border-radius: 1rem; padding: .2rem .6rem; background: #fff0cc; color: #705016; font-size: .75rem; font-weight: 600; }
.unread-notes-copy p { margin: .4rem 0 0; color: #50675d; font-size: .8125rem; line-height: 1.5; }
.unread-notes-action { width: auto; display: inline-flex; align-items: center; justify-content: center; gap: .65rem; flex-shrink: 0; min-height: 2.75rem; padding: .65rem 1rem; border: 1px solid #216751; border-radius: .6rem; background: #216751; color: #fff; font-size: .8125rem; font-weight: 600; }
.unread-notes-action svg { width: 1.1rem; height: 1.1rem; }
.unread-notes-action:hover { background: #194e3e; border-color: #194e3e; }
.unread-notes-action:focus-visible { outline: 3px solid #af8530; outline-offset: 3px; }
@media (max-width: 600px) {
  .unread-notes-card { flex-wrap: wrap; gap: .8rem; padding: 1rem; align-items: flex-start; }
  .unread-notes-copy { flex-basis: calc(100% - 4rem); }
  .unread-notes-action { width: 100%; }
}
</style>
