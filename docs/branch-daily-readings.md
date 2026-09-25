# Additional branch daily readings

Admins can select an active additional branch and preview attributed invoices and sales for a business date. The X-reading filters sales, full reversals, and partial refunds by the selected sale's branch. Historical sales with `sales.branch_id = NULL` and sales from another branch are excluded.

A branch Z-reading requires the admin password, confirmation, and active branch invoice settings. It creates an immutable snapshot for that branch and date. Another branch or the original register can have its own closing on the same date. A duplicate branch/date closing is rejected.

This PR introduces the branch closing record and preview. Additional branch checkout is still disabled. The checkout milestone must check `branch_daily_closings` for the selected branch under the same branch lock before accepting a new sale, and must keep the original register's legacy closing path separate. Readings use the application business timezone (`APP_TIMEZONE`, currently Asia/Manila); branch-specific timezones need a later explicit policy and implementation.
