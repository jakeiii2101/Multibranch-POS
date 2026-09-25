# Branch monitor

The Branch Monitor is a read-only view for admins, managers, and supervisors. Managers can see all their active assigned branches, while supervisors see their assigned branch. Revoking an assignment removes it from the next request. Admins see all active branches. Changing the branch selector to an unauthorized ID returns 404.

The sales column includes today's completed, non-reversed sales with an explicit `sales.branch_id`. It is **before refunds**, and older sales with no branch ID are excluded. Inventory columns read `branch_products` balances, including stock entered for future branches; they do not imply that checkout is enabled for those branches. An empty branch shows zero totals.

After original branch activation, cashiers are sent to POS instead of the global dashboard. Managers and supervisors are sent to Branch Monitor from `/dashboard`. This does not yet provide branch-separated invoice numbering, returns, daily readings, or full financial reporting.
