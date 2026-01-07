-- Optional DB update for Alarm Level stars (run once)
ALTER TABLE incidents
  ADD COLUMN alarm_level TINYINT UNSIGNED NULL DEFAULT 0 AFTER safety_checklist_notes;
