SET NAMES utf8;

CREATE TABLE clients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL,
    status ENUM('active', 'prospect', 'archived') NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO clients (company, email, status) VALUES
('Студия Север', 'hello@north.example', 'active'),
('Мастерская Линия', 'team@line.example', 'active'),
('Бюро Контур', 'office@outline.example', 'prospect'),
('Проект Маяк', 'contact@beacon.example', 'prospect'),
('Архивный пример', 'archive@sample.example', 'archived');
