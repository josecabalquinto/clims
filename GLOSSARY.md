# Computer Laboratory Inventory Management

The laboratory's equipment, borrowing records, and personnel responsible for maintaining them.

## Language

**Equipment record**:
An inventory entry representing one individually tracked asset or a batch of identical items at one location. A batch may contain both serviceable and defective units; items requiring individual condition or location tracking have separate equipment records.
_Avoid_: Individual asset when referring to a batch

**Equipment unit**:
One physical item counted within an equipment record. Equipment totals count units, while equipment-record counts count inventory entries.

**Available quantity**:
The number of serviceable units currently available to borrow.

**Outstanding quantity**:
The number of borrowed units that have not yet been returned, regardless of whether their return deadline has passed.

**Defective quantity**:
The number of returned or otherwise on-hand units that are unfit for borrowing.

**Asset tag**:
The unique identifier of an equipment record, whether that record represents one asset or a batch.
_Avoid_: Serial number

**Serial number**:
A manufacturer's identifier for an individually tracked asset.
_Avoid_: Asset tag

**Borrower**:
A student, instructor, staff member, or other person receiving laboratory equipment on loan. A borrower does not require a system account.

**Borrowing transaction**:
A recorded loan of one or more equipment items to a borrower, with a borrowing time and an expected return deadline.

**Return**:
A recorded handover of some or all outstanding equipment from a borrowing transaction, attributed to the personnel who processed it and the time it occurred. It distinguishes serviceable units from defective units received.

**Overdue**:
A borrowing transaction with equipment still outstanding after its expected return date and time.

**Lab Staff**:
Authorized laboratory personnel who manage daily inventory and borrowing operations without user-management privileges.
_Avoid_: Manager as a separate role

**Super Admin**:
Authorized personnel with access to every application module, including user management.

**Inactive account**:
A retained personnel account that cannot access protected application features.
_Avoid_: Deleted account
