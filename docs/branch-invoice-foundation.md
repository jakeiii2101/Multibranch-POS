# Branch invoice configuration foundation

The existing BIR Settings page and legacy POS continue to use records with `branch_id = NULL`. Branch-linked `bir_settings` and `invoice_sequences` records can now be stored separately for an additional branch. `InvoiceNumberService::next($branch)` uses only that branch's active setting and matching active sequence. It fails if either is missing; it never substitutes the original store's configuration.

This is a schema and service milestone. There is no branch configuration form or second-branch checkout in this PR. The next milestone should provide an admin-only configuration flow that validates each branch's registered details and invoice numbering before using `next($branch)` in checkout. Keep the existing BIR Settings page for the original register.

`branches.code` is an internal code; `bir_settings.branch_code` is the invoice registration code. They are separate fields. An invoice sequence remains unique by document type and registration branch code, and a branch can have only one Sales Invoice sequence of a given document type.
