USE phpmyadmin-error-report;

DROP TABLE IF EXISTS reports;
CREATE TABLE reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    error_message VARCHAR(150),
    error_name VARCHAR(50),
    pma_version VARCHAR(30),
    php_version VARCHAR(15),
    browser VARCHAR(40),
    user_os VARCHAR(30),
    server_software VARCHAR(100),
    status VARCHAR(20),
    steps TEXT,
    stacktrace TEXT,
    full_report TEXT,
    related_report_id INT UNSIGNED,
    created DATETIME DEFAULT NULL,
    modified DATETIME DEFAULT NULL
);

DROP TABLE IF EXISTS developers;
CREATE TABLE developers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    github_id INT UNSIGNED,
    full_name VARCHAR(50),
    email VARCHAR(70),
    gravatar_id VARCHAR(100),
    access_token VARCHAR(100),
    has_commit_access BIT(1),
    created DATETIME DEFAULT NULL,
    modified DATETIME DEFAULT NULL
);
