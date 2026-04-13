-- Fix playoff transaction months: JSB stores month=10 for playoffs,
-- but IBL calendar defines playoffs as month=6 (Season::IBL_PLAYOFF_MONTH).
-- Month 10 is IBL_HEAT_MONTH (October preseason tournament). There are
-- no actual HEAT transactions in the database, so all month=10 rows are
-- playoff transactions that need remapping.

UPDATE ibl_jsb_transactions
SET transaction_month = 6
WHERE transaction_month = 10;
