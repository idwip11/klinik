ALTER TABLE users
    MODIFY role ENUM('registration', 'lab_staff', 'supervisor_admin', 'admin', 'perawat') NOT NULL;

UPDATE users SET role = CASE role
    WHEN 'supervisor_admin' THEN 'admin'
    ELSE 'perawat'
END;

ALTER TABLE users
    MODIFY role ENUM('admin', 'perawat') NOT NULL;
