# DMS external API (core banking and other source systems)

The ERP has no Swagger; its API references are Markdown plus a Postman collection, and this follows that.
Import `BRERP_DMS_External_API.postman_collection.json` (with `BRERP_Local_Environment.postman_environment.json`,
`base_url` = `http://localhost:8000/api`) to try every call below.

CBS reaches this through the gateway (its own Swagger lists the same calls under `api/v1/erp/dms`); the gateway
forwards to the calls here. Routes: `routes/integrations/dms.php`, controller
`App\Http\Controllers\DMS\API\ExternalDocumentController`.

## Model

- A **source system** (Settings > DMS > Source Systems) is an external system such as CBS. Its **service user** is the
  ERP account the gateway logs in as; documents it files are owned by that account. The person who acted in the
  external system is sent as `actor` / `actorName` and kept as `source.*` document attributes and in the activity log.
- An **event type** (Settings > DMS > Event Types) is a business occasion in that system: a document type (which
  fixes repository, extensions, size, expiry rule), the kind of record it belongs to (`ACCOUNT`, `APPLICATION`, `LOAN`),
  the screens (module ids) that can raise it, its properties (the form), and the default reviewer and validation type.
- A document is found by `(source system, record type, record id)`.

## Authentication

`POST /api/v1/dms/external/{system}/token` with `{"userId","password"}` -> a 60-minute bearer token
(`data.token`, `data.expiresAt`). Every other call sends `Authorization: Bearer <token>`. The token works only for
that system, only while its user is still the system's service user and active. Any failure is a plain 401.
Login is limited to 10 attempts a minute per IP. Expired tokens are pruned daily.

## Calls (all under `/api/v1/dms/external/{system}`)

| Call | Purpose |
|---|---|
| `GET events?module=&entityType=` | Events for a screen, with document type rules, review/validation availability and the properties to render (`type`: string, integer, float, boolean, datetime, time, list) |
| `GET documents?entityType=&entityId=&event=&module=` | Documents for one record, each with `status` |
| `GET documents/{id}` | One document (404 if another system's) |
| `POST documents/{id}/link` `{actor}` | 5-minute signed URL for the current version (`GET /api/v1/dms/external/documents/{id}/content?...`, no token) |
| `POST documents` (multipart) | `file`, `event`, `module`, `entityId`, `actor`, `actorName`, `properties` (JSON list of `{name,value}`), `expiryDate`, `notes` |
| `POST documents/{id}/replace` (multipart) | `file`, `actor`, `actorName`, `remark` |
| `POST documents/{id}/review` | `actor`, `actorName`, `note`, `reviewer` (DMS user ID, overrides the event default) |
| `POST documents/{id}/validation` | `actor`, `actorName`, `note` |

## Status

Each document carries `status`: `state` (`active`, `validated`, `legal_hold`, `under_review`, `pending_validation`,
`archived`, `checked_out`), the flags `onHold`, `underReview`, `archived`, `checkedOut`, `pendingValidation`,
`validated`, `canReplace`, `lockReason`, when a hold or review was lifted (`holdReleasedOn`, `lastReviewedOn`), and the
latest `review` (`pending`/`reviewed`, reviewer, comment) and `validation` (`pending`/`submitted`/`approved`/`rejected`,
rejection reason).

## Errors

| Status | Meaning |
|---|---|
| 401 | No/invalid/expired token, wrong system, or the service user changed or is inactive |
| 404 | Document not found (or filed by another system) |
| 409 | Locked. `reason`: `legal_hold`, `under_review`, `archived`, `checked_out`, `pending_validation` |
| 422 | Validation failed (`errors` keyed like `properties.cbs.class`, `file`, `event`), or nothing to route to (no reviewer / validation type) |

Replacing, requesting a review and requesting validation are refused while the document is locked; legal hold is
enforced here whichever system asks.
