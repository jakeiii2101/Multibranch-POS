# Additional branch invoice settings

After activating the original branch, an admin can open **Branch Invoices** and select an active additional branch. Enter the registered seller details, numeric invoice branch code, unique invoice prefix, starting range, and any confirmed permit details. The branch selector uses the internal branch code; the form's registered invoice branch code is separate.

Saving stores one BIR setting and one Sales Invoice sequence for that branch. It does not enable the second branch's checkout. The original register keeps using **BIR Settings**, and the original branch is excluded from this form. The branch invoice service rejects missing, inactive, or mismatched configuration.

Once a number has been issued, the form cannot change that sequence's branch code, prefix, or starting number, or reduce its ending number below the last issued value. Existing completed invoices retain their seller snapshots. Have the registration details and number range reviewed before activating a branch; saving a configuration is not an approval of the POS system.
