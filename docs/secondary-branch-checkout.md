# Second branch register pilot

1. Activate the original branch inventory mirror and reconcile its balances.
2. Create an active second branch, assign a cashier to it, and enter its product balances and optional prices.
3. Configure and activate that branch's invoice settings and number sequence. Keep the original invoice configuration for `/pos`.
4. Open **POS → Choose a register**, select the second branch, and make a small test sale. Check the invoice, branch stock movement, and Branch Daily Readings before normal use.
5. Check that closing the second branch's business date blocks only its register; the original register uses its own Z-reading.

The original `/pos` route and `Product.stock_quantity` remain the original register. A second branch sale changes only its `branch_products.on_hand`. The register rechecks branch access, stock, price, closing, and invoicing inside its checkout transaction. An invoice receipt is accessible to its cashier and administrators.
