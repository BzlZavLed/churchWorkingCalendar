# Church Working Calendar

Church Working Calendar is a scheduling and approval platform for church departments. Teams create events, lock them for review, and secretaries approve, request changes, or deny. Approved events can optionally be published to a public ICS feed for calendar subscriptions.

## Project Structure

- `app/`
  - Laravel backend (API controllers, models, policies, events).
- `routes/`
  - API endpoints (`routes/api.php`) for auth, events, objectives, superadmin, and public feeds.
- `database/`
  - Migrations and seeders.
- `frontend/`
  - Vue 3 SPA (Vite + Pinia). Main views are in `frontend/src/components/`.
- `public/`
  - Built SPA assets in `public/spa/`.

## Key Workflows

### Event lifecycle
1. Department creates a hold.
2. Department locks the event.
3. Secretary reviews (approve/deny/changes).
4. Final outcome is recorded (`final_validation`).
5. If approved and marked “publish to feed,” the event appears in the public ICS feed.

### Notes workflow
- Secretaries can leave notes on events.
- Departments can reply once per event.
- Unseen notes are tracked for department users.

## Public Feed

- JSON feed: `GET /api/public/churches/{slug}/events`
- ICS feed: `GET /api/public/churches/{slug}/events.ics`
- Legacy (requires `church_id` query): `GET /api/public/events?church_id=...`
- Example subscription URL: `https://www.mychurchadmin.net/api/public/churches/my-church/events.ics`

Only events with:
- `final_validation = accepted`
- `publish_to_feed = true`

are included in the public feed.

## Frontend

- Calendar: `frontend/src/components/CalendarView.vue`
- Reports: `frontend/src/components/ReportsView.vue`
- Objectives: `frontend/src/components/ObjectivesView.vue`
- Superadmin: `frontend/src/components/superadmin/`

Language selection persists via `localStorage` key `ui_locale`.

## Development

Backend:
```bash
composer install
php artisan migrate
php artisan serve
```

Frontend:
```bash
cd frontend
npm install
npm run dev
```

## Seeders

- Update Benjamin password:
```bash
php artisan db:seed --class=UpdateBenjaminPasswordSeeder
```

## Propuesta de proyecto (ES)

### Título
Digitalización del plan de trabajo por departamento

### 1. Resumen Ejecutivo
Este proyecto propone implementar una plataforma digital para la planificación, aprobación y seguimiento del plan de trabajo de cada departamento de la iglesia. El sistema reemplaza procesos en papel por un flujo digital, permitiendo a cada departamento organizarse con anticipación, a la secretaría validar y aprobar los eventos, y a la administración mantener visibilidad total. El resultado esperado es una operación más eficiente, con menos errores, mayor transparencia y mejor coordinación interdepartamental.

### 2. Problema Actual
El manejo manual del plan de trabajo genera:
- Demoras en la aprobación y comunicación entre áreas.
- Riesgo de pérdida o duplicidad de información.
- Dificultad para planificar con anticipación.
- Falta de trazabilidad sobre quién solicitó, revisó o aprobó cambios.
- Escasa visibilidad global del plan de trabajo institucional.

### 3. Objetivo General
Digitalizar la gestión del plan de trabajo para mejorar la coordinación y la planificación estratégica entre todos los departamentos de la iglesia.

### 4. Objetivos Específicos
- Sustituir procesos en papel por un flujo digital estructurado.
- Permitir a cada departamento crear y gestionar su plan de trabajo con anticipación.
- Establecer un proceso claro de revisión y aprobación por la secretaría.
- Proveer un calendario común y actualizado del plan de trabajo de toda la organización.
- Generar historial de cambios, notas y trazabilidad por cada evento aprobado.

### 5. Alcance del Proyecto
El sistema incluirá:
- Registro y edición del plan de trabajo por departamento.
- Flujo de revisión: aprobación, solicitud de cambios o rechazo de eventos.
- Alertas en la plataforma y notas entre secretaría y departamentos.
- Publicación de eventos aceptados en un feed ICS público por iglesia.
- Reportes por departamento para seguimiento de objetivos y ejecución.

### 6. Beneficios Clave
- Eficiencia operativa: menos tiempos de aprobación y coordinación más rápida.
- Planificación anticipada: departamentos con acceso directo a su plan de trabajo.
- Transparencia: historial completo de cambios y decisiones.
- Reducción de errores: un único sistema centralizado.
- Escalabilidad: el modelo se replica fácilmente para nuevas iglesias o departamentos.

### 7. Justificación
La transición de procesos en papel a un sistema digital es necesaria para responder al crecimiento organizacional y a la necesidad de coordinación moderna. Este proyecto habilita una gestión más ágil del plan de trabajo, con acceso inmediato a información confiable, fomentando la colaboración y la planificación estratégica. Además, asegura que todas las áreas puedan organizar su trabajo con claridad, sin depender de procesos manuales que hoy generan retrasos y pérdida de control.

### 8. Resultados Esperados
- Reducción del tiempo de aprobación del plan de trabajo.
- Mayor visibilidad de la programación mensual por departamento.
- Mejor coordinación entre secretaría y departamentos.
- Reportes claros para la toma de decisiones administrativas.

### 9. Definición de Roles
- Superadmin: Configura iglesias, departamentos y usuarios a nivel global. Administra permisos y parámetros generales del sistema.
- Admin: Administra usuarios y datos dentro de su iglesia. Supervisa el plan de trabajo general y mantiene orden interno.
- Secretario: Revisa, aprueba o solicita cambios en los eventos de los departamentos. Añade notas y da seguimiento al historial.
- Miembro (Departamento): Crea y actualiza el plan de trabajo de su área, responde a observaciones de secretaría y da seguimiento a cambios solicitados.

## Guía de uso por iglesia (Tutorial)

### 1) Configuración inicial (Superadmin/Admin)
- Crear la iglesia (si aplica) y configurar los departamentos.
- Registrar usuarios y asignar roles (Admin, Secretaría, Miembro).
- Cada usuario queda asociado a su iglesia; los departamentos pertenecen a esa iglesia.

### 2) Definir Objetivos por Departamento (Miembro/Admin)
- Cada departamento crea sus objetivos (metas o métricas anuales).
- Los objetivos sirven como base para planificar el plan de trabajo del departamento.

### 3) Crear Plan de Trabajo / Eventos (Miembro)
- Cada departamento crea eventos relacionados a sus objetivos.
- Los eventos se organizan en el calendario y quedan listos para revisión.

### 4) Revisión y Aprobación (Secretaría)
- Secretaría inicia sesión y ve todos los eventos de todos los departamentos.
- Puede revisar por departamento y por objetivo.
- Para cada evento puede:
  - Aceptar
  - Rechazar
  - Solicitar cambios

### 5) Publicación al Calendario (Secretaría)
- Si un evento es aceptado, Secretaría puede marcar “Publicar al feed”.
- El evento se publica en el feed ICS público de la iglesia.
- Los clientes de calendario sincronizan periódicamente el feed y reflejan los cambios.

### 6) Reporte y seguimiento
- Se puede generar lista de eventos aprobados por departamento/objetivo.
- Al final del ciclo, se puede imprimir el listado de eventos.
