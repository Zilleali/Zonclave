-- Zonclave - authoritative registry and admin log (PostgreSQL)
-- CLAUDE.md Sections 7 and 17
--
-- ppsk_groups is the single source of truth. radcheck/radreply rows
-- (01_radius.sql) are generated from it through the Section 23.1 path,
-- never maintained independently. Must stay in lockstep with the
-- install_db() block in installer/install.sh.

CREATE TABLE IF NOT EXISTS ppsk_groups (
  id SERIAL PRIMARY KEY,
  label VARCHAR(128) NOT NULL,
  radius_username VARCHAR(64) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  vlan_id INT NOT NULL,
  subnet VARCHAR(32) NOT NULL,
  wireguard_interface VARCHAR(32) NOT NULL,
  wireguard_gateway VARCHAR(32) NOT NULL,
  opnsense_interface VARCHAR(64),
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_ppsk_groups_vlan   ON ppsk_groups (vlan_id);
CREATE INDEX IF NOT EXISTS idx_ppsk_groups_status ON ppsk_groups (status);
CREATE INDEX IF NOT EXISTS idx_ppsk_groups_label  ON ppsk_groups (label);

CREATE TABLE IF NOT EXISTS admin_log (
  id SERIAL PRIMARY KEY,
  ts TIMESTAMP DEFAULT NOW(),
  admin_user VARCHAR(128),
  action VARCHAR(64) NOT NULL,
  target_ppsk_id INT,
  detail TEXT
);
CREATE INDEX IF NOT EXISTS idx_admin_log_ts     ON admin_log (ts);
CREATE INDEX IF NOT EXISTS idx_admin_log_target ON admin_log (target_ppsk_id);
CREATE INDEX IF NOT EXISTS idx_admin_log_action ON admin_log (action);
