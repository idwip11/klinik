CREATE TABLE IF NOT EXISTS lab_number_sequences (
    examined_on DATE NOT NULL,
    next_sequence INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (examined_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
