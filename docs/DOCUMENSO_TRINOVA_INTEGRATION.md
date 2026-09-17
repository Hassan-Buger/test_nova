# TriNova Client Portal & Documenso Digital Signature Integration Architecture

## 1. Executive Summary

This document specifies the technical architecture for integrating the digital signature / e-signature engine from Documenso into the **TriNova Client Portal**. 

TriNova remains the primary application. Digital signatures are integrated natively into TriNova's existing MVC structure, MySQL database, role-based entity authorization, file storage, audit logging, and notification systems. The user experiences digital signatures as an integral capability of TriNova without external redirects, separate logins, or third-party branding.

---

## 2. Component Analysis & Strategy Matrix

| Component Domain | TriNova Existing Implementation | Documenso Implementation | Integration Strategy | Disposition (Reuse / Adapt / Replace / Reject) |
| :--- | :--- | :--- | :--- | :--- |
| **Identity & Users** | `users` table (`client`, `staff`), `clients`, `client_entities`, `entity_directors` | NextAuth/Prisma `User`, `Organisation`, `Team` | Keep TriNova as the single source of truth. Signers link to `users.id` if registered, or external email/name. | **Reuse TriNova / Reject Documenso User System** |
| **Document Storage** | `documents` table, `FileStorageService` (`storage/uploads/`) | S3 / MinIO / Local FS, `documentData` | Use TriNova's `FileStorageService` and `documents` records. Original PDF is preserved; signed PDF is stored as a new document version. | **Reuse TriNova Storage / Adapt Documenso Stamped Strategy** |
| **Entity Authorization** | `EntityAccess` model (`client_id`, `entity_id`, `entity_directors`) | Team / Organization RBAC | Enforce TriNova's `EntityAccess` on all signature requests, document access, and staff operations. | **Reuse TriNova EntityAccess / Reject Documenso Org Model** |
| **Field System** | None (new capability) | Prisma `Field` model (type, page, positionX%, positionY%, width%, height%) | Adapt Documenso percentage-based field coordinates into TriNova's `signature_fields` table. | **Adapt Documenso Field Model to TriNova MySQL** |
| **Signer & Token Model** | None | Prisma `Recipient` model (token, email, name, role, order, status) | Create `signature_signers` table with unguessable 64-char hex tokens (`bin2hex(random_bytes(32))`). | **Adapt Documenso Recipient/Token Pattern** |
| **PDF Processing** | None (viewing/downloading only) | `@cantoo/pdf-lib` in Node.js (flattens, embeds PNGs/text, appends cert) | Implement native PHP PDF sealing engine using `setasign/fpdi` & `setasign/fpdf`. Operates directly within PHP 8.2+ without Node runtime dependency. | **Adapt Documenso PDF Sealing Pipeline to PHP FPDI** |
| **Audit Logging** | `audit_log` table, `AuditService::log()` | Prisma `DocumentAuditLog` with structured JSON metadata | Integrate signature audit events into both TriNova's `audit_log` and dedicated `signature_audit_events` with SHA-256 hashes and IP tracking. | **Reuse TriNova AuditService + Adapt Documenso Event Types** |
| **Notifications** | `notifications` table, `NotificationService` (Resend API + local mail.log) | Documenso Email Jobs | Use TriNova's `NotificationService` for signature invitation, completion, and reminder emails + in-app notification badges. | **Reuse TriNova NotificationService / Reject Documenso Jobs** |
| **UI & Styling** | PHP Views, Tailwind CSS, Vanilla JS, Modals | Remix / React / Radix / Tailwind | Implement native TriNova views in Tailwind CSS and modern Vanilla JS (PDF.js + HTML5 Canvas signature pad). | **Adapt Documenso UI Workflows to TriNova Views & Tailwind** |

---

## 3. Database Architecture (MySQL)

All tables use `InnoDB`, `utf8mb4_unicode_ci`, foreign keys with appropriate cascade/set null rules, and strict indexes.

### 3.1 `signature_requests`
Represents an envelope / signature workflow created around a TriNova document.
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `document_id` INT UNSIGNED NOT NULL (FK `documents.id` ON DELETE CASCADE)
- `client_id` INT UNSIGNED NOT NULL (FK `clients.id` ON DELETE CASCADE)
- `entity_id` INT UNSIGNED NULL (FK `client_entities.id` ON DELETE SET NULL)
- `created_by_user_id` INT UNSIGNED NOT NULL (FK `users.id` ON DELETE RESTRICT)
- `title` VARCHAR(255) NOT NULL
- `status` ENUM('draft', 'pending', 'completed', 'declined', 'expired', 'cancelled') NOT NULL DEFAULT 'draft'
- `signing_order` ENUM('parallel', 'sequential') NOT NULL DEFAULT 'parallel'
- `signed_document_id` INT UNSIGNED NULL (FK `documents.id` ON DELETE SET NULL)
- `qr_token` VARCHAR(64) NULL UNIQUE
- `allow_drawn_signature` TINYINT(1) NOT NULL DEFAULT 1
- `allow_typed_signature` TINYINT(1) NOT NULL DEFAULT 1
- `allow_upload_signature` TINYINT(1) NOT NULL DEFAULT 1
- `expires_at` DATETIME NULL
- `completed_at` DATETIME NULL
- `declined_at` DATETIME NULL
- `decline_reason` TEXT NULL
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- `deleted_at` DATETIME NULL
- INDEXES: `idx_sig_req_status`, `idx_sig_req_client`, `idx_sig_req_entity`, `idx_sig_req_doc`, `idx_sig_req_deleted_at`

