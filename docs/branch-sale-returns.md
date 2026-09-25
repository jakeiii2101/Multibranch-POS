# Branch sale returns

The reversal and partial refund services now use the completed sale's immutable `branch_id` when stock is returned. Sales for the original branch, including older sales with no branch ID, keep the legacy product stock and original branch mirror behavior. A sale for an additional branch restores only that branch's balance and writes a movement attributed to it. A non-restockable refund changes no stock.

An additional branch's Z-reading blocks reversals and refunds for that branch on the closed business date. The original register keeps its existing Z-reading lock. The financial adjustment and stock movement occur in one transaction; if the branch balance is missing, the operation rolls back rather than changing another branch's stock.

This prepares returns for a later checkout milestone. The existing admin sales screens still control who can authorize returns, and no additional branch checkout is enabled by this PR.
