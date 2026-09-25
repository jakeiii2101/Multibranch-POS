# Original branch stock cutover

This procedure copies existing `products.stock_quantity` into the original branch and activates the transactional stock mirror. It does not change checkout into a branch-selecting workflow, and it does not assign historical movements to a branch.

1. Deploy this PR and run `php artisan migrate --force` while the existing POS is still available. Confirm that the original branch exists, has the correct internal code, and is active.
2. Preview with `php artisan inventory:activate-original-branch ORIGINAL`, replacing `ORIGINAL` with its actual code. Confirm the product and unit totals. The original branch must have no `branch_products` rows; do not delete rows to satisfy this check without reconciliation.
3. Schedule a maintenance window and stop background workers, scheduled stock jobs, and any other writers using the database. Run `php artisan down`. Check that no direct database writer can change products during the cutover.
4. Run `php artisan inventory:activate-original-branch ORIGINAL --apply --confirm-code=ORIGINAL`. The command creates a MySQL or SQLite backup under the local storage disk's `backups` directory, writes a `.sha256` checksum beside it, and aborts if the backup fails. Keep a separate copy of both files before reopening the POS.
5. Run `php artisan inventory:branch-audit ORIGINAL --strict`. It should exit successfully with no missing or different balances. Check `SELECT branch_id, activated_at FROM original_branch_inventory WHERE id = 1;` and confirm the intended branch ID. Then run `php artisan up` and restart workers.

The activation marker and copied balances commit together. Repeating the command fails after activation. A failed attempt before the commit leaves no marker and rolls back copied balances; retain its backup for investigation. If the strict audit fails after activation, keep the POS in maintenance mode and investigate before restoring service. Stock writes after activation verify the previous balance and roll back if the legacy and branch values diverge.
