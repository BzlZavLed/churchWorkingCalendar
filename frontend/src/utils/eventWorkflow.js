const actions = {
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
