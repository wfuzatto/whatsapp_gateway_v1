-- Execute uma única vez no banco da Ponte V1 via phpMyAdmin.
-- Não modifica as tabelas de conversas existentes.
CREATE TABLE IF NOT EXISTS whatsapp_mobile_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    UNIQUE KEY uq_mobile_token_hash (token_hash),
    KEY ix_mobile_user (tenant_id,user_id,expires_at),
    CONSTRAINT fk_mobile_session_tenant FOREIGN KEY (tenant_id) REFERENCES whatsapp_tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_mobile_session_user FOREIGN KEY (user_id) REFERENCES whatsapp_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_mobile_devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    fcm_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    fcm_token_enc TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    UNIQUE KEY uq_mobile_device_token (fcm_token_hash),
    KEY ix_mobile_device_user (tenant_id,user_id,revoked_at),
    CONSTRAINT fk_mobile_device_tenant FOREIGN KEY (tenant_id) REFERENCES whatsapp_tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_mobile_device_user FOREIGN KEY (user_id) REFERENCES whatsapp_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
