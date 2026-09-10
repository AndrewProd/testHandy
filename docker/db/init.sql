-- Runs once, only when the Postgres data volume is first created.
-- Tests use a separate database so `RefreshDatabase` never touches dev data.
CREATE DATABASE reply_center_test OWNER app;
