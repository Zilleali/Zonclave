-- Zonclave - FreeRADIUS rlm_sql schema (PostgreSQL)
-- CLAUDE.md Section 8.2
--
-- Mirrors the standard FreeRADIUS 3.x PostgreSQL schema shipped with the
-- freeradius-postgresql package. On the production node the installer loads
-- the package-shipped schema.sql directly; this file exists so a dev or test
-- database can be bootstrapped identically without the package installed.
--
-- radcheck and radreply are a transactional projection of ppsk_groups
-- (Section 7 / Section 23.1). They are never edited directly.

CREATE TABLE IF NOT EXISTS radcheck (
  id        serial PRIMARY KEY,
  username  text NOT NULL DEFAULT '',
  attribute text NOT NULL DEFAULT '',
  op        varchar(2) NOT NULL DEFAULT '==',
  value     text NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS radcheck_username ON radcheck (username, attribute);

CREATE TABLE IF NOT EXISTS radreply (
  id        serial PRIMARY KEY,
  username  text NOT NULL DEFAULT '',
  attribute text NOT NULL DEFAULT '',
  op        varchar(2) NOT NULL DEFAULT '=',
  value     text NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS radreply_username ON radreply (username, attribute);

CREATE TABLE IF NOT EXISTS radgroupcheck (
  id        serial PRIMARY KEY,
  groupname text NOT NULL DEFAULT '',
  attribute text NOT NULL DEFAULT '',
  op        varchar(2) NOT NULL DEFAULT '==',
  value     text NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS radgroupcheck_groupname ON radgroupcheck (groupname, attribute);

CREATE TABLE IF NOT EXISTS radgroupreply (
  id        serial PRIMARY KEY,
  groupname text NOT NULL DEFAULT '',
  attribute text NOT NULL DEFAULT '',
  op        varchar(2) NOT NULL DEFAULT '=',
  value     text NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS radgroupreply_groupname ON radgroupreply (groupname, attribute);

CREATE TABLE IF NOT EXISTS radusergroup (
  id        serial PRIMARY KEY,
  username  text NOT NULL DEFAULT '',
  groupname text NOT NULL DEFAULT '',
  priority  integer NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS radusergroup_username ON radusergroup (username);

-- Accounting is Phase 2 (Section 22), but the table is part of the standard
-- rlm_sql schema and must exist so the shipped FreeRADIUS SQL queries do not
-- error. It stays empty until accounting is enabled.
CREATE TABLE IF NOT EXISTS radacct (
  radacctid          bigserial PRIMARY KEY,
  acctsessionid      text NOT NULL,
  acctuniqueid       text NOT NULL UNIQUE,
  username           text,
  groupname          text,
  realm              text,
  nasipaddress       inet NOT NULL,
  nasportid          text,
  nasporttype        text,
  acctstarttime      timestamp with time zone,
  acctupdatetime     timestamp with time zone,
  acctstoptime       timestamp with time zone,
  acctinterval       bigint,
  acctsessiontime    bigint,
  acctauthentic      text,
  connectinfo_start  text,
  connectinfo_stop   text,
  acctinputoctets    bigint,
  acctoutputoctets   bigint,
  calledstationid    text,
  callingstationid   text,
  acctterminatecause text,
  servicetype        text,
  framedprotocol     text,
  framedipaddress    inet
);
CREATE INDEX IF NOT EXISTS radacct_active_session_idx ON radacct (acctuniqueid) WHERE acctstoptime IS NULL;
CREATE INDEX IF NOT EXISTS radacct_start_user_idx     ON radacct (acctstarttime, username);

CREATE TABLE IF NOT EXISTS radpostauth (
  id       bigserial PRIMARY KEY,
  username text NOT NULL,
  pass     text,
  reply    text,
  calledstationid  text,
  callingstationid text,
  authdate timestamp with time zone NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS radpostauth_username ON radpostauth (username);

CREATE TABLE IF NOT EXISTS nas (
  id          serial PRIMARY KEY,
  nasname     text NOT NULL,
  shortname   text NOT NULL,
  type        text NOT NULL DEFAULT 'other',
  ports       integer,
  secret      text NOT NULL,
  server      text,
  community   text,
  description text
);
CREATE INDEX IF NOT EXISTS nas_nasname ON nas (nasname);
