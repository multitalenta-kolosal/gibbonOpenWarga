ALTER TABLE gibbonMessenger
ADD COLUMN whatsapp enum('N','Y') DEFAULT 'N' AFTER email;
