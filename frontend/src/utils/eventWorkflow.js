const actions = {
  conference_published: ['Conference event published', 'Evento de conferencia publicado'],
  conference_unpublished: ['Conference event unpublished', 'Evento de conferencia retirado de publicación'],
  conference_saved: ['Conference event saved', 'Evento de conferencia guardado'],
  cancelled: ['Cancelled', 'Cancelado'],
  submitted: ['Submitted', 'Enviado'],
  accepted: ['Accepted', 'Aceptado'],
  rejected: ['Rejected', 'Rechazado'],
  changes_requested: ['Changes requested', 'Cambios solicitados'],
  resubmitted: ['Resubmitted', 'Reenviado'],
  opening_requested: ['Opening requested', 'Apertura solicitada'],
  reopened: ['Reopened for editing', 'Reabierto para editar'],
  update: ['Updated', 'Actualizado'],
  review: ['Reviewed', 'Revisado'],
  publish_batch: ['Published', 'Publicado'],
}

export const eventActionLabel = (action, locale) => actions[action]?.[locale === 'es' ? 1 : 0] || action

export const eventReviewLabel = (status, locale) => ({
  pending: ['Pending', 'Pendiente'], resubmitted: ['Resubmitted', 'Reenviado'],
  approved: ['Accepted', 'Aceptado'], denied: ['Rejected', 'Rechazado'],
  changes_requested: ['Changes requested', 'Cambios solicitados'],
})[status]?.[locale === 'es' ? 1 : 0] || status
