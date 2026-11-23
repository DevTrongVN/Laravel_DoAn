-- Migration: Add 'bookmark' to interactions table type ENUM
-- Safe and idempotent migration for Bookmark support
-- Workspace: C:\laragon\www\DoAnMaNguonMo

-- Modify column type in interactions table to include 'bookmark'
-- Preserves all existing 'like' and 'share' records without loss or conversion.
ALTER TABLE interactions MODIFY COLUMN type ENUM('like', 'share', 'bookmark') NOT NULL;