### 3.2 `signature_signers`
Represents signers or recipients assigned to a signature request.
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `request_id` INT UNSIGNED NOT NULL (FK `signature_requests.id` ON DELETE CASCADE)
- `user_id` INT UNSIGNED NULL (FK `users.id` ON DELETE SET NULL)
- `name` VARCHAR(255) NOT NULL
- `email` VARCHAR(255) NOT NULL
- `role` ENUM('signer', 'approver', 'viewer', 'cc') NOT NULL DEFAULT 'signer'
- `signing_order` INT UNSIGNED NOT NULL DEFAULT 1
- `token` VARCHAR(64) NOT NULL UNIQUE
- `status` ENUM('pending', 'signed', 'declined', 'expired') NOT NULL DEFAULT 'pending'
- `read_status` ENUM('not_opened', 'opened') NOT NULL DEFAULT 'not_opened'
- `sent_at` DATETIME NULL
- `opened_at` DATETIME NULL
- `signed_at` DATETIME NULL
- `declined_at` DATETIME NULL
- `decline_reason` TEXT NULL
- `ip_address` VARCHAR(45) NULL
- `user_agent` VARCHAR(255) NULL
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- INDEXES: `idx_signers_req`, `idx_signers_token`, `idx_signers_email`, `idx_signers_status`

### 3.3 `signature_fields`
Represents field positions placed on the document pages.
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `request_id` INT UNSIGNED NOT NULL (FK `signature_requests.id` ON DELETE CASCADE)
- `signer_id` INT UNSIGNED NOT NULL (FK `signature_signers.id` ON DELETE CASCADE)
- `type` ENUM('signature', 'initials', 'name', 'email', 'date', 'text', 'checkbox') NOT NULL DEFAULT 'signature'
- `page` INT UNSIGNED NOT NULL DEFAULT 1
- `position_x` DECIMAL(6, 3) NOT NULL DEFAULT 0.000 (0 to 100% from left)
- `position_y` DECIMAL(6, 3) NOT NULL DEFAULT 0.000 (0 to 100% from top)
- `width` DECIMAL(6, 3) NOT NULL DEFAULT 20.000 (percentage width)
- `height` DECIMAL(6, 3) NOT NULL DEFAULT 6.000 (percentage height)
- `custom_text` TEXT NULL
- `signature_data` LONGTEXT NULL (Base64 data URL for drawn/uploaded image, or string for typed)
- `signature_type` ENUM('drawn', 'typed', 'uploaded') NULL
- `required` TINYINT(1) NOT NULL DEFAULT 1
- `inserted` TINYINT(1) NOT NULL DEFAULT 0
- `signed_at` DATETIME NULL
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- INDEXES: `idx_sig_fields_req`, `idx_sig_fields_signer`, `idx_sig_fields_page`

### 3.4 `signature_audit_events`
Immutable record of signature events for legal compliance and audit verification.
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `request_id` INT UNSIGNED NOT NULL (FK `signature_requests.id` ON DELETE CASCADE)
- `signer_id` INT UNSIGNED NULL (FK `signature_signers.id` ON DELETE SET NULL)
- `user_id` INT UNSIGNED NULL (FK `users.id` ON DELETE SET NULL)
- `event_type` VARCHAR(50) NOT NULL
- `event_description` TEXT NOT NULL
- `metadata` JSON NULL
- `ip_address` VARCHAR(45) NULL
- `user_agent` VARCHAR(255) NULL
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
- INDEXES: `idx_sig_audit_req`, `idx_sig_audit_type`, `idx_sig_audit_created`

---

## 4. End-to-End Signature Lifecycle

```text
[Staff Portal]
  1. Staff views document in TriNova Document Hub.
  2. Clicks "Request Signature".
  3. Form selects Signers (auto-suggested from Client entity directors/contacts).
  4. Staff places Signature / Date / Text fields on PDF preview pages.
  5. Staff clicks "Send Signature Request".
  6. Backend generates unguessable tokens, writes audit log, sends invitation emails via NotificationService.

[Signer Experience]
  7. Signer opens link: https://portal.trinova.example/sign/{token}
  8. Backend validates token, status (not already completed/expired), sequential signing order.
  9. System records 'document_opened' with IP and User-Agent.
 10. Native TriNova signing interface loads PDF with interactive signature fields.
 11. Signer draws on canvas, types, or uploads signature PNG.
 12. Signer reviews and clicks "Finish & Sign".

[PDF Sealing Engine]
 13. Backend validates all required fields are signed.
 14. FPDI imports original PDF pages.
 15. Stamps high-resolution signature PNGs / text at exact page coordinates.
 16. Appends Certificate of Completion page (Audit Trail, SHA-256 document checksums, signers, timestamps, QR code).
 17. Saves signed file in storage/uploads/ as `<hash>.pdf`.
 18. Creates new TriNova document record with filename `<title>_signed.pdf`, status 'Signed'.
 19. Original document remains intact and unaltered.
 20. Sends completion emails to all signers and staff with download links.
```

---

## 5. Security & Authorization

1. **Authorization**:
   - Staff can only manage signature requests for clients/entities they have access to.
   - Clients logged in can only see signature requests belonging to their linked entities (`EntityAccess::canAccessEntity`).
2. **Token Security**:
   - Unguessable 256-bit cryptographically secure random tokens (`bin2hex(random_bytes(32))`).
   - Constant-time token lookup to prevent timing attacks.
   - Tokens expire after request expiry date or when marked cancelled.
3. **Replay & Concurrency Protection**:
   - Atomic database transactions guard state transitions. Once marked `signed` or `completed`, subsequent signing submissions are rejected with 400 Bad Request.
4. **Tamper-Resistance & Audit**:
   - SHA-256 checksum calculated for both the original document and the final signed PDF.
   - Stored in audit events and permanently stamped on the Certificate of Completion.
5. **No Separate Documenso Surface**:
   - Zero Documenso branding, dashboards, or external accounts exposed.
