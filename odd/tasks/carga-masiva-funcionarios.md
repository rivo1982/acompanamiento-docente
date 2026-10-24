# Feature: Bulk staff import and invitations (Vinkured Q18), built to hand back to Vinkured

## Objective
Let a Vinkured Administrador load staff (one by one or from an .xlsx spreadsheet) into the host
tables and invite each person by email to activate their account, so establishments that buy
only the coaching module can onboard their teachers without waiting for the Vinkured team.

## Why
Vinkured decided (docs/alcance-y-pendientes.md:24, CONTEXT.md "Administrador") that the
Administrador loads users one by one or in bulk and each person activates by email invitation,
but only the schema exists: membresias invitation columns, importaciones_usuarios (no model, no
estado values), no invitation send/accept, no RUT validation, no spreadsheet library, no Q18
file spec. Mapping evidence: Vinkured exploration of 2026-10-24 (see alineacion-vinkured.md).

## Decisions (user, 2026-10-24)
- Built by us "a la medida de Vinkured": host tables + importaciones_usuarios + membresia
  invitation flow; coordinated with the Vinkured team to avoid duplicate work.
- Spreadsheet format .xlsx, one row per person, columns in this order:
  RUT | Nombres | Primer apellido | Segundo apellido | Correo | Teléfono | Función.
  Función ∈ docente, directivo, asistente, profesional de apoyo (Vinkured funciones.categoria).
  No fecha de ingreso (null = vigente in Funcionario::scopeVigentes).

## Constraints (from Vinkured)
- Only the Administrador (membresias.es_administrador, gate pattern of ver-panel-usuarios).
- ADR-0005 anti-enumeration: responses and per-row errors must never reveal that an email already
  has an account in another establecimiento.
- Host tables written through host models inside EstablecimientoActual (paraTrabajo for jobs);
  module/host files tagged @host-owned stay untouched — new host-side code is written as new
  classes intended to move into Vinkured.
- Mail: plain-text Mailable sent by a ShouldQueueAfterCommit job with ids only, retries, no
  address leakage in exceptions/logs (pattern of NotificarRespuestaAlApoderado).
- New PHP dependency for .xlsx parsing requires an ADR proposal for Vinkured.
- Audit (Auditoria) allow-lists and accion CHECK are host-owned: propose changes, do not edit.
- Real personal data: Vinkured ticket 03 (legal review) must be resolved before loading real
  data in production.
- Safety: roberto_dev public schema is never modified; tests only in acompanamiento_test.

## Tasks
- [ ] C1 RUT value object/validator (DV mod 11, normalization to Vinkured's '12345678-5' storage
  form) + spreadsheet row parser/validator (pure, no DB) + .xlsx reader behind an interface,
  with dependency choice and ADR draft for Vinkured.
- [ ] C2 Unit invitation: invite one person (persona + funcionario + funcion + usuario +
  membresia invitada), token generation (hashed at rest) + expiry, plain-text mail via queued
  job, accept route + set password → membresia activa; resend/revoke; anti-enumeration.
- [ ] C3 Bulk import: ImportacionUsuario model + estado values, upload, per-row processing
  reusing C2, summary (total/creados/con_error/errores without enumeration leaks).
- [ ] C4 UI (Livewire, Vinkured UI skills) for invite, import and results.
- [ ] C5 Hand-off package for Vinkured: ADRs, migration/CHECK proposals (estado values, audit
  allow-lists), CONTEXT.md glossary entries.

## Progress / Evidence
- Feature doc created on branch feat/vinkured-alignment.

## Next step
C1 — route: delegated writer.
