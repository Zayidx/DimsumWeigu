# Supplier returns

Open **Retur pemasok** from the Mazer sidebar. Staff with `supplier-returns-create` select a completed receipt, enter quantities and a reason, choose credit or refund, and save a draft. The service reads receipt prices and PO tax from the database; submitted prices and batch IDs are never accepted.

Draft and approved requests reserve returnable receipt quantity. Cancelled requests release it. Requests cannot exceed accepted receipt quantity after earlier returns. A request UUID with a fingerprint prevents duplicate creation and rejects reuse with different content.

`supplier-returns-approve` permits draft approval and approved completion. Completion locks the receipt and return, deducts stock through `StockMutationService`, and settles finance in the same database transaction. Only the active outlet or a permitted central warehouse is accessible. Tracked products use the exact receipt batch. Expired batches may leave stock through supplier returns or waste; they remain unavailable for sales.

Return value includes the original purchase tax. Outstanding debt on the source receipt is settled first through a `supplier-credit` payable-payment entry. Original invoice amount remains intact. `paid_amount` represents total settled liability, including these credit entries, and must not be interpreted as cash paid alone.

Any remaining return value becomes either:

- A supplier credit note with an available balance. Users with `payables-pay` can apply it to an unpaid invoice for the same supplier, subject to credit/debt bounds and scoped warehouse access. A payment UUID prevents duplicate application.
- A confirmed supplier refund. Bank-transfer refunds require a reference. Cash refunds require the operator's open shift at the source warehouse and create a shift inflow plus warehouse cash ledger entry. A missing shift rolls back stock and financial changes together. Monetary refunds require `payables-pay` in addition to return approval.

Completed returns are immutable and completion retries do not create additional stock mutations, payments, credits, or refunds. A draft can be cancelled; completed returns cannot be cancelled through this workflow.

## Verification

`tests/Feature/SupplierReturnsTest.php` covers invoice offsets, paid-invoice credits, credit application replay, duplicate quantity prevention, cancellation, exact expired-batch return, cash refund reconciliation, staff page actions, missing-shift rollback, and forged cross-outlet IDs. SQLite coverage does not prove MySQL concurrency behavior; production concurrency and device/provider QA remain part of the overall completion audit.
