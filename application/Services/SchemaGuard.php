<?php

namespace Application\Services;

use Application\Core\Database;
use Application\Exceptions\SystemSetupException;

final class SchemaGuard
{
    public static function assertClientCsvReady(): void
    {
        $db = Database::getInstance();
        $required = ['id', 'entity_id', 'user_id', 'name', 'email', 'phone', 'is_primary', 'needs_contact_details'];
        $stmt = $db->query("SELECT LOWER(column_name) FROM information_schema.columns WHERE table_schema = DATABASE() AND LOWER(table_name) = 'entity_contacts'");
        $available = array_map('strtolower', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        $stmt->closeCursor();
        if (array_diff($required, $available)) {
            throw new SystemSetupException('Client CSV schema migration is incomplete: entity_contacts is missing required columns.');
        }
        $trackingStmt = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND LOWER(table_name) = 'client_csv_imports'");
        $tracking = (int)$trackingStmt->fetchColumn();
        $trackingStmt->closeCursor();
        if (!$tracking) {
            throw new SystemSetupException('Client CSV duplicate-tracking migration is incomplete.');
        }

        self::assertImportBatchDeletionReady();
    }

    public static function assertDirectorImporterReady(): void
    {
        self::assertClientCsvReady();
        $db = Database::getInstance();
        $required = ['original_full_name', 'director_utr', 'address', 'id_number', 'ch_verification_number', 'last_director_import_id'];
        $stmt = $db->query("SELECT LOWER(column_name) FROM information_schema.columns WHERE table_schema = DATABASE() AND LOWER(table_name) = 'entity_contacts'");
        $available = array_map('strtolower', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        $stmt->closeCursor();
        if (array_diff($required, $available)) {
            throw new SystemSetupException('Directors Importer schema migration is incomplete.');
        }
    }

    public static function assertImportBatchDeletionReady(): void
    {
        $db = Database::getInstance();
        $tables = ['users', 'clients', 'client_entities', 'entity_directors', 'documents', 'document_requests', 'messages', 'deadlines', 'meetings', 'client_csv_imports'];
        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND LOWER(table_name) = LOWER(:table_name) AND LOWER(column_name) = 'deleted_at'");
        foreach ($tables as $table) {
            $stmt->execute(['table_name' => $table]);
            $found = (int)$stmt->fetchColumn();
            $stmt->closeCursor();
            if (!$found) {
                throw new SystemSetupException('CSV batch deletion schema is incomplete: ' . $table . '.deleted_at is missing.');
            }
        }
    }

    public static function assertDeleteSchemaReady(): void
    {
        self::assertImportBatchDeletionReady();
    }

    public static function assertSignaturesReady(): void
    {
        $db = Database::getInstance();
        $tables = ['signature_requests', 'signature_signers', 'signature_fields', 'signature_audit_events'];
        foreach ($tables as $table) {
            $stmt = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND LOWER(table_name) = '{$table}'");
            $found = (int)$stmt->fetchColumn();
            $stmt->closeCursor();
            if (!$found) {
                throw new SystemSetupException("Digital Signatures schema migration is incomplete: {$table} is missing.");
            }
        }
    }
}
