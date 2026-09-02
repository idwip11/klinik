CREATE TABLE IF NOT EXISTS examination_panel_selections (
    examination_id BIGINT UNSIGNED NOT NULL,
    panel_id BIGINT UNSIGNED NOT NULL,
    is_auto_included TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (examination_id, panel_id),
    KEY examination_panel_selections_panel_index (panel_id),
    CONSTRAINT examination_panel_selections_examination_foreign FOREIGN KEY (examination_id) REFERENCES examinations(id) ON DELETE CASCADE,
    CONSTRAINT examination_panel_selections_panel_foreign FOREIGN KEY (panel_id) REFERENCES examination_panels(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
