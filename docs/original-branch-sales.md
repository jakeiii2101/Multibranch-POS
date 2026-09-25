# Original branch sale attribution

After the original branch stock mirror is activated, each new POS sale records that branch in `sales.branch_id`. The sale's branch cannot be changed after completion. Sales made before activation retain `NULL` as their branch; this release does not guess or rewrite historical financial records.

Before deploying this change to an activated store, assign each cashier to the original branch with an active membership in Branch Management. Admins can check out for the original branch without a membership. Cashiers assigned only to another branch cannot use the legacy POS to sell that branch's stock. Recheck cashier access before opening the register.

The register still uses the original branch's legacy product stock and shared BIR invoice sequence. Separate checkout stock, tax setup, invoice sequencing, reversal and reporting by branch require later milestones. Do not use this register to process sales for a second branch.
