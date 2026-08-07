CREATE TABLE IF NOT EXISTS madapt_portals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  url VARCHAR(500) NOT NULL,
  description VARCHAR(500) NULL,
  logo_path VARCHAR(500) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_active_sort (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO madapt_portals (name,url,description,logo_path,active,sort_order)
SELECT 'MADAPT','https://madapt.es','MADAPT main portal','',1,1
WHERE NOT EXISTS (SELECT 1 FROM madapt_portals WHERE name='MADAPT');
