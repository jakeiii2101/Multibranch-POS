# Additional branch stock

After the original branch mirror is activated, an admin or a manager/supervisor assigned to an active additional branch can record stock in and stock out from **Branch Inventory**. Each movement changes only that branch's `branch_products.on_hand` and creates a branch-attributed stock movement and audit entry. It does not change `products.stock_quantity`, the original branch's balance, or the shared POS register.

Select the branch, product, movement, quantity and reason. A first stock-in creates a branch balance using the product's low-stock level as its reorder level. Stock-out cannot make a balance negative. Existing reserved units are not changed by this screen; a future allocation workflow must account for reservations before selling.

The original branch is excluded from this screen; continue using legacy Inventory for its stock. Do not treat an additional branch as checkout-ready: per-branch sales, BIR invoicing, returns, and readings need further work.
