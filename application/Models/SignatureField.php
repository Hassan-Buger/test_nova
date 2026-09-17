<?php

namespace Application\Models;

use Application\Core\Model;
use PDO;

class SignatureField extends Model
{
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM signature_fields WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function getByRequestId(int $requestId): array
    {
        $stmt = $this->db->prepare("
            SELECT sf.*, ss.name AS signer_name, ss.email AS signer_email, ss.role AS signer_role
            FROM signature_fields sf
            JOIN signature_signers ss ON ss.id = sf.signer_id
            WHERE sf.request_id = :request_id
            ORDER BY sf.page ASC, sf.position_y ASC, sf.position_x ASC
        ");
        $stmt->execute(['request_id' => $requestId]);
        return $stmt->fetchAll();
    }

    public function getBySignerId(int $signerId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM signature_fields 
            WHERE signer_id = :signer_id 
            ORDER BY page ASC, position_y ASC, position_x ASC
        ");
        $stmt->execute(['signer_id' => $signerId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO signature_fields (
                request_id, signer_id, type, page,
                position_x, position_y, width, height,
                custom_text, signature_data, signature_type,
                required, inserted
            ) VALUES (
                :request_id, :signer_id, :type, :page,
                :position_x, :position_y, :width, :height,
                :custom_text, :signature_data, :signature_type,
                :required, :inserted
            )
        ");
        $stmt->execute([
            'request_id'     => (int)$data['request_id'],
            'signer_id'      => (int)$data['signer_id'],
            'type'           => $data['field_type'] ?? $data['type'] ?? 'signature',
            'page'           => max(1, (int)($data['page_number'] ?? $data['page'] ?? 1)),
            'position_x'     => (float)($data['position_x'] ?? 0.0),
            'position_y'     => (float)($data['position_y'] ?? 0.0),
            'width'          => (float)($data['width'] ?? 20.0),
            'height'         => (float)($data['height'] ?? 6.0),
            'custom_text'    => !empty($data['custom_label']) ? (string)$data['custom_label'] : (!empty($data['custom_text']) ? (string)$data['custom_text'] : null),
            'signature_data' => !empty($data['field_value']) ? (string)$data['field_value'] : (!empty($data['signature_data']) ? (string)$data['signature_data'] : null),
            'signature_type' => !empty($data['signature_type']) ? (string)$data['signature_type'] : null,
            'required'       => isset($data['is_required']) ? (int)(bool)$data['is_required'] : (isset($data['required']) ? (int)(bool)$data['required'] : 1),
            'inserted'       => !empty($data['inserted']) ? 1 : 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function saveFieldValue(int $id, ?string $signatureData, ?string $signatureType, ?string $customText): bool
    {
        $stmt = $this->db->prepare("
            UPDATE signature_fields 
            SET signature_data = :sig_data,
                signature_type = :sig_type,
                custom_text = :custom_text,
                inserted = 1,
                signed_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            'sig_data'    => $signatureData,
            'sig_type'    => $signatureType,
            'custom_text' => $customText,
            'id'          => $id,
        ]);
    }

    public function clearFieldValue(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE signature_fields 
            SET signature_data = NULL,
                signature_type = NULL,
                custom_text = NULL,
                inserted = 0,
                signed_at = NULL
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }

    public function hasUnsignedRequiredFields(int $signerId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM signature_fields 
            WHERE signer_id = :signer_id 
              AND required = 1 
              AND inserted = 0
        ");
        $stmt->execute(['signer_id' => $signerId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
