-- ============================================================
-- MIGRATION: Restore global (one-per-phone) bidder identity
-- Created: 2026-07-02
--
-- Migration 005 introduced a composite UNIQUE (phone_number, event_id) to allow
-- the same phone to be a distinct bidder per event. The application, however,
-- treats a bidder as ONE global identity per phone everywhere (bids, My Bids,
-- and sessions are all global, and getOrCreateUser() never set event_id — so new
-- signups landed with event_id = NULL, where MySQL's NULL-is-distinct unique
-- semantics allowed race-condition duplicates).
--
-- This migration aligns the schema with the app: a single global UNIQUE on
-- phone_number, which also makes getOrCreateUser()'s INSERT ... ON DUPLICATE KEY
-- UPDATE race-safe.
--
-- IMPORTANT: before running in production, resolve any existing duplicate
-- phone_number rows (merge their bids/transactions onto one canonical user id),
-- or this ALTER will fail with a duplicate-key error. Example to find them:
--   SELECT phone_number, COUNT(*) FROM users GROUP BY phone_number HAVING COUNT(*) > 1;
-- ============================================================

ALTER TABLE users DROP INDEX uq_phone_event;
ALTER TABLE users ADD UNIQUE KEY uq_phone (phone_number);
