UPDATE examination_panels
SET is_always_included = 1, updated_at = UTC_TIMESTAMP()
WHERE code = 'widal';

UPDATE examination_panels
SET is_always_included = 0,
    description = 'Pemeriksaan golongan darah, rhesus, dan serologi.',
    updated_at = UTC_TIMESTAMP()
WHERE code = 'immunoserology';
