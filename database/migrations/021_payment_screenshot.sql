-- Store proof screenshot for contractor payments
ALTER TABLE local_payments
    ADD COLUMN payment_screenshot_path VARCHAR(500) NULL AFTER transaction_reference;
