ALTER TABLE gibbonMessengerReceipt
MODIFY COLUMN contactType enum('Email', 'SMS', 'Whatsapp') DEFAULT NULL;
