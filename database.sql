USE phpmyadmin-error-report;

DROP TABLE IF EXISTS reports;
CREATE TABLE reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message VARCHAR(150),
    phpma_version VARCHAR(50),
    status VARCHAR(20),
    steps VARCHAR(20),
    stacktrace TEXT,
    full_report TEXT,
    created DATETIME DEFAULT NULL,
    modified DATETIME DEFAULT NULL
);
