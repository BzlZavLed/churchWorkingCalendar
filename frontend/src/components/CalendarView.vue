<template>
  <section class="calendar-page container-xl">
    <header class="calendar-header">
      <div class="row">
        <div class="col-12 col-lg-4">
          <h1 class="mb-1">{{ t.title }}</h1>
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
      <div class="row">
        <div
        v-if="canViewUnseenNotes && unseenCount"
        class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2"
        role="alert"
      >
        <div>
          {{ t.unseenNotesAlert }}: <strong>{{ unseenCount }}</strong>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" @click="openUnseenNotesModal">
          {{ t.unseenNotesButton }}
        </button>
      </div>
      </div>
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
                  :style="eventPillStyle(event)"
                  @click.stop="openEventDetails(event)"
                >
                  {{ event.title }} - {{ event.objective?.name || '' }} ({{ event.status }})
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
                  :style="eventPillStyle(event)"
                  @click.stop="openEventDetails(event)"
                >
                  {{ event.title }} - {{ event.objective?.name || '' }} ({{ event.status }})
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

    <section v-if="canViewChangeRequests" class="review-panel">
      <div class="review-header">
        <h2 class="review-title">{{ t.changeRequestsTitle }}</h2>
        <p class="review-subtitle">{{ t.changeRequestsSubtitle }}</p>
      </div>
      <div v-if="changeRequestedEvents.length === 0" class="review-empty">
        {{ t.changeRequestsEmpty }}
      </div>
      <div v-else class="review-list">
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

    <section v-if="canReview" class="review-panel">
      <div class="review-header">
        <h2 class="review-title">{{ t.clubConflictsTitle }}</h2>
        <p class="review-subtitle">{{ t.clubConflictsSubtitle }}</p>
      </div>
      <div v-if="clubConflictEvents.length === 0" class="review-empty">
        {{ t.clubConflictsEmpty }}
      </div>
      <div v-else class="review-actions">
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
      :is-editing="Boolean(editEventId)"
      :error="formError"
      :notice="formNotice"
      :labels="t.drawer"
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
      :can-reply-notes="canReplyEventNotes"
      :can-edit-event="canEditSelectedEvent"
      :show-status-details="showStatusDetails"
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

    <div v-if="statusModalOpen && activeReviewEvent" class="modal-backdrop" @click.self="closeStatusModal">
      <div class="modal-panel">
        <header class="modal-header">
          <h2>{{ t.statusModalTitle }}</h2>
          <button type="button" class="modal-close" @click="closeStatusModal">×</button>
        </header>
        <div class="event-details">
          <p class="event-details-title">{{ activeReviewEvent.title }}</p>
          <label class="form-label">
            {{ t.reviewStatus }}
            <select v-model="statusSelection" class="form-select">
              <option value="approved">{{ t.statusApproved }}</option>
              <option value="changes_requested">{{ t.statusChanges }}</option>
              <option value="denied">{{ t.statusDenied }}</option>
            </select>
          </label>
          <label v-if="statusSelection === 'approved'" class="export-toggle mt-2">
            <input v-model="publishToFeed" type="checkbox" />
            <span>{{ t.publishToFeed }}</span>
          </label>
          <label v-if="statusSelection === 'approved'" class="form-label mt-2">
            {{ t.statusAcceptedAt }}
            <input v-model="statusAcceptedAt" class="form-control" type="date" />
          </label>
          <label class="form-label mt-2">
            {{ t.statusModalNote }}
            <textarea v-model="statusNote" class="form-control" rows="3"></textarea>
          </label>
        </div>
        <div class="action-row">
          <button type="button" class="btn btn-outline-secondary" @click="closeStatusModal">
            {{ t.reviewModalClose }}
          </button>
          <button type="button" class="btn btn-outline-primary" @click="submitStatusModal">
            {{ t.statusModalSubmit }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="notesModalOpen && activeReviewEvent" class="modal-backdrop" @click.self="closeNotesModal">
      <div class="modal-panel">
        <header class="modal-header">
          <h2>{{ t.notesModalTitle }}</h2>
          <button type="button" class="modal-close" @click="closeNotesModal">×</button>
        </header>
        <div class="event-details">
          <p class="event-details-title">{{ activeReviewEvent.title }}</p>
          <div v-if="canAddNote" class="note-add">
            <label class="form-label">
              {{ t.statusModalNote }}
              <textarea v-model="noteDraft" class="form-control" rows="3"></textarea>
            </label>
            <button
              type="button"
              class="btn btn-outline-secondary mt-2"
              :disabled="!noteDraft || !noteDraft.trim()"
              @click="submitNoteModal"
            >
              {{ t.notesModalSubmit }}
            </button>
          </div>
        </div>
        <div class="action-row">
          <button type="button" @click="closeNotesModal">{{ t.reviewModalClose }}</button>
        </div>
      </div>
    </div>

    <div v-if="unseenNotesOpen" class="modal-backdrop" @click.self="closeUnseenNotesModal">
      <div class="modal-panel">
        <header class="modal-header">
          <h2>{{ t.unseenNotesTitle }}</h2>
          <button type="button" class="modal-close" @click="closeUnseenNotesModal">×</button>
        </header>
        <div class="event-details">
          <p v-if="unseenNotesLoading" class="event-details-text">{{ t.unseenNotesLoading }}</p>
          <p v-else-if="unseenNotes.length === 0" class="event-details-text">{{ t.unseenNotesEmpty }}</p>
          <ul v-else class="history-list">
            <li v-for="note in unseenNotes" :key="note.id" class="history-item">
              <div class="history-meta">
                {{ note.author?.name || '—' }} · {{ formatHistoryValue(note.created_at) }}
              </div>
              <div class="history-note">{{ note.note }}</div>
              <div class="history-note">
                <strong>{{ t.unseenNotesEvent }}:</strong> {{ note.event?.title || '—' }}
              </div>
              <div class="history-note">
                <strong>{{ t.unseenNotesDate }}:</strong> {{ formatHistoryValue(note.event?.start_at) }}
              </div>
              <button
                type="button"
                class="btn btn-outline-secondary btn-sm mt-2"
                @click="openEventFromNote(note)"
              >
                {{ t.unseenNotesOpenEvent }}
              </button>
            </li>
          </ul>
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
                <button type="button" class="btn btn-outline-primary" @click="openStatusModal(event)">
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

    <div v-if="publishAcceptedOpen" class="modal-backdrop" @click.self="closePublishAcceptedModal">
      <div class="modal-panel modal-panel--lg">
        <header class="modal-header">
          <h2>{{ t.publishAcceptedModalTitle }}</h2>
          <button type="button" class="modal-close" @click="closePublishAcceptedModal">×</button>
        </header>
        <div v-if="publishAcceptedEvents.length === 0" class="event-details-text">
          {{ t.publishAcceptedEmpty }}
        </div>
        <div v-else class="event-details">
          <ul class="history-list">
            <li
              v-for="event in publishAcceptedEvents"
              :key="event.id"
              class="history-item"
              :style="eventPillStyle(event)"
            >
              <div class="history-meta">
                <strong>{{ event.title }}</strong>
              </div>
              <div class="event-details-text">
                {{ formatEventTime(event) }} · {{ event.department?.name || t.unknownDepartment }}
              </div>
              <div class="event-details-text">
                {{ formatDateOnly(event.start_at) }}
              </div>
            </li>
          </ul>
        </div><br><br>
        <div class="action-row">
          <button type="button" class="btn btn-outline-secondary" @click="closePublishAcceptedModal">
            {{ t.publishAcceptedClose }}
          </button>
          <button
            v-if="publishAcceptedEvents.length > 0"
            type="button"
            class="btn btn-outline-primary"
            @click="publishAcceptedAll"
          >
            {{ t.publishAcceptedPublish }}
          </button>
        </div>
      </div>
    </div>

    <section v-if="canReview" class="review-panel">
      <div class="review-header">
        <div>
          <h2 class="review-title">{{ reviewScopeLabel }}</h2>
          <p class="review-subtitle">{{ t.reviewSubtitle }}</p>
        </div>
        <button type="button" class="btn btn-link" @click="openPublishAcceptedModal">
          {{ t.publishAcceptedAll }}
        </button>
      </div>
      <div class="review-filters row g-2">
        <label class="col-12 col-sm-6 col-lg-3">
          <span class="form-label small mb-1">{{ t.filterStatus }}</span>
          <select v-model="statusFilter" class="form-select">
            <option value="all">{{ t.allStatuses }}</option>
            <option value="pending">{{ t.statusPending }}</option>
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
        {{ t.reviewEmpty }}
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
                <span v-if="event.accepted_at" class="review-status">
                  {{ t.reviewAcceptedAt }}: {{ formatDateOnly(event.accepted_at) }}
                </span>
              </div>
              <div class="review-actions">
                <div class="review-buttons">
                  <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="openStatusModal(event)"
                  >
                    {{ t.statusUpdateButton }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-outline-primary"
                    @click="openNotesModal(event)"
                  >
                    {{ t.notesButton }}
                  </button>
                </div>
                <p v-if="reviewErrors[event.id]" class="text-danger mt-1">
                  {{ reviewErrors[event.id] }}
                </p>
                <div class="review-tabs">
                  <button
                    type="button"
                    class="review-tab"
                    :class="{ active: reviewTab(event.id) === 'history' }"
                    @click="setReviewTab(event.id, 'history')"
                  >
                    {{ t.historyTitle }}
                  </button>
                  <button
                    type="button"
                    class="review-tab"
                    :class="{ active: reviewTab(event.id) === 'notes' }"
                    @click="setReviewTab(event.id, 'notes')"
                  >
                    {{ t.notesTitle }} ({{ notesForEvent(event).length }})
                  </button>
                </div>
                <div v-if="reviewTab(event.id) === 'history'" class="review-history">
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
                    <li v-for="entry in historyForEvent(event)" :key="entry.id" class="history-item">
                      <div class="history-meta">{{ formatHistoryMeta(entry) }}</div>
                      <div v-if="entry.note" class="history-note">
                        {{ entry.note }}
                      </div>
                      <ul v-if="entry.changes && Object.keys(entry.changes).length" class="history-changes">
                        <li v-for="(change, field) in entry.changes" :key="field">
                          <strong>{{ historyFieldLabel(field) }}:</strong>
                          {{ formatHistoryValue(change.from) }} → {{ formatHistoryValue(change.to) }}
                        </li>
                      </ul>
                    </li>
                  </ul>
                </div>
                <div v-else class="review-notes">
                  <div class="notes-header">
                    <h4 class="history-title">{{ t.notesTitle }}</h4>
                    <button
                      v-if="notesForEvent(event).length"
                      type="button"
                      class="history-toggle"
                      @click="toggleNotes(event.id)"
                    >
                      {{ isNotesExpanded(event.id) ? t.notesHide : t.notesShow }}
                    </button>
                  </div>
                  <p v-if="notesForEvent(event).length === 0" class="history-empty">
                    {{ t.notesEmpty }}
                  </p>
                  <ul v-else-if="isNotesExpanded(event.id)" class="history-list review-scroll">
                    <li
                      v-for="note in notesForEvent(event)"
                      :key="note.id"
                      class="history-item note-item"
                      :class="noteClass(note)"
                    >
                      <div class="history-meta">
                        {{ note.author?.name || '—' }} · {{ formatHistoryValue(note.created_at) }}
                      </div>
                      <div class="history-note">{{ note.note }}</div>
                      <div v-if="note.reply" class="history-note">
                        <strong>{{ t.notesReply }}:</strong> {{ note.reply }}
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
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'
import { objectiveApi } from '../services/objectiveApi'
import { publicApi } from '../services/publicApi'
import { calendarApi } from '../services/calendarApi'
import EventModal from './EventModal.vue'
import EventDetailsModal from './EventDetailsModal.vue'

const authStore = useAuthStore()
const calendarStore = useCalendarStore()
const uiStore = useUiStore()
const { events } = storeToRefs(calendarStore)
const { locale } = storeToRefs(uiStore)

const user = computed(() => authStore.user)
const isSuperAdmin = computed(() => authStore.isSuperAdmin)
const isSecretary = computed(() => authStore.isSecretary)
const role = computed(() => authStore.role)
const canCreateEvents = computed(() => !isSecretary.value)
const canReview = computed(() => isSecretary.value || isSuperAdmin.value)
const canViewUnseenNotes = computed(
  () => !isSecretary.value && !isSuperAdmin.value && Boolean(user.value?.department_id)
)
const canReplyEventNotes = computed(() => canViewUnseenNotes.value)
const showStatusDetails = computed(() => false)
const canViewChangeRequests = computed(
  () => !isSecretary.value && !isSuperAdmin.value && Boolean(user.value?.department_id)
)
const canEditSelectedEvent = computed(() => {
  const event = selectedEvent.value
  if (!event) {
    return false
  }
  if (isSecretary.value || isSuperAdmin.value) {
    return false
  }
  if (event.review_status !== 'changes_requested') {
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
const objectives = ref([])
const isDayView = ref(false)
const departments = ref([])
const isModalOpen = ref(false)
const isEventDetailsOpen = ref(false)
const selectedEvent = ref(null)
const departmentFilter = ref('all')
const statusFilter = ref('all')
const reviewDepartmentFilter = ref('all')
const reviewErrors = reactive({})
const monthSelection = ref('')
const expandedHistoryIds = ref(new Set())
const reviewModalOpen = ref(false)
const reviewModalMessage = ref('')
const activeReviewTab = reactive({})
const expandedNotesIds = ref(new Set())
const statusModalOpen = ref(false)
const notesModalOpen = ref(false)
const activeReviewEvent = ref(null)
const statusSelection = ref('pending')
const statusNote = ref('')
const publishToFeed = ref(false)
const statusAcceptedAt = ref('')
const editEventId = ref(null)
const noteDraft = ref('')
const includeHistoryExport = ref(false)
const unseenNotes = ref([])
const unseenNotesOpen = ref(false)
const unseenNotesLoading = ref(false)
const clubConflictsOpen = ref(false)
const publishAcceptedOpen = ref(false)

const form = reactive({
  title: '',
  description: '',
  location: '',
  startTime: '09:00',
  endTime: '10:00',
  objectiveId: '',
})

const t = computed(() => translations[locale.value].calendar)

const weekDays = computed(() => t.value.days)

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

const publishAcceptedEvents = computed(() => {
  if (!canReview.value) {
    return []
  }
  const range = reviewRange.value
  if (!range) {
    return []
  }
  return (events.value || [])
    .filter((event) => event.final_validation === 'accepted')
    .filter((event) => !event.publish_to_feed)
    .filter((event) => {
      const startAt = new Date(event.start_at)
      return startAt >= range.start && startAt <= range.end
    })
    .sort((a, b) => new Date(a.start_at) - new Date(b.start_at))
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

const legendDepartments = computed(() => departments.value)

const eventsForDate = (date) => {
  if (!date) {
    return []
  }
  const key = date.toISOString().slice(0, 10)
  return eventsByDate.value[key] || []
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
  formError.value = ''
  formNotice.value = ''
}

const openEventDetails = async (event) => {
  selectedEvent.value = event
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

  if (!form.objectiveId) {
    formError.value = t.value.errors.selectObjective
    return
  }

  try {
    const event = await calendarStore.createHold({
      title: form.title || t.value.untitled,
      description: form.description || null,
      location: form.location || null,
      objective_id: form.objectiveId,
      start_at: startAt.toISOString(),
      end_at: endAt.toISOString(),
    })
    activeHoldId.value = event.id
    formNotice.value = 'Hold created. Lock to confirm.'
  } catch {
    formError.value = 'Unable to create hold.'
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
    await calendarStore.lockEvent(activeHoldId.value)
    formNotice.value = 'Event locked.'
  } catch {
    formError.value = 'Unable to lock event.'
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
  if (event.review_status !== 'changes_requested') {
    return false
  }
  return Boolean(user.value?.department_id) && user.value.department_id === event.department_id
}

const prefillFormFromEvent = (event) => {
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
  editEventId.value = event.id
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
  let list = displayEvents.value
    .filter((event) => event.status === 'locked')
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

const formatDateLocal = (value) => {
  const date = value ? new Date(value) : new Date()
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const toIsoDate = (value) => {
  if (!value) {
    return null
  }
  const date = new Date(`${value}T00:00:00`)
  if (Number.isNaN(date.getTime())) {
    return null
  }
  return date.toISOString()
}

const reviewStatusLabel = (status) => {
  if (!status) {
    return t.value.statusPending
  }
  const map = {
    pending: t.value.statusPending,
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

const reviewTab = (eventId) => activeReviewTab[eventId] || 'history'

const setReviewTab = (eventId, tab) => {
  activeReviewTab[eventId] = tab
}

const historyForEvent = (event) => {
  const list = event?.histories || []
  return [...list].sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
}

const historyFieldLabel = (field) => {
  return t.value.historyFields?.[field] || field
}

const formatHistoryValue = (value) => {
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  if (typeof value === 'string' && !Number.isNaN(Date.parse(value))) {
    return new Date(value).toLocaleString(locale.value)
  }
  return String(value)
}

const formatHistoryMeta = (entry) => {
  const actionLabel = t.value.historyActions?.[entry.action] || entry.action
  const userName = entry.user?.name || '—'
  const date = entry.created_at ? new Date(entry.created_at).toLocaleString(locale.value) : '—'
  return `${actionLabel} · ${userName} · ${date}`
}

const notesForEvent = (event) => {
  const list = event?.notes || []
  return [...list].sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
}

const isSecretaryNote = (note) => {
  const role = note?.author?.role
  return role === 'secretary' || role === 'superadmin'
}

const noteClass = (note) => (isSecretaryNote(note) ? 'note-item--incoming' : 'note-item--outgoing')

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
  if (unseenNotes.value.length === 0) {
    await fetchUnseenNotes()
  }
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

const openPublishAcceptedModal = () => {
  publishAcceptedOpen.value = true
}

const closePublishAcceptedModal = () => {
  publishAcceptedOpen.value = false
}

const markNoteSeen = async (note) => {
  try {
    await calendarStore.markNoteSeen(note.id)
    unseenNotes.value = unseenNotes.value.filter((item) => item.id !== note.id)
  } catch {
    // no-op
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
  await markNoteSeen(note)
  if (event) {
    openEventDetails(event)
  }
}

const handleDetailsReply = async ({ reply }) => {
  const eventId = selectedEvent.value?.id
  if (!eventId) {
    return
  }
  try {
    await calendarStore.replyNote(eventId, { reply })
  } catch {
    // no-op
  }
}

const toggleNotes = (eventId) => {
  const next = new Set(expandedNotesIds.value)
  if (next.has(eventId)) {
    next.delete(eventId)
  } else {
    next.add(eventId)
  }
  expandedNotesIds.value = next
}

const isNotesExpanded = (eventId) => expandedNotesIds.value.has(eventId)

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

const canAddNote = computed(() => isSecretary.value || isSuperAdmin.value)

const submitReview = async (event, status, noteOverride = '', publishToFeedFlag = false, acceptedAt = null) => {
  reviewErrors[event.id] = ''
  const note = noteOverride || ''
  if ((status === 'denied' || status === 'changes_requested') && !note.trim()) {
    reviewErrors[event.id] = t.value.reviewNoteRequired
    return
  }

  try {
    await calendarStore.reviewEvent(event.id, {
      review_status: status,
      review_note: note || null,
      publish_to_feed: publishToFeedFlag,
      accepted_at: acceptedAt,
    })
    reviewErrors[event.id] = ''
    reviewModalMessage.value = t.value.errors.reviewUpdated
    reviewModalOpen.value = true
  } catch {
    reviewErrors[event.id] = t.value.errors.reviewFailed
    reviewModalMessage.value = reviewErrors[event.id]
    reviewModalOpen.value = true
  }
}

const publishAcceptedAll = async () => {
  const range = reviewRange.value
  if (!range) {
    return
  }
  if (!window.confirm(t.value.publishAcceptedConfirm)) {
    return
  }

  try {
    const response = await calendarStore.publishAccepted({
      start: range.start.toISOString(),
      end: range.end.toISOString(),
    })
    reviewModalMessage.value = `${t.value.publishAcceptedSuccess} (${response.updated || 0})`
    reviewModalOpen.value = true
    publishAcceptedOpen.value = false
  } catch {
    reviewModalMessage.value = t.value.publishAcceptedFailed
    reviewModalOpen.value = true
  }
}

const closeReviewModal = () => {
  reviewModalOpen.value = false
  reviewModalMessage.value = ''
}

const openStatusModal = (event) => {
  activeReviewEvent.value = event
  statusSelection.value = event.review_status || 'pending'
  statusNote.value = ''
  publishToFeed.value = Boolean(event.publish_to_feed)
  statusAcceptedAt.value = statusSelection.value === 'approved'
    ? formatDateLocal(event.accepted_at)
    : ''
  statusModalOpen.value = true
}

const closeStatusModal = () => {
  statusModalOpen.value = false
  statusNote.value = ''
  publishToFeed.value = false
  statusAcceptedAt.value = ''
  activeReviewEvent.value = null
}

const submitStatusModal = async () => {
  if (!activeReviewEvent.value) {
    return
  }
  await submitReview(
    activeReviewEvent.value,
    statusSelection.value,
    statusNote.value,
    statusSelection.value === 'approved' ? publishToFeed.value : false,
    statusSelection.value === 'approved' ? toIsoDate(statusAcceptedAt.value) : null
  )
  closeStatusModal()
}

const openNotesModal = (event) => {
  activeReviewEvent.value = event
  noteDraft.value = ''
  notesModalOpen.value = true
}

const closeNotesModal = () => {
  notesModalOpen.value = false
  noteDraft.value = ''
  activeReviewEvent.value = null
}

const submitNoteModal = async () => {
  if (!activeReviewEvent.value) {
    return
  }
  const note = noteDraft.value
  if (!note || !note.trim()) {
    return
  }
  try {
    await calendarStore.addNote(activeReviewEvent.value.id, { note: note.trim() })
    noteDraft.value = ''
    closeNotesModal()
    reviewModalMessage.value = t.value.errors.noteSent
    reviewModalOpen.value = true
  } catch {
    closeNotesModal()
    reviewModalMessage.value = t.value.errors.noteSendFailed
    reviewModalOpen.value = true
  }
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

watch(currentMonth, async () => {
  monthSelection.value = `${currentMonth.value.getFullYear()}-${String(currentMonth.value.getMonth() + 1).padStart(2, '0')}`
  await loadMonth()
})

watch(statusSelection, (next) => {
  if (next !== 'approved') {
    publishToFeed.value = false
    statusAcceptedAt.value = ''
    return
  }
  if (!statusAcceptedAt.value) {
    statusAcceptedAt.value = formatDateLocal(new Date())
  }
})

onMounted(async () => {
  handleResize()
  window.addEventListener('resize', handleResize)
  objectives.value = await objectiveApi.list()
  await loadDepartments(user.value?.church_id)
  monthSelection.value = `${currentMonth.value.getFullYear()}-${String(currentMonth.value.getMonth() + 1).padStart(2, '0')}`
  await loadMonth()
  await fetchUnseenNotes()
  calendarStore.connectRealtime()
})

watch(user, async (next) => {
  if (next?.church_id) {
    await loadDepartments(next.church_id)
  }
  await fetchUnseenNotes()
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
})
</script>
