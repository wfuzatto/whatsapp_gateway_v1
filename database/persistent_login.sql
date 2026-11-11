-- WhatsApp Gateway V1: sessões do painel web persistentes e revogáveis.
-- NÃO armazena senhas, MFA, tokens Meta ou valores brutos do cookie.
CREATE TABLE IF NOT EXISTS whatsapp_persistent_logins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    password_stamp CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uq_wgate_persistent_token (token_hash),
    KEY ix_wgate_persistent_user (user_id, tenant_id),
    KEY ix_wgate_persistent_expiry (expires_at),
    CONSTRAINT fk_wgate_persistent_user FOREIGN KEY (user_id)
        REFERENCES whatsapp_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wgate_persistent_tenant FOREIGN KEY (tenant_id)
        REFERENCES whatsapp_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
