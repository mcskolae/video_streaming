CREATE DATABASE homeflix_test OWNER homeflix;
\connect homeflix
CREATE EXTENSION IF NOT EXISTS pg_trgm;
\connect homeflix_test
CREATE EXTENSION IF NOT EXISTS pg_trgm;
